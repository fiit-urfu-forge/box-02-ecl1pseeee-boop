"""Smoke tests for AI Engine endpoints."""

from fastapi.testclient import TestClient

from app.main import app

client = TestClient(app)


def test_health() -> None:
    r = client.get("/health")
    assert r.status_code == 200
    assert r.json() == {"status": "ok", "mode": "hybrid"}


def test_shadow_portfolio_deterministic() -> None:
    a = client.get("/ai/shadow-portfolio/42").json()
    b = client.get("/ai/shadow-portfolio/42").json()
    assert a == b
    assert 0 <= a["health_score"] <= 100
    assert a["shadow_cashback"] >= a["real_cashback"]


def test_nudging_shape() -> None:
    r = client.get("/ai/nudging/42").json()
    assert r["boost_multiplier"] >= 1.0
    assert r["is_stub"] is True


def test_cross_sell_sorted() -> None:
    r = client.get("/ai/cross-sell/42?segment=HIGH").json()
    items = r["items"]
    assert len(items) > 0
    gains = [it["potential_gain"] for it in items]
    assert gains == sorted(gains, reverse=True)


def test_zero_click_probability_bounds() -> None:
    r = client.get("/ai/zero-click/42").json()
    assert 0.0 <= r["probability"] <= 1.0
