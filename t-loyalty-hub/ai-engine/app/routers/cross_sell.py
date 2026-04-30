from fastapi import APIRouter

from app.schemas.ai_schemas import CrossSellListResponse, CrossSellOfferResponse
from app.services.stub_generator import StubGenerator

router = APIRouter()
_stub = StubGenerator()


@router.get("/cross-sell/{user_id}", response_model=CrossSellListResponse)
def get_cross_sell(user_id: int, segment: str = "MEDIUM") -> CrossSellListResponse:
    items = [CrossSellOfferResponse(**vars(o)) for o in _stub.cross_sell_offers(user_id, segment)]
    return CrossSellListResponse(items=items)
