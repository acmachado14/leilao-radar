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


def to_iso_date(value: str | None) -> str | None:
    """Normalize common BR/ISO date strings to YYYY-MM-DD when possible."""
    dt = parse_datetime(value)
    if dt is None:
        return value
    return dt.strftime("%Y-%m-%d")