import logging
from dataclasses import asdict

from fastapi import APIRouter

from app.schemas.ai_schemas import CrossSellListResponse, CrossSellOfferResponse
from app.services.cross_sell_ai import get_cross_sell_ai
from app.services.stub_generator import StubGenerator

logger = logging.getLogger(__name__)

router = APIRouter()
_stub = StubGenerator()


@router.get("/cross-sell/{user_id}", response_model=CrossSellListResponse)
def get_cross_sell(user_id: int, segment: str = "MEDIUM") -> CrossSellListResponse:
    """Real ML pipeline: PyTorch two-tower recommender (user MLP + MiniLM
    product tower) trained with InfoNCE on currency-affinity positives.
    Falls back to deterministic stub when the user is not in the dataset
    or if ML inference fails.
    """
    try:
        analysis = get_cross_sell_ai().analyze(user_id, segment_override=segment)
    except FileNotFoundError:
        logger.warning("Cross-Sell dataset not mounted; using stub fallback.")
        analysis = None
    except Exception:  # noqa: BLE001 — keep service alive via stub fallback
        logger.exception("Cross-Sell AI failed; falling back to stub.")
        analysis = None

    if analysis is None:
        items = [CrossSellOfferResponse(**vars(o)) for o in _stub.cross_sell_offers(user_id, segment)]
        return CrossSellListResponse(items=items)

    return CrossSellListResponse(
        items=[CrossSellOfferResponse(**asdict(o)) for o in analysis.items],
        is_stub=False,
    )
