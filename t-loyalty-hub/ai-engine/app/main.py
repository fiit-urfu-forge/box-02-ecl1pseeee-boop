"""T-Loyalty Hub — AI Engine (FastAPI).

Hybrid mode: stubs for shadow-portfolio / nudging / cross-sell, real
HuggingFace inference for /ai/zero-click when a `query` is supplied.
"""

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.routers import cross_sell, dynamic_nudging, shadow_portfolio, zero_click

app = FastAPI(
    title="T-Loyalty AI Engine",
    description="AI microservice for T-Loyalty Hub. Mode: HYBRID (stub + ML zero-click)",
    version="0.2.0",
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["GET"],
    allow_headers=["*"],
)

app.include_router(shadow_portfolio.router, prefix="/ai", tags=["Shadow Portfolio"])
app.include_router(dynamic_nudging.router, prefix="/ai", tags=["Dynamic Nudging"])
app.include_router(cross_sell.router, prefix="/ai", tags=["Cross-Sell"])
app.include_router(zero_click.router, prefix="/ai", tags=["Zero-Click"])


@app.get("/health")
def health_check() -> dict:
    return {"status": "ok", "mode": "hybrid"}
