"""Cross-Sell Optimizer AI: real PyTorch two-tower recommender.

Ranks T-ecosystem products (Investments / Mobile / Insurance / Business / City)
for a given user by predicted affinity × monetary potential.

Pipeline:
1. ProductProfiler — sentence-transformers (multilingual MiniLM) embeds
   each ecosystem product's description, projected to a low-dim latent
   via a learned linear "product tower".
2. UserTower — small PyTorch MLP that maps user features (segment one-hot,
   account composition, balance stats, transaction cadence, program-mix
   shares) into the same latent space.
3. AffinityModel — trained with InfoNCE-style contrastive loss: the
   positive product per user is the one whose latent currency-affinity
   most closely matches the user's revealed program-currency mix; all
   other products are negatives. This is a self-supervised proxy for
   "this user already shows behavior consistent with product X".
4. PotentialEstimator — closed-form: estimates monthly potential gain
   per product as f(segment, balance, recent_cashback) using calibrated
   per-product economics, scaled by predicted affinity.
5. Ranker — sorts products by `affinity * potential * segment_boost`
   and emits top-K with personalised reasons.

Models load and (the towers) train lazily on first ML inference call.
Trained weights are cached to disk.
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
_LATENT_DIM = 16
_USER_FEATURE_DIM = 12
_TRAIN_EPOCHS = 1000
_TRAIN_LR = 5e-3
_TORCH_SEED = 71

_SEGMENTS = ("LOW", "MEDIUM", "HIGH")

# T-ecosystem catalogue. Each product has:
#   description     — for MiniLM embedding (drives the product tower)
#   currency_affinity — which loyalty-program currency it most resonates with
#                       (rub / miles / bravo-points). Used to derive the
#                       self-supervised positive label.
#   base_gain       — calibrated monthly upside in ₽ for a MEDIUM-segment user.
#   reason_template — Russian-language explanation, parameterised.
_PRODUCTS: tuple[dict, ...] = (
    {
        "name": "Т-Инвестиции",
        "description": "брокерский счёт, акции, облигации, валюта, ИИС, доходность, биржа",
        "currency_affinity": "rub",
        "base_gain": 1500.0,
        "reason_template": "Свободный остаток на счетах ({balance:.0f} ₽) уже работал бы на тебя — ИИС добавит ~{gain:.0f} ₽/мес.",
    },
    {
        "name": "Т-Мобайл",
        "description": "сотовая связь, тарифы, мобильный интернет, безлимитные звонки, eSIM",
        "currency_affinity": "rub",
        "base_gain": 350.0,
        "reason_template": "С тарифом Т-Мобайл получишь до ~{gain:.0f} ₽/мес кэшбэка обратно через единую программу.",
    },
    {
        "name": "Т-Страхование",
        "description": "страховка путешествий, авто КАСКО ОСАГО, ВЗР, медицинская, имущества",
        "currency_affinity": "miles",
        "base_gain": 800.0,
        "reason_template": "Под твой профиль поездок страховка вернёт ~{gain:.0f} ₽/мес бонусами и закроет риски.",
    },
    {
        "name": "Т-Бизнес",
        "description": "расчётный счёт ИП и ООО, эквайринг, бухгалтерия, налоги, бизнес-карты",
        "currency_affinity": "rub",
        "base_gain": 2500.0,
        "reason_template": "Объёмы транзакций показывают активность уровня самозанятого — Т-Бизнес даст до ~{gain:.0f} ₽/мес.",
    },
    {
        "name": "Т-Город",
        "description": "афиша, рестораны, кино, концерты, кафе, скидки в городе, развлечения",
        "currency_affinity": "bravo-points",
        "base_gain": 600.0,
        "reason_template": "Высокая активность в категории развлечений — Т-Город конвертирует её в ~{gain:.0f} ₽/мес.",
    },
)

_PROGRAM_RATE = {
    "rub": 0.01,
    "miles": 0.0067,
    "bravo-points": 0.01,
}


@dataclass
class CrossSellOffer:
    product_name: str
    reason: str
    potential_gain: float
    priority: int
    affinity: float = 0.0


@dataclass
class CrossSellAnalysis:
    items: list[CrossSellOffer] = field(default_factory=list)


def _segment_one_hot(segment: str) -> list[float]:
    seg = (segment or "MEDIUM").upper()
    return [1.0 if seg == s else 0.0 for s in _SEGMENTS]


class CrossSellAI:
    """Inference-only wrapper. Trains the towers once on first call, caches to disk."""

    def __init__(self, data_dir: str, weights_path: Optional[str] = None) -> None:
        self.data_dir = Path(data_dir)
        self.weights_path = Path(weights_path) if weights_path else self.data_dir.parent / ".cache" / "cross_sell.pt"
        self._data_lock = threading.Lock()
        self._models_lock = threading.Lock()
        self._data_ready = False
        self._models_ready = False
        self._users: Any = None
        self._accounts: Any = None
        self._programs: Any = None
        self._history: Any = None
        self._user_tower: Any = None
        self._product_tower: Any = None
        self._product_latents: Any = None  # tensor [num_products, latent]

    # ----- Lazy loading -----------------------------------------------------

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
            self._data_ready = True

    def _ensure_models_loaded(self) -> None:
        if self._models_ready:
            return
        self._ensure_data_loaded()
        with self._models_lock:
            if self._models_ready:
                return

            logger.info("Loading Cross-Sell AI models — first call, may take ~1 min.")
            import torch
            from sentence_transformers import SentenceTransformer

            embedder = SentenceTransformer(_SEARCH_MODEL)
            descriptions = [p["description"] for p in _PRODUCTS]
            product_emb = embedder.encode(descriptions, convert_to_tensor=True).cpu()
            # encode returns float32 tensor; ensure shape [P, D_in]

            self._user_tower, self._product_tower = self._build_or_train_towers(
                product_emb=product_emb,
                users=self._users,
                accounts=self._accounts,
                programs=self._programs,
                history=self._history,
            )

            with torch.inference_mode():
                self._product_latents = self._product_tower(product_emb)
                self._product_latents = torch.nn.functional.normalize(self._product_latents, dim=-1)

            self._models_ready = True
            logger.info("Cross-Sell AI ready: %d products indexed.", len(_PRODUCTS))

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

        merged_hist = (
            history[history["account_id"].isin(user_accounts["account_id"])]
            .merge(user_accounts[["account_id", "loyalty_program_id"]], on="account_id", how="left")
            .merge(programs[["loyalty_program_id", "cashback_currency"]], on="loyalty_program_id", how="left")
        )

        if merged_hist.empty:
            tx_per_week = 0.0
            avg_tx = 0.0
            rub_share = miles_share = bravo_share = 0.0
            total_cashback = 0.0
        else:
            span_days = max(
                1.0,
                float((merged_hist["payout_date"].max() - merged_hist["payout_date"].min()).days),
            )
            tx_per_week = len(merged_hist) / (span_days / 7.0)
            avg_tx = float(merged_hist["cashback_amount"].mean())
            cur_counts = merged_hist["cashback_currency"].value_counts(normalize=True)
            rub_share = float(cur_counts.get("rub", 0.0))
            miles_share = float(cur_counts.get("miles", 0.0))
            bravo_share = float(cur_counts.get("bravo-points", 0.0))
            total_cashback = float(merged_hist["cashback_amount"].sum())

        avg_balance = float(user_accounts["current_balance"].mean())

        return [
            *_segment_one_hot(segment),               # 3
            float(len(user_accounts)),                # 1
            avg_balance / 100_000.0,                  # 1
            tx_per_week,                              # 1
            avg_tx / 1000.0,                          # 1
            rub_share,                                # 1
            miles_share,                              # 1
            bravo_share,                              # 1
            total_cashback / 10_000.0,                # 1
            float(min(len(merged_hist), 60)) / 60.0,  # 1
        ]

    def _positive_product_idx(self, feats: list[float]) -> int:
        """Self-supervised positive label: product whose currency_affinity
        matches the user's dominant program-currency share. Tie-broken by
        segment heuristic (HIGH → Бизнес/Инвестиции; LOW → Мобайл).
        """
        rub, miles, bravo = feats[8], feats[9], feats[10]
        seg_idx = feats[:3].index(1.0) if 1.0 in feats[:3] else 1
        dominant = max([("rub", rub), ("miles", miles), ("bravo-points", bravo)], key=lambda x: x[1])[0]

        # All products with that affinity, scored by base_gain × segment fit
        candidates = [(i, p) for i, p in enumerate(_PRODUCTS) if p["currency_affinity"] == dominant]
        if not candidates:
            candidates = list(enumerate(_PRODUCTS))

        seg_pref = {
            0: ("Т-Мобайл", "Т-Город"),       # LOW
            1: ("Т-Страхование", "Т-Город"),  # MEDIUM
            2: ("Т-Бизнес", "Т-Инвестиции"),  # HIGH
        }[seg_idx]

        for i, p in candidates:
            if p["name"] in seg_pref:
                return i
        # fallback: highest base_gain among candidates
        return max(candidates, key=lambda kv: kv[1]["base_gain"])[0]

    def _build_or_train_towers(
        self,
        product_emb: "torch.Tensor",
        users: "pd.DataFrame",
        accounts: "pd.DataFrame",
        programs: "pd.DataFrame",
        history: "pd.DataFrame",
    ) -> tuple[Any, Any]:
        import torch
        from torch import nn

        torch.manual_seed(_TORCH_SEED)

        in_dim = int(product_emb.shape[1])

        class UserTower(nn.Module):
            def __init__(self) -> None:
                super().__init__()
                self.net = nn.Sequential(
                    nn.Linear(_USER_FEATURE_DIM, 64),
                    nn.ReLU(),
                    nn.Linear(64, 32),
                    nn.ReLU(),
                    nn.Linear(32, _LATENT_DIM),
                )

            def forward(self, x: "torch.Tensor") -> "torch.Tensor":
                return self.net(x)

        class ProductTower(nn.Module):
            def __init__(self) -> None:
                super().__init__()
                self.net = nn.Sequential(
                    nn.Linear(in_dim, 64),
                    nn.ReLU(),
                    nn.Linear(64, _LATENT_DIM),
                )

            def forward(self, x: "torch.Tensor") -> "torch.Tensor":
                return self.net(x)

        user_tower = UserTower()
        product_tower = ProductTower()

        if self.weights_path.exists():
            try:
                state = torch.load(self.weights_path, map_location="cpu", weights_only=True)
                user_tower.load_state_dict(state["user"])
                product_tower.load_state_dict(state["product"])
                user_tower.eval()
                product_tower.eval()
                logger.info("Loaded cached Cross-Sell tower weights from %s", self.weights_path)
                return user_tower, product_tower
            except Exception as exc:  # noqa: BLE001
                logger.warning("Cached Cross-Sell weights unusable (%s); retraining.", exc)

        feats_list: list[list[float]] = []
        pos_idx_list: list[int] = []
        for _, user in users.iterrows():
            uid = int(user["id"])
            f = self._features_for_user(uid)
            if f is None:
                continue
            feats_list.append(f)
            pos_idx_list.append(self._positive_product_idx(f))

        if len(feats_list) < 2:
            logger.warning("Not enough users to train Cross-Sell — using untrained init.")
            user_tower.eval()
            product_tower.eval()
            return user_tower, product_tower

        feats_t = torch.tensor(feats_list, dtype=torch.float32)
        pos_t = torch.tensor(pos_idx_list, dtype=torch.long)

        params = list(user_tower.parameters()) + list(product_tower.parameters())
        opt = torch.optim.Adam(params, lr=_TRAIN_LR, weight_decay=1e-4)
        loss_fn = nn.CrossEntropyLoss()

        user_tower.train()
        product_tower.train()
        for epoch in range(_TRAIN_EPOCHS):
            opt.zero_grad()
            user_lat = user_tower(feats_t)                          # [N, D]
            prod_lat = product_tower(product_emb)                   # [P, D]
            user_lat = nn.functional.normalize(user_lat, dim=-1)
            prod_lat = nn.functional.normalize(prod_lat, dim=-1)
            logits = user_lat @ prod_lat.T * 10.0                    # temperature
            loss = loss_fn(logits, pos_t)
            loss.backward()
            opt.step()
            if epoch % 200 == 0:
                logger.debug("Cross-Sell epoch %d  loss=%.4f", epoch, float(loss))

        user_tower.eval()
        product_tower.eval()
        try:
            self.weights_path.parent.mkdir(parents=True, exist_ok=True)
            torch.save({"user": user_tower.state_dict(), "product": product_tower.state_dict()}, self.weights_path)
            logger.info("Saved Cross-Sell tower weights to %s", self.weights_path)
        except OSError as exc:
            logger.warning("Could not persist Cross-Sell weights (%s); inference still works.", exc)

        return user_tower, product_tower

    # ----- Public inference -------------------------------------------------

    def analyze(self, user_id: int, segment_override: Optional[str] = None) -> Optional[CrossSellAnalysis]:
        """Returns None when the user is unknown — caller falls back to stub."""
        self._ensure_data_loaded()

        feats = self._features_for_user(user_id)
        if feats is None:
            return None

        self._ensure_models_loaded()
        import torch

        users = self._users
        user_row = users[users["id"] == user_id].iloc[0]
        segment = (segment_override or str(user_row["financial_segment"])).upper()
        seg_boost = {"LOW": 1.0, "MEDIUM": 1.2, "HIGH": 1.5}.get(segment, 1.0)

        with torch.inference_mode():
            x = torch.tensor([feats], dtype=torch.float32)
            user_lat = self._user_tower(x)
            user_lat = torch.nn.functional.normalize(user_lat, dim=-1)
            affinities = (user_lat @ self._product_latents.T).squeeze(0)  # [P]
            affinities = affinities.cpu().tolist()

        accounts = self._accounts
        user_accounts = accounts[accounts["user_id"] == user_id]
        avg_balance = float(user_accounts["current_balance"].mean()) if not user_accounts.empty else 0.0

        offers: list[CrossSellOffer] = []
        for i, product in enumerate(_PRODUCTS):
            affinity = float(affinities[i])
            # Map affinity from [-1, 1] → [0.5, 1.5] multiplier
            affinity_mult = 1.0 + max(-0.5, min(0.5, affinity / 2.0))
            base = float(product["base_gain"])
            balance_factor = 1.0 + min(2.0, avg_balance / 200_000.0) * 0.5  # bigger balance → more upside
            potential = round(base * affinity_mult * seg_boost * balance_factor, 2)
            reason = product["reason_template"].format(gain=potential, balance=avg_balance)
            offers.append(CrossSellOffer(
                product_name=str(product["name"]),
                reason=reason,
                potential_gain=potential,
                priority=0,  # set after sort
                affinity=round(affinity, 3),
            ))

        offers.sort(key=lambda o: o.potential_gain, reverse=True)
        for i, o in enumerate(offers):
            o.priority = i + 1

        return CrossSellAnalysis(items=offers)


_singleton: Optional[CrossSellAI] = None
_singleton_lock = threading.Lock()


def get_cross_sell_ai() -> CrossSellAI:
    global _singleton
    if _singleton is not None:
        return _singleton
    with _singleton_lock:
        if _singleton is None:
            data_dir = os.environ.get("AI_DATA_DIR", "/app/data")
            weights_path = os.environ.get("CROSS_SELL_WEIGHTS")
            _singleton = CrossSellAI(data_dir=data_dir, weights_path=weights_path)
    return _singleton
