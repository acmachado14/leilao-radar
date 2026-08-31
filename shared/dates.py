from __future__ import annotations

from datetime import datetime
from zoneinfo import ZoneInfo


def parse_datetime(value: str | None) -> datetime | None:
    if not value:
        return None
    for fmt in ("%Y-%m-%d %H:%M:%S", "%Y-%m-%dT%H:%M:%S", "%Y-%m-%d"):
        try:
            return datetime.strptime(value, fmt).replace(tzinfo=ZoneInfo("America/Sao_Paulo"))
        except ValueError:
            continue
    return None
