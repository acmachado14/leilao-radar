from __future__ import annotations

from datetime import datetime
from zoneinfo import ZoneInfo

from shared.dates import parse_datetime

DISCOUNT_CAP = 0.50
DATE_HORIZON_DAYS = 14
DISCOUNT_WEIGHT = 0.55
DATE_WEIGHT = 0.45


def discount_component(desconto_pct: float | None) -> float:
    if desconto_pct is None or desconto_pct <= -900:
        return 0.0
    return max(0.0, min(1.0, desconto_pct / DISCOUNT_CAP))


def date_urgency(days_until: float) -> float:
    if days_until <= 0:
        return 1.0
    if days_until >= DATE_HORIZON_DAYS:
        return 0.0
    return 1.0 - (days_until / DATE_HORIZON_DAYS)


def days_until_auction(
    leilao_fim: str | None,
    leilao_em: str | None,
    now: datetime | None = None,
) -> float | None:
    reference = now or datetime.now(tz=ZoneInfo("America/Sao_Paulo"))
    auction_dt = parse_datetime(leilao_fim) or parse_datetime(leilao_em)
    if auction_dt is None:
        return None
    return (auction_dt - reference).total_seconds() / 86400


def compute_relevance(
    desconto_pct: float | None,
    leilao_fim: str | None,
    leilao_em: str | None,
    now: datetime | None = None,
) -> tuple[float, float | None]:
    """Higher score = closer auction date + bigger gap below FIPE."""
    days = days_until_auction(leilao_fim, leilao_em, now)
    urgency = date_urgency(days) if days is not None else 0.25
    discount = discount_component(desconto_pct)
    score = round(DISCOUNT_WEIGHT * discount + DATE_WEIGHT * urgency, 4)
    return score, days
