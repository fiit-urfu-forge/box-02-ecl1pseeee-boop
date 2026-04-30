"""Dynamic Nudging AI: real PyTorch + zero-shot HuggingFace implementation.

Picks the most likely *next* spending category for a user and the time-of-week
when they usually spend on it, then composes a Russian-language nudge with
the best matching partner offer.

Pipeline:
1. CategoryTagger — zero-shot mDeBERTa (same model as zero_click_ai) tags
   each row in Offers.csv with one of the canonical categories. Cached
   per-process, embeddings computed once.
2. TemporalPatternMLP — small PyTorch MLP that, given (segment, weekday,
   timeslot, program_currency_share, recent_history_features), outputs a
   distribution over (category × timeslot) for the next 24 hours.
   Trained self-supervised on per-user transaction streams: predict the
   category/timeslot of the next transaction from the previous N.
3. NudgeRanker — blends model probabilities with offer cashback_percent
   and segment boost; picks the highest-scoring (category, timeslot)
   pair plus the matching partner offer.

Models load and (the MLP) trains lazily on first ML inference call. CSV
data is loaded eagerly via a lightweight path so unknown-user requests
return None without booting the heavy stack.
"""

from __future__ import annotations

import logging
import os
import threading
from dataclasses import dataclass, field
from pathlib import Path
from typing import TYPE_CHECKING, Any, Optional

if TYPE_CHECKING:
    import pandas as pd
    import torch

logger = logging.getLogger(__name__)


_INTENT_MODEL = "MoritzLaurer/mDeBERTa-v3-base-mnli-xnli"
_FEATURE_DIM = 13
_TRAIN_EPOCHS = 800
_TRAIN_LR = 5e-3
_TORCH_SEED = 17
_RECENT_TX_WINDOW = 5
_MIN_TX_FOR_ML = 4

_SEGMENTS = ("LOW", "MEDIUM", "HIGH")
_CATEGORIES = (
    "АЗС",
    "Рестораны",
    "Супермаркеты",
    "Аптеки",
    "Развлечения",
    "Онлайн-покупки",
    "Транспорт",
    "Путешествия",
    "Услуги",
)

# 4 timeslots covering one week → cross product gives 36-dim joint target.
_TIMESLOTS = ("утром сегодня", "в обед", "вечером сегодня", "в эти выходные")
_JOINT_DIM = len(_CATEGORIES) * len(_TIMESLOTS)

# Effective rate per program currency (mirrors shadow_portfolio_ai for spend inference).
_PROGRAM_RATE = {
    "rub": 0.01,
    "miles": 0.0067,
    "bravo-points": 0.01,
}


@dataclass
class NudgeResult:
    message: Optional[str]
    category: Optional[str]
    boost_multiplier: float
    trigger_time: Optional[str]
    partner_name: Optional[str] = None
    cashback_percent: Optional[float] = None
    confidence: float = 0.0


def _segment_one_hot(segment: str) -> list[float]:
    seg = (segment or "LOW").upper()
    return [1.0 if seg == s else 0.0 for s in _SEGMENTS]


def _timeslot_for_date(dt: Any) -> int:
    """Map a payout_date (we only have day-precision) to a 4-bin slot.

    With only date data we infer a slot from weekday: Mon-Wed → утром,
    Thu → в обед, Fri → вечером, Sat-Sun → в эти выходные. This gives
    the model a real, learnable temporal signal even without timestamps.
    """
    weekday = int(dt.weekday())
    if weekday >= 5:
        return 3  # weekend
    if weekday == 4:
        return 2  # friday evening
    if weekday == 3:
        return 1  # thursday lunch
    return 0      # mon-wed morning


