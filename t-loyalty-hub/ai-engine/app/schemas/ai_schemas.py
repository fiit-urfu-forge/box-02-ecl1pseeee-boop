"""Pydantic schemas for AI Engine responses."""

from typing import Optional

from pydantic import BaseModel, Field


class ShadowPortfolioResponse(BaseModel):
    real_cashback: float = Field(description="Реальный кэшбэк, ₽")
    shadow_cashback: float = Field(description="Идеальный кэшбэк (Shadow), ₽")
    gap: float = Field(description="Упущенная выгода, ₽")
    insight: str
    health_score: int = Field(ge=0, le=100)
    is_stub: bool = True


class NudgingResponse(BaseModel):
    message: Optional[str]
    category: Optional[str]
    boost_multiplier: float = Field(ge=1.0)
    trigger_time: Optional[str]
    is_stub: bool = True


class CrossSellOfferResponse(BaseModel):
    product_name: str
    reason: str
    potential_gain: float
    priority: int


class CrossSellListResponse(BaseModel):
    items: list[CrossSellOfferResponse]
    is_stub: bool = True


class ZeroClickResponse(BaseModel):
    activated_offer: Optional[str] = None
    partner_name: Optional[str] = None
    probability: float = Field(ge=0.0, le=1.0)
    intent: Optional[str] = Field(default=None, description="COMMERCIAL | INFORMATIONAL | COMMERCIAL_NO_OFFER")
    cashback_percent: Optional[float] = None
    match_accuracy: Optional[float] = Field(default=None, ge=0.0, le=1.0)
    query: Optional[str] = None
    is_stub: bool = True
