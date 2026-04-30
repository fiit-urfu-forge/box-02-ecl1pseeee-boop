import logging
from dataclasses import asdict

from fastapi import APIRouter

from app.schemas.ai_schemas import ShadowOfferResponse, ShadowPortfolioResponse
from app.services.shadow_portfolio_ai import get_shadow_portfolio_ai
from app.services.stub_generator import StubGenerator

logger = logging.getLogger(__name__)

router = APIRouter()
_stub = StubGenerator()


@router.get("/shadow-portfolio/{user_id}", response_model=ShadowPortfolioResponse)
def get_shadow_portfolio(user_id: int) -> ShadowPortfolioResponse:
    """Real ML pipeline: PyTorch MLP for ceiling-multiplier + MiniLM offer
    ranking. Falls back to deterministic stub when the user is not in the
    dataset or if ML inference fails.
    """
    try:
        analysis = get_shadow_portfolio_ai().analyze(user_id)
    except FileNotFoundError:
        logger.warning("Shadow Portfolio dataset not mounted; using stub fallback.")
        analysis = None
    except Exception:  # noqa: BLE001 — keep service alive via stub fallback
        logger.exception("Shadow Portfolio AI failed; falling back to stub.")
        analysis = None

    if analysis is None:
        result = _stub.shadow_portfolio(user_id)
        return ShadowPortfolioResponse(**vars(result))

    return ShadowPortfolioResponse(
        real_cashback=analysis.real_cashback,
        shadow_cashback=analysis.shadow_cashback,
        gap=analysis.gap,
        insight=analysis.insight,
        health_score=analysis.health_score,
        top_offers=[ShadowOfferResponse(**asdict(o)) for o in analysis.top_offers],
        is_stub=False,
    )
