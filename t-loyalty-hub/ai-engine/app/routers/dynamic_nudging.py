import logging

from fastapi import APIRouter

from app.schemas.ai_schemas import NudgingResponse
from app.services.dynamic_nudging_ai import get_dynamic_nudging_ai
from app.services.stub_generator import StubGenerator

logger = logging.getLogger(__name__)

router = APIRouter()
_stub = StubGenerator()


@router.get("/nudging/{user_id}", response_model=NudgingResponse)
def get_nudging(user_id: int) -> NudgingResponse:
    """Real ML pipeline: zero-shot mDeBERTa for offer category tagging +
    PyTorch MLP for next-(category, timeslot) prediction. Falls back to
    deterministic stub when the user is not in the dataset, has too little
    history, or if ML inference fails.
    """
    try:
        analysis = get_dynamic_nudging_ai().analyze(user_id)
    except FileNotFoundError:
        logger.warning("Dynamic Nudging dataset not mounted; using stub fallback.")
        analysis = None
    except Exception:  # noqa: BLE001 — keep service alive via stub fallback
        logger.exception("Dynamic Nudging AI failed; falling back to stub.")
        analysis = None

    if analysis is None:
        result = _stub.dynamic_nudging(user_id)
        return NudgingResponse(**vars(result))

    return NudgingResponse(
        message=analysis.message,
        category=analysis.category,
        boost_multiplier=analysis.boost_multiplier,
        trigger_time=analysis.trigger_time,
        partner_name=analysis.partner_name,
        cashback_percent=analysis.cashback_percent,
        confidence=analysis.confidence,
        is_stub=False,
    )
