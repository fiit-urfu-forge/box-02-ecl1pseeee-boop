from fastapi import APIRouter

from app.schemas.ai_schemas import NudgingResponse
from app.services.stub_generator import StubGenerator

router = APIRouter()
_stub = StubGenerator()


@router.get("/nudging/{user_id}", response_model=NudgingResponse)
def get_nudging(user_id: int) -> NudgingResponse:
    result = _stub.dynamic_nudging(user_id)
    return NudgingResponse(**vars(result))
