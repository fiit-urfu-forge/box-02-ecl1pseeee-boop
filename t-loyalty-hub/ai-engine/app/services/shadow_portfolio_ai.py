"""Shadow Portfolio AI: real PyTorch + sentence-transformers implementation.

Computes the gap between a user's actual cashback and the "ideal shadow"
cashback they could earn with an optimal program/offer mix.

Pipeline:
1. ShadowEfficiencyMLP — small PyTorch MLP that, given user features,
   predicts a "ceiling multiplier" k such that shadow_cashback = k * real_cashback.
   Trained self-supervised on the dataset's own users with target
   k* = max_monthly_cashback / mean_monthly_cashback (each user's own
   demonstrated ceiling versus their floor).
2. OfferRanker — multilingual MiniLM bi-encoder that ranks partner offers
   by semantic relevance to the user's loyalty-program profile, blended
   with cashback_percent. Filters by financial_segment.
3. InsightGenerator — Russian-language template parametrised with real
   numbers and the top-ranked offer.

Models load and (the MLP) trains lazily on first request. Trained weights
are cached to disk so subsequent container starts skip training.
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


_SEARCH_MODEL = "paraphrase-multilingual-MiniLM-L12-v2"
_FEATURE_DIM = 9
_TRAIN_EPOCHS = 600
_TRAIN_LR = 5e-3
_TORCH_SEED = 42

_SEGMENTS = ("LOW", "MEDIUM", "HIGH")

# Effective cashback rate per program currency, used to back-infer spend
# volume from observed cashback. Calibrated to typical T-Bank programs.
_PROGRAM_RATE = {
    "rub": 0.01,           # Black: ~1% baseline
    "miles": 0.0067,       # All Airlines: ~1 mile / 30 ₽, ≈1.5 ₽ per mile
    "bravo-points": 0.01,  # Bravo: ~1% equivalent
}

# Anchor descriptions used to embed each loyalty program in the same
# space as offers — drives the OfferRanker's relevance score.
_PROGRAM_DESCRIPTIONS = {
    "All Airlines": "путешествия, авиабилеты, отели, мили, поездки, отпуск",
    "Black": "повседневные покупки, рестораны, супермаркеты, кэшбэк рублями",
    "Bravo": "развлечения, кино, рестораны, шопинг, подарки, бонусы",
}

# Cap how much of total spend can realistically flow through partner
# offers — beyond this the marginal benefit collapses.
_MAX_PARTNER_SHARE = 0.55
_MIN_HEALTH_SCORE_FLOOR = 5  # avoid scary 0/100 even for tiny histories


@dataclass
class ShadowOffer:
    partner_name: str
    cashback_percent: float
    relevance: float


@dataclass
class ShadowPortfolioAnalysis:
    real_cashback: float
    shadow_cashback: float
    gap: float
    insight: str
    health_score: int
    top_offers: list[ShadowOffer] = field(default_factory=list)


def _segment_one_hot(segment: str) -> list[float]:
    seg = (segment or "LOW").upper()
    return [1.0 if seg == s else 0.0 for s in _SEGMENTS]


class ShadowPortfolioAI:
    """Inference-only wrapper. Trains the MLP once on first call, caches to disk."""

    def __init__(self, data_dir: str, weights_path: Optional[str] = None) -> None:
        self.data_dir = Path(data_dir)
        self.weights_path = Path(weights_path) if weights_path else self.data_dir.parent / ".cache" / "shadow_portfolio.pt"
        self._data_lock = threading.Lock()
        self._models_lock = threading.Lock()
        self._data_ready = False
        self._models_ready = False
        self._model: Any = None
        self._search_model: Any = None
        self._program_embeddings: Any = None  # dict[program_name, tensor]
        self._offer_embeddings: Any = None
        self._users: Any = None
        self._accounts: Any = None
        self._programs: Any = None
        self._history: Any = None
        self._offers: Any = None

    # ----- Lazy loading & training -----------------------------------------

    def _ensure_data_loaded(self) -> None:
        """Light: just read CSVs. Cheap — used for user-existence check."""
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
        """Heavy: download MiniLM, embed offers, train (or load) the MLP."""
        if self._models_ready:
            return
        self._ensure_data_loaded()
        with self._models_lock:
            if self._models_ready:
                return

            logger.info("Loading Shadow Portfolio AI models — first call, may take ~1 min.")
            from sentence_transformers import SentenceTransformer

            search_model = SentenceTransformer(_SEARCH_MODEL)
            self._search_model = search_model
            self._program_embeddings = {
                name: search_model.encode(desc, convert_to_tensor=True)
                for name, desc in _PROGRAM_DESCRIPTIONS.items()
            }
            offers_text = (
                self._offers["partner_name"].astype(str) + " — " + self._offers["short_description"].astype(str)
            ).tolist()
            self._offer_embeddings = search_model.encode(offers_text, convert_to_tensor=True)

            self._model = self._build_or_train_mlp(self._users, self._accounts, self._programs, self._history)
            self._models_ready = True
            logger.info("Shadow Portfolio AI ready: %d users indexed.", len(self._users))

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

        class ShadowEfficiencyMLP(nn.Module):
            def __init__(self) -> None:
                super().__init__()
                self.net = nn.Sequential(
                    nn.Linear(_FEATURE_DIM, 32),
                    nn.ReLU(),
                    nn.Linear(32, 16),
                    nn.ReLU(),
                    nn.Linear(16, 1),
                    nn.Softplus(),  # k > 0
                )

            def forward(self, x: "torch.Tensor") -> "torch.Tensor":
                # k is positive; shift so k >= 1 (shadow >= real)
                return 1.0 + self.net(x).squeeze(-1)

        model = ShadowEfficiencyMLP()

        if self.weights_path.exists():
            try:
                model.load_state_dict(torch.load(self.weights_path, map_location="cpu", weights_only=True))
                model.eval()
                logger.info("Loaded cached MLP weights from %s", self.weights_path)
                return model
            except Exception as exc:  # noqa: BLE001 — corrupted cache shouldn't crash service
                logger.warning("Cached MLP weights unusable (%s); retraining.", exc)

        feats, targets = self._build_training_set(users, accounts, programs, history)
        if feats.shape[0] < 2:
            logger.warning("Not enough users to train MLP — using untrained init.")
            model.eval()
            return model

        opt = torch.optim.Adam(model.parameters(), lr=_TRAIN_LR)
        loss_fn = nn.SmoothL1Loss()
        model.train()
        for epoch in range(_TRAIN_EPOCHS):
            opt.zero_grad()
            pred = model(feats)
            loss = loss_fn(pred, targets)
            loss.backward()
            opt.step()
            if epoch % 100 == 0:
                logger.debug("MLP epoch %d  loss=%.4f", epoch, float(loss))

        model.eval()
        try:
            self.weights_path.parent.mkdir(parents=True, exist_ok=True)
            torch.save(model.state_dict(), self.weights_path)
            logger.info("Saved MLP weights to %s", self.weights_path)
        except OSError as exc:
            logger.warning("Could not persist MLP weights (%s); inference still works.", exc)

        return model

    def _build_training_set(
        self,
        users: "pd.DataFrame",
        accounts: "pd.DataFrame",
        programs: "pd.DataFrame",
        history: "pd.DataFrame",
    ) -> tuple["torch.Tensor", "torch.Tensor"]:
        import torch

        feats: list[list[float]] = []
        targets: list[float] = []

        for _, user in users.iterrows():
            uid = int(user["id"])
            f = self._features_for_user(uid)
            if f is None:
                continue

            user_accounts = accounts[accounts["user_id"] == uid]
            user_history = history[history["account_id"].isin(user_accounts["account_id"])]
            if user_history.empty:
                continue

            monthly = (
                user_history
                .assign(month=user_history["payout_date"].dt.to_period("M"))
                .groupby("month")["cashback_amount"]
                .sum()
            )
            if monthly.empty or monthly.mean() <= 0:
                continue

            ratio = float(monthly.max() / monthly.mean())
            ratio = max(1.05, min(ratio, 3.5))  # clip to sane range
            feats.append(f)
            targets.append(ratio)

        feats_t = torch.tensor(feats, dtype=torch.float32)
        targets_t = torch.tensor(targets, dtype=torch.float32)
        return feats_t, targets_t

    # ----- Per-user feature extraction --------------------------------------

    def _features_for_user(self, user_id: int) -> Optional[list[float]]:
        users = self._users
        accounts = self._accounts
        programs = self._programs
        history = self._history

        user_row = users[users["id"] == user_id]
        if user_row.empty:
            return None
        segment = str(user_row.iloc[0]["financial_segment"])

        user_accounts = accounts[accounts["user_id"] == user_id]
        if user_accounts.empty:
            return None

        program_ids = set(int(p) for p in user_accounts["loyalty_program_id"].tolist())
        all_program_ids = set(int(p) for p in programs["loyalty_program_id"].tolist())
        program_coverage = len(program_ids) / max(1, len(all_program_ids))

        user_history = history[history["account_id"].isin(user_accounts["account_id"])]
        n_tx = int(len(user_history))
        if n_tx == 0:
            tx_per_week = 0.0
            avg_tx = 0.0
            days_active = 0.0
        else:
            span_days = max(
                1.0,
                float((user_history["payout_date"].max() - user_history["payout_date"].min()).days),
            )
            tx_per_week = n_tx / (span_days / 7.0)
            avg_tx = float(user_history["cashback_amount"].mean())
            days_active = span_days

        avg_balance = float(user_accounts["current_balance"].mean())

        return [
            *_segment_one_hot(segment),                  # 3
            float(len(user_accounts)),                   # 1
            program_coverage,                            # 1
            avg_balance / 100_000.0,                     # 1  (scaled)
            tx_per_week,                                 # 1
            avg_tx / 1000.0,                             # 1  (scaled)
            days_active / 30.0,                          # 1  (scaled, "months active")
        ]

    # ----- Public inference -------------------------------------------------

    def analyze(self, user_id: int) -> Optional[ShadowPortfolioAnalysis]:
        """Returns None when the user is unknown — caller should fall back to stub.

        Avoids loading the heavy ML stack for users that aren't in the dataset.
        """
        self._ensure_data_loaded()

        feats = self._features_for_user(user_id)
        if feats is None:
            return None

        # User exists — now we definitely need the models.
        self._ensure_models_loaded()
        import torch

        users = self._users
        accounts = self._accounts
        programs = self._programs
        history = self._history
        offers = self._offers

        user_row = users[users["id"] == user_id].iloc[0]
        segment = str(user_row["financial_segment"]).upper()

        user_accounts = accounts[accounts["user_id"] == user_id]
        user_history = history[history["account_id"].isin(user_accounts["account_id"])]
        real_cashback = float(user_history["cashback_amount"].sum())

        # MLP-predicted ceiling multiplier (k >= 1)
        with torch.inference_mode():
            x = torch.tensor([feats], dtype=torch.float32)
            k_mlp = float(self._model(x).item())
        k_mlp = max(1.05, min(k_mlp, 3.0))

        # Offer-driven uplift: best partner cashback the user could route spend through
        top_offers = self._rank_offers(user_accounts, programs, segment, offers, top_k=3)
        inferred_spend = self._infer_spend(user_accounts, programs, user_history)
        program_uplift = self._program_uplift(top_offers, segment)
        offer_shadow = inferred_spend * program_uplift * _MAX_PARTNER_SHARE

        shadow_cashback = max(real_cashback * k_mlp, real_cashback + offer_shadow)
        if shadow_cashback < real_cashback:
            shadow_cashback = real_cashback  # safety
        gap = round(shadow_cashback - real_cashback, 2)

        if shadow_cashback > 0:
            score = int(round((real_cashback / shadow_cashback) * 100))
        else:
            score = 0
        score = max(_MIN_HEALTH_SCORE_FLOOR, min(100, score))

        insight = self._build_insight(
            real=real_cashback,
            shadow=shadow_cashback,
            gap=gap,
            score=score,
            segment=segment,
            top_offers=top_offers,
        )

        return ShadowPortfolioAnalysis(
            real_cashback=round(real_cashback, 2),
            shadow_cashback=round(shadow_cashback, 2),
            gap=gap,
            insight=insight,
            health_score=score,
            top_offers=top_offers,
        )

    # ----- Helpers ----------------------------------------------------------

    def _rank_offers(
        self,
        user_accounts: "pd.DataFrame",
        programs: "pd.DataFrame",
        segment: str,
        offers: "pd.DataFrame",
        top_k: int = 3,
    ) -> list[ShadowOffer]:
        from sentence_transformers import util

        eligible = offers[offers["financial_segment"].isin([segment, "ALL"])]
        if eligible.empty:
            eligible = offers

        # Average program embedding across the user's programs
        user_program_names = (
            programs[programs["loyalty_program_id"].isin(user_accounts["loyalty_program_id"])]["loyalty_program_name"]
            .astype(str)
            .tolist()
        )
        embs = [self._program_embeddings[n] for n in user_program_names if n in self._program_embeddings]
        if not embs:
            embs = list(self._program_embeddings.values())

        import torch
        profile_emb = torch.stack(embs).mean(dim=0)

        eligible_idx = eligible.index.tolist()
        eligible_offer_embs = self._offer_embeddings[eligible_idx]
        sims = util.cos_sim(profile_emb, eligible_offer_embs)[0].cpu().tolist()

        scored: list[ShadowOffer] = []
        for local_i, df_i in enumerate(eligible_idx):
            row = eligible.loc[df_i]
            cashback = float(row["cashback_percent"])
            relevance = float(sims[local_i])
            scored.append(ShadowOffer(
                partner_name=str(row["partner_name"]),
                cashback_percent=cashback,
                relevance=round(relevance, 3),
            ))

        # Sort by blended score: cashback% scaled by relevance (relevance shifted to [0.5, 1.5])
        scored.sort(key=lambda o: o.cashback_percent * (1.0 + o.relevance), reverse=True)
        return scored[:top_k]

    def _infer_spend(
        self,
        user_accounts: "pd.DataFrame",
        programs: "pd.DataFrame",
        user_history: "pd.DataFrame",
    ) -> float:
        if user_history.empty:
            return 0.0

        merged = user_history.merge(
            user_accounts[["account_id", "loyalty_program_id"]],
            on="account_id",
            how="left",
        ).merge(
            programs[["loyalty_program_id", "cashback_currency"]],
            on="loyalty_program_id",
            how="left",
        )

        spend = 0.0
        for _, row in merged.iterrows():
            currency = str(row.get("cashback_currency") or "rub")
            rate = _PROGRAM_RATE.get(currency, 0.01)
            spend += float(row["cashback_amount"]) / rate
        return spend

    @staticmethod
    def _program_uplift(top_offers: list[ShadowOffer], segment: str) -> float:
        """Average top-3 cashback% (as fraction) — what fraction of spend could earn this rate."""
        if not top_offers:
            return 0.03  # conservative default
        avg_pct = sum(o.cashback_percent for o in top_offers) / len(top_offers)
        # Higher segments can route more spend at higher rates
        seg_boost = {"HIGH": 1.15, "MEDIUM": 1.0, "LOW": 0.9}.get(segment, 1.0)
        return (avg_pct / 100.0) * seg_boost

    @staticmethod
    def _build_insight(
        *,
        real: float,
        shadow: float,
        gap: float,
        score: int,
        segment: str,
        top_offers: list[ShadowOffer],
    ) -> str:
        if not top_offers or gap < 50:
            return (
                f"Твой кэшбэк-портфель близок к идеалу — Health Score {score}/100. "
                f"Оптимизировать почти нечего, продолжай в том же духе."
            )

        best = top_offers[0]
        ratio = shadow / real if real > 0 else 1.0

        if score >= 75:
            return (
                f"Сильный результат: используешь {score}% потенциала. "
                f"Добавь «{best.partner_name}» (кэшбэк {best.cashback_percent:.0f}%) — "
                f"это закроет оставшийся разрыв в {gap:.0f} ₽."
            )
        if score >= 40:
            return (
                f"Ты теряешь {gap:.0f} ₽ — это {ratio:.1f}× от текущего кэшбэка. "
                f"Главный рычаг: «{best.partner_name}», {best.cashback_percent:.0f}% возврата. "
                f"Активируй и подними Health Score выше {min(100, score + 20)}."
            )
        return (
            f"Сейчас ты используешь только {score}% возможностей программы. "
            f"Разрыв — {gap:.0f} ₽. Старт: подключи «{best.partner_name}» "
            f"({best.cashback_percent:.0f}%) и пересмотри активные категории."
        )


_singleton: Optional[ShadowPortfolioAI] = None
_singleton_lock = threading.Lock()


def get_shadow_portfolio_ai() -> ShadowPortfolioAI:
    global _singleton
    if _singleton is not None:
        return _singleton
    with _singleton_lock:
        if _singleton is None:
            data_dir = os.environ.get("AI_DATA_DIR", "/app/data")
            weights_path = os.environ.get("SHADOW_PORTFOLIO_WEIGHTS")
            _singleton = ShadowPortfolioAI(data_dir=data_dir, weights_path=weights_path)
    return _singleton
