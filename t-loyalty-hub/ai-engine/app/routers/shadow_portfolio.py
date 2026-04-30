from fastapi import APIRouter

from app.schemas.ai_schemas import ShadowPortfolioResponse
from app.services.stub_generator import StubGenerator

router = APIRouter()
_stub = StubGenerator()


@router.get("/shadow-portfolio/{user_id}", response_model=ShadowPortfolioResponse)
def get_shadow_portfolio(user_id: int) -> ShadowPortfolioResponse:
    result = _stub.shadow_portfolio(user_id)
    return ShadowPortfolioResponse(**vars(result))