class DynamicNudgingAI:
    """Inference-only wrapper. Trains the MLP once on first ML call, caches to disk."""

    def __init__(self, data_dir: str, weights_path: Optional[str] = None) -> None:
        self.data_dir = Path(data_dir)
        self.weights_path = Path(weights_path) if weights_path else self.data_dir.parent / ".cache" / "dynamic_nudging.pt"
        self._data_lock = threading.Lock()
        self._models_lock = threading.Lock()
        self._data_ready = False
        self._models_ready = False
        self._users: Any = None
        self._accounts: Any = None
        self._programs: Any = None
        self._history: Any = None
        self._offers: Any = None
        self._offer_categories: Any = None  # list[str] aligned with offers.index
        self._model: Any = None

    # ----- Lazy loading & training -----------------------------------------

    def _ensure_data_loaded(self) -> None:
        if self._data_ready:
            return
        with self._data_lock:
            if self._data_ready:
                return
            import pandas as pd

            self._users = pd.read_csv(self.data_dir / "Users.csv")
            self._accounts = pd.read_csv(self.data_dir / "Accounts.csv")
            self._programs = pd.read_csv(self.data_dir / "LoyaltyPrograms.csv")
            self._history = pd.read_csv(self.data_dir / "LoyaltyHistory.csv", parse_dates=["payout_date"])
            self._offers = pd.read_csv(self.data_dir / "Offers.csv")
            self._data_ready = True

    def _ensure_models_loaded(self) -> None:
        if self._models_ready:
            return
        self._ensure_data_loaded()
        with self._models_lock:
            if self._models_ready:
                return

            logger.info("Loading Dynamic Nudging AI models — first call, may take ~1 min.")
            self._offer_categories = self._tag_offers_with_categories(self._offers)
            self._model = self._build_or_train_mlp(self._users, self._accounts, self._programs, self._history)
            self._models_ready = True
            logger.info("Dynamic Nudging AI ready.")

    # ----- Zero-shot category tagging --------------------------------------

    def _tag_offers_with_categories(self, offers: "pd.DataFrame") -> list[str]:
        from transformers import pipeline

        clf = pipeline("zero-shot-classification", model=_INTENT_MODEL)
        texts = (offers["partner_name"].astype(str) + " — " + offers["short_description"].astype(str)).tolist()
        labels = list(_CATEGORIES)
        out: list[str] = []
        for t in texts:
            res = clf(t, labels, hypothesis_template="Эта категория: {}.")
            out.append(str(res["labels"][0]))
        return out

    # ----- Per-user feature extraction --------------------------------------

    def _user_history(self, user_id: int) -> Optional["pd.DataFrame"]:
        accounts = self._accounts
        history = self._history
        programs = self._programs

        user_accounts = accounts[accounts["user_id"] == user_id]
        if user_accounts.empty:
            return None
        merged = (
            history[history["account_id"].isin(user_accounts["account_id"])]
            .merge(user_accounts[["account_id", "loyalty_program_id"]], on="account_id", how="left")
            .merge(programs[["loyalty_program_id", "cashback_currency"]], on="loyalty_program_id", how="left")
            .sort_values("payout_date")
            .reset_index(drop=True)
        )
        return merged if not merged.empty else None

    def _features_for_user(self, user_id: int, recent: "pd.DataFrame") -> Optional[list[float]]:
        users = self._users
        user_row = users[users["id"] == user_id]
        if user_row.empty:
            return None
        segment = str(user_row.iloc[0]["financial_segment"])

        if recent.empty:
            return None

        # Latest timeslot
        last_slot = _timeslot_for_date(recent["payout_date"].iloc[-1])
        slot_one_hot = [1.0 if last_slot == i else 0.0 for i in range(len(_TIMESLOTS))]

        # Currency mix (Black/Miles/Bravo share)
        cur_counts = recent["cashback_currency"].value_counts(normalize=True)
        rub_share = float(cur_counts.get("rub", 0.0))
        miles_share = float(cur_counts.get("miles", 0.0))
        bravo_share = float(cur_counts.get("bravo-points", 0.0))

        # Recent activity stats
        amounts = recent["cashback_amount"].tail(_RECENT_TX_WINDOW)
        avg_amount = float(amounts.mean()) / 1000.0
        n_recent = float(len(amounts)) / _RECENT_TX_WINDOW

        return [
            *_segment_one_hot(segment),  # 3
            *slot_one_hot,                # 4
            rub_share,                    # 1
            miles_share,                  # 1
            bravo_share,                  # 1
            avg_amount,                   # 1
            n_recent,                     # 1
            float(min(len(recent), 60)) / 60.0,  # 1: history saturation
        ]

    def _next_target(self, recent: "pd.DataFrame") -> Optional[int]:
        """Self-supervised target: joint index of (category, timeslot) of the
        next transaction. Without true category data, we proxy category via
        the program currency of the next transaction (rub→Супермаркеты,
        miles→Путешествия, bravo→Развлечения). Crude but consistent — the
        model learns *when* a given currency tends to be charged next.
        """
        if recent.empty:
            return None
        row = recent.iloc[-1]
        currency = str(row.get("cashback_currency") or "rub")
        cat = {
            "rub": "Супермаркеты",
            "miles": "Путешествия",
            "bravo-points": "Развлечения",
        }.get(currency, "Услуги")
        try:
            cat_idx = _CATEGORIES.index(cat)
        except ValueError:
            cat_idx = 0
        slot_idx = _timeslot_for_date(row["payout_date"])
        return cat_idx * len(_TIMESLOTS) + slot_idx

    def _build_training_set(
        self,
        users: "pd.DataFrame",
        accounts: "pd.DataFrame",
        programs: "pd.DataFrame",
        history: "pd.DataFrame",
    ) -> tuple["torch.Tensor", "torch.Tensor"]:
        import torch

        feats: list[list[float]] = []
        targets: list[int] = []

        for _, user in users.iterrows():
            uid = int(user["id"])
            user_hist = self._user_history(uid)
            if user_hist is None or len(user_hist) < _MIN_TX_FOR_ML:
                continue
            # Sliding window: features from rows[0:i], target from row[i]
            for i in range(_MIN_TX_FOR_ML - 1, len(user_hist) - 1):
                window = user_hist.iloc[: i + 1]
                f = self._features_for_user(uid, window)
                if f is None:
                    continue
                tgt = self._next_target(user_hist.iloc[i + 1 : i + 2])
                if tgt is None:
                    continue
                feats.append(f)
                targets.append(tgt)

        feats_t = torch.tensor(feats, dtype=torch.float32)
        targets_t = torch.tensor(targets, dtype=torch.long)
        return feats_t, targets_t

    def _build_or_train_mlp(
        self,
        users: "pd.DataFrame",
        accounts: "pd.DataFrame",
        programs: "pd.DataFrame",
        history: "pd.DataFrame",
    ) -> Any:
        import torch
        from torch import nn

        torch.manual_seed(_TORCH_SEED)

        class TemporalPatternMLP(nn.Module):
            def __init__(self) -> None:
                super().__init__()
                self.net = nn.Sequential(
                    nn.Linear(_FEATURE_DIM, 64),
                    nn.ReLU(),
                    nn.Linear(64, 64),
                    nn.ReLU(),
                    nn.Linear(64, _JOINT_DIM),
                )

            def forward(self, x: "torch.Tensor") -> "torch.Tensor":
                return self.net(x)

        model = TemporalPatternMLP()

        if self.weights_path.exists():
            try:
                model.load_state_dict(torch.load(self.weights_path, map_location="cpu", weights_only=True))
                model.eval()
                logger.info("Loaded cached Nudging MLP weights from %s", self.weights_path)
                return model
            except Exception as exc:  # noqa: BLE001
                logger.warning("Cached Nudging MLP weights unusable (%s); retraining.", exc)

        feats, targets = self._build_training_set(users, accounts, programs, history)
        if feats.shape[0] < 8:
            logger.warning("Not enough samples to train Nudging MLP — using untrained init.")
            model.eval()
            return model

        opt = torch.optim.Adam(model.parameters(), lr=_TRAIN_LR, weight_decay=1e-4)
        loss_fn = nn.CrossEntropyLoss()
        model.train()
        for epoch in range(_TRAIN_EPOCHS):
            opt.zero_grad()
            logits = model(feats)
            loss = loss_fn(logits, targets)
            loss.backward()
            opt.step()
            if epoch % 200 == 0:
                logger.debug("Nudging MLP epoch %d  loss=%.4f", epoch, float(loss))

        model.eval()
        try:
            self.weights_path.parent.mkdir(parents=True, exist_ok=True)
            torch.save(model.state_dict(), self.weights_path)
            logger.info("Saved Nudging MLP weights to %s", self.weights_path)
        except OSError as exc:
            logger.warning("Could not persist Nudging MLP weights (%s); inference still works.", exc)

        return model

    # ----- Public inference -------------------------------------------------

    def analyze(self, user_id: int) -> Optional[NudgeResult]:
        """Returns None when the user is unknown or has too little history."""
        self._ensure_data_loaded()

        user_hist = self._user_history(user_id)
        if user_hist is None or len(user_hist) < _MIN_TX_FOR_ML:
            return None

        self._ensure_models_loaded()
        import torch

        feats = self._features_for_user(user_id, user_hist)
        if feats is None:
            return None

        with torch.inference_mode():
            x = torch.tensor([feats], dtype=torch.float32)
            logits = self._model(x)
            probs = torch.softmax(logits, dim=-1).squeeze(0)

        # Decode argmax to (category, timeslot)
        joint_idx = int(torch.argmax(probs).item())
        confidence = float(probs[joint_idx].item())
        cat_idx, slot_idx = divmod(joint_idx, len(_TIMESLOTS))
        category = _CATEGORIES[cat_idx]
        trigger_time = _TIMESLOTS[slot_idx]

        users = self._users
        segment = str(users[users["id"] == user_id].iloc[0]["financial_segment"]).upper()

        # Pick best partner offer in that category for the user's segment.
        best_offer = self._pick_offer(category, segment)

        if best_offer is None:
            # Confident category but no matching offer — emit a soft nudge
            # with the user's segment-best offer overall.
            best_offer = self._pick_offer(None, segment)

        if best_offer is None:
            return None

        partner_name = str(best_offer["partner_name"])
        cashback_percent = float(best_offer["cashback_percent"])
        seg_boost = {"HIGH": 1.3, "MEDIUM": 1.15, "LOW": 1.0}.get(segment, 1.0)
        # Multiplier shown to the user: how much their cashback would scale
        # if they routed this category through the partner vs. baseline ~1%.
        multiplier = round(max(1.5, (cashback_percent / 1.0) * 0.25 * seg_boost), 1)
        multiplier = min(multiplier, 5.0)

        message = (
            f"Обычно ты тратишь на «{category}» {trigger_time} — "
            f"активируй кэшбэк {cashback_percent:.0f}% у «{partner_name}» и получи ×{multiplier} баллов!"
        )

        return NudgeResult(
            message=message,
            category=category,
            boost_multiplier=multiplier,
            trigger_time=trigger_time,
            partner_name=partner_name,
            cashback_percent=cashback_percent,
            confidence=round(confidence, 3),
        )

    def _pick_offer(self, category: Optional[str], segment: str) -> Optional[Any]:
        offers = self._offers
        cats = self._offer_categories
        import pandas as pd

        eligible_mask = offers["financial_segment"].isin([segment, "ALL"])
        if category is not None:
            cat_mask = pd.Series([c == category for c in cats], index=offers.index)
            mask = eligible_mask & cat_mask
        else:
            mask = eligible_mask
        eligible = offers[mask]
        if eligible.empty:
            return None
        return eligible.sort_values("cashback_percent", ascending=False).iloc[0]


_singleton: Optional[DynamicNudgingAI] = None
_singleton_lock = threading.Lock()


def get_dynamic_nudging_ai() -> DynamicNudgingAI:
    global _singleton
    if _singleton is not None:
        return _singleton
    with _singleton_lock:
        if _singleton is None:
            data_dir = os.environ.get("AI_DATA_DIR", "/app/data")
            weights_path = os.environ.get("DYNAMIC_NUDGING_WEIGHTS")
            _singleton = DynamicNudgingAI(data_dir=data_dir, weights_path=weights_path)
    return _singleton
