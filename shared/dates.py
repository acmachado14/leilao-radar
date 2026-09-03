from __future__ import annotations

from datetime import datetime
from zoneinfo import ZoneInfo


def parse_datetime(value: str | None) -> datetime | None:
    if not value:
        return None
    text = value.strip()
    for fmt in (
        "%Y-%m-%d %H:%M:%S",
        "%Y-%m-%dT%H:%M:%S",
        "%Y-%m-%d",
        "%d/%m/%Y %H:%M:%S",
        "%d/%m/%Y",
        "%d/%m/%y",
    ):
        try:
            return datetime.strptime(text, fmt).replace(tzinfo=ZoneInfo("America/Sao_Paulo"))
        except ValueError:
            continue
    return None


def parse_auction_end(value: str | None) -> datetime | None:
    """Parse auction end time; date-only values stay open until end of day (BRT)."""
    if not value:
        return None
    text = value.strip()
    date_only_formats = ("%Y-%m-%d", "%d/%m/%Y", "%d/%m/%y")
    for fmt in date_only_formats:
        try:
            dt = datetime.strptime(text, fmt).replace(tzinfo=ZoneInfo("America/Sao_Paulo"))
            return dt.replace(hour=23, minute=59, second=59)
        except ValueError:
            continue
    return parse_datetime(text)


def to_iso_date(value: str | None) -> str | None:
    """Normalize common BR/ISO date strings to YYYY-MM-DD when possible."""
    dt = parse_datetime(value)
    if dt is None:
        return value
    return dt.strftime("%Y-%m-%d")