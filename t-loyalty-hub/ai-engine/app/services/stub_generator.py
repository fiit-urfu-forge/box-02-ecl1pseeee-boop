"""Deterministic per-user stub generator for AI features.

Seeded by user_id so the same user always gets the same response —
predictable demos without losing realism.
"""

import random
from dataclasses import dataclass
from typing import Optional


@dataclass
class ShadowPortfolioResult:
    real_cashback: float
    shadow_cashback: float
    gap: float
    insight: str
    health_score: int
    is_stub: bool = True


@dataclass
class NudgingResult:
    message: Optional[str]
    category: Optional[str]
    boost_multiplier: float
    trigger_time: Optional[str]
    is_stub: bool = True


@dataclass
class CrossSellOffer:
    product_name: str
    reason: str
    potential_gain: float
    priority: int


@dataclass
class ZeroClickResult:
    activated_offer: Optional[str]
    partner_name: Optional[str]
    probability: float
    is_stub: bool = True


class StubGenerator:
    CATEGORIES = ["АЗС", "Рестораны", "Супермаркеты", "Аптеки", "Кино", "Онлайн-покупки", "Транспорт"]
    PRODUCTS = ["Т-Инвестиции", "Т-Мобайл", "Т-Страхование", "Т-Бизнес", "Т-Город"]
    INSIGHTS = [
        "Ты потерял {gap:.0f} ₽, не использовав подписку и нужные категории. Сократим разрыв?",
        "Активируй категорию «{category}» — это вернёт ещё {potential:.0f} ₽ в месяц.",
        "Твой кэшбэк мог быть в {ratio:.1f}× выше. Покажем как?",
        "Ты используешь только {pct:.0f}% потенциала — давай поднимем планку.",
    ]
    TRIGGER_TIMES = ["вечером сегодня", "в обед", "в эти выходные", "после работы"]
    ACTIVATED_OFFERS = [
        ("Кэшбэк 10% в спортивных магазинах", "СпортМастер"),
        ("Кэшбэк 7% на доставку еды", "Самокат"),
        ("Кэшбэк 12% на путешествия", "Туту.ру"),
        ("Кэшбэк 8% на электронику", "DNS"),
    ]

    def _rng(self, user_id: int) -> random.Random:
        return random.Random(user_id * 7919 + 13)

    def shadow_portfolio(self, user_id: int) -> ShadowPortfolioResult:
        rng = self._rng(user_id)
        real = round(rng.uniform(800, 5000), 2)
        shadow = round(real * rng.uniform(1.3, 2.5), 2)
        gap = round(shadow - real, 2)
        score = int(min(100, (real / shadow) * 100)) if shadow > 0 else 0
        category = rng.choice(self.CATEGORIES)
        insight = rng.choice(self.INSIGHTS).format(
            gap=gap,
            category=category,
            potential=gap * 0.4,
            ratio=shadow / real if real > 0 else 1.0,
            pct=score,
        )

        return ShadowPortfolioResult(
            real_cashback=real,
            shadow_cashback=shadow,
            gap=gap,
            insight=insight,
            health_score=score,
        )

    def dynamic_nudging(self, user_id: int) -> NudgingResult:
        rng = self._rng(user_id + 1)
        if rng.random() < 0.25:
            return NudgingResult(
                message=None,
                category=None,
                boost_multiplier=1.0,
                trigger_time=None,
            )

        category = rng.choice(self.CATEGORIES)
        multiplier = round(rng.uniform(1.5, 3.0), 1)
        trigger = rng.choice(self.TRIGGER_TIMES)

        return NudgingResult(
            message=f"Обычно ты тратишь на «{category}» {trigger} — активируй и получи ×{multiplier} баллов!",
            category=category,
            boost_multiplier=multiplier,
            trigger_time=trigger,
        )

    def cross_sell_offers(self, user_id: int, segment: str) -> list[CrossSellOffer]:
        rng = self._rng(user_id + 2)
        boost = 1.5 if segment.upper() == "HIGH" else (1.2 if segment.upper() == "MEDIUM" else 1.0)
        products = rng.sample(self.PRODUCTS, k=len(self.PRODUCTS))
        offers: list[CrossSellOffer] = []
        for i, product in enumerate(products):
            gain = round(rng.uniform(200, 3000) * boost, 2)
            offers.append(
                CrossSellOffer(
                    product_name=product,
                    reason=f"Повысит твой Loyalty Health Score и добавит до {gain:.0f} ₽/мес",
                    potential_gain=gain,
                    priority=i + 1,
                )
            )

        offers.sort(key=lambda o: o.potential_gain, reverse=True)
        for i, o in enumerate(offers):
            o.priority = i + 1
        return offers

    def zero_click(self, user_id: int) -> ZeroClickResult:
        rng = self._rng(user_id + 3)
        probability = round(rng.uniform(0.4, 0.99), 2)
        if probability > 0.8:
            offer, partner = rng.choice(self.ACTIVATED_OFFERS)
            return ZeroClickResult(activated_offer=offer, partner_name=partner, probability=probability)

        return ZeroClickResult(activated_offer=None, partner_name=None, probability=probability)
