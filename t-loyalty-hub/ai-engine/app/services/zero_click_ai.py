"""Zero-Click AI: real implementation using HuggingFace transformers.

Two pretrained models:
- mDeBERTa zero-shot classifier — splits queries into commercial vs informational.
- multilingual MiniLM bi-encoder — semantic search over Offers.csv to pick the
  best-matching partner for a commercial query.

Models are loaded lazily on first request so FastAPI startup (and the Docker
healthcheck) is not blocked by ~400 MB of weights.
"""

from __future__ import annotations

import logging
import os
import threading
from dataclasses import dataclass
from typing import TYPE_CHECKING, Any, Optional

if TYPE_CHECKING:
    import pandas as pd

logger = logging.getLogger(__name__)


@dataclass
class ZeroClickAnalysis:
    intent: str  # COMMERCIAL | INFORMATIONAL | COMMERCIAL_NO_OFFER
    confidence: float
    activated_offer: Optional[str] = None
    partner_name: Optional[str] = None
    cashback_percent: Optional[float] = None
    match_accuracy: Optional[float] = None
    query: Optional[str] = None


_INTENT_LABELS = ["покупка, заказ, сервис", "инфо, рецепт, вакансия, обучение, дизайн, фото, идея"]
_INFO_TRIGGERS = ("как ", "почему ", "вакансии ", "история ", "рейтинг ", "дизайн ", "фото ")
_INTENT_THRESHOLD = 0.75
_OFFER_MATCH_THRESHOLD = 0.4

_INTENT_MODEL = "MoritzLaurer/mDeBERTa-v3-base-mnli-xnli"
_SEARCH_MODEL = "paraphrase-multilingual-MiniLM-L12-v2"


class ZeroClickAI:
    """Inference-only wrapper. Models are loaded once on first call."""

    def __init__(self, offers_csv_path: str) -> None:
        self.offers_csv_path = offers_csv_path
        self._lock = threading.Lock()
        self._ready = False
        self._df: Any = None
        self._intent_model: Any = None
        self._search_model: Any = None
        self._offer_embeddings: Any = None

    def _ensure_loaded(self) -> None:
        if self._ready:
            return
        with self._lock:
            if self._ready:
                return

            logger.info("Loading Zero-Click AI models — this may take a minute on first run.")
            import pandas as pd
            from sentence_transformers import SentenceTransformer
            from transformers import pipeline

            df = pd.read_csv(self.offers_csv_path)
            df["full_info"] = df["partner_name"].astype(str) + " " + df["short_description"].astype(str)

            search_model = SentenceTransformer(_SEARCH_MODEL)
            offer_embeddings = search_model.encode(df["full_info"].tolist(), convert_to_tensor=True)
            intent_model = pipeline("zero-shot-classification", model=_INTENT_MODEL)

            self._df = df
            self._search_model = search_model
            self._offer_embeddings = offer_embeddings
            self._intent_model = intent_model
            self._ready = True
            logger.info("Zero-Click AI ready: %d offers indexed.", len(df))

    def analyze(self, user_query: str) -> ZeroClickAnalysis:
        self._ensure_loaded()

        from sentence_transformers import util

        intent_res = self._intent_model(  # type: ignore[misc]
            user_query,
            _INTENT_LABELS,
            hypothesis_template="Этот запрос про {}.",
        )
        top_label = intent_res["labels"][0]
        score = float(intent_res["scores"][0])

        is_commercial = top_label == _INTENT_LABELS[0] and score > _INTENT_THRESHOLD
        if any(trigger in user_query.lower() for trigger in _INFO_TRIGGERS):
            is_commercial = False

        if not is_commercial:
            return ZeroClickAnalysis(
                intent="INFORMATIONAL",
                confidence=round(score, 2),
                query=user_query,
            )

        query_emb = self._search_model.encode(user_query, convert_to_tensor=True)  # type: ignore[union-attr]
        hits = util.semantic_search(query_emb, self._offer_embeddings, top_k=1)[0]
        best_hit = hits[0]
        match_score = float(best_hit["score"])

        if match_score < _OFFER_MATCH_THRESHOLD:
            return ZeroClickAnalysis(
                intent="COMMERCIAL_NO_OFFER",
                confidence=round(score, 2),
                match_accuracy=round(match_score, 2),
                query=user_query,
            )

        partner = self._df.iloc[best_hit["corpus_id"]]  # type: ignore[union-attr]
        return ZeroClickAnalysis(
            intent="COMMERCIAL",
            confidence=round(score, 2),
            activated_offer=f"Кэшбэк {partner['cashback_percent']}% — {partner['partner_name']}",
            partner_name=str(partner["partner_name"]),
            cashback_percent=float(partner["cashback_percent"]),
            match_accuracy=round(match_score, 2),
            query=user_query,
        )


_singleton: Optional[ZeroClickAI] = None
_singleton_lock = threading.Lock()


def get_zero_click_ai() -> ZeroClickAI:
    global _singleton
    if _singleton is not None:
        return _singleton
    with _singleton_lock:
        if _singleton is None:
            csv_path = os.environ.get("OFFERS_CSV_PATH", "/app/data/Offers.csv")
            _singleton = ZeroClickAI(offers_csv_path=csv_path)
    return _singleton
