import logging
from typing import Optional

from fastapi import APIRouter, HTTPException, Query

from app.schemas.ai_schemas import ZeroClickResponse
from app.services.stub_generator import StubGenerator
from app.services.zero_click_ai import get_zero_click_ai

logger = logging.getLogger(__name__)

router = APIRouter()
_stub = StubGenerator()


@router.get("/zero-click/{user_id}", response_model=ZeroClickResponse)
def get_zero_click(
    user_id: int,
    query: Optional[str] = Query(default=None, description="User search query for intent + offer matching"),
) -> ZeroClickResponse:
    """When `query` is provided, runs the real ML pipeline (intent classifier
    + semantic offer search). Without `query`, falls back to the deterministic
    stub for the existing demo flow.
    """
    if not query:
        result = _stub.zero_click(user_id)
        return ZeroClickResponse(**vars(result))

    try:
        analysis = get_zero_click_ai().analyze(query)
    except FileNotFoundError as exc:
        logger.error("Offers CSV missing: %s", exc)
        raise HTTPException(status_code=503, detail="Offers dataset not mounted") from exc
    except Exception as exc:  # noqa: BLE001 — surface model errors as 500
        logger.exception("Zero-Click AI failed")
        raise HTTPException(status_code=500, detail=f"AI inference failed: {exc}") from exc

    return ZeroClickResponse(
        activated_offer=analysis.activated_offer,
        partner_name=analysis.partner_name,
        probability=analysis.match_accuracy if analysis.match_accuracy is not None else analysis.confidence,
        intent=analysis.intent,
        cashback_percent=analysis.cashback_percent,
        match_accuracy=analysis.match_accuracy,
        query=analysis.query,
        is_stub=False,
    )
