from datetime import datetime
from zoneinfo import ZoneInfo

from shared.monta import parse_monta_class
from shared.scoring import compute_relevance

BRT = ZoneInfo("America/Sao_Paulo")


def test_pequena_and_media_bmw_lots_are_not_excluded():
    morning = datetime(2026, 9, 9, 8, 0, tzinfo=BRT)
    cases = [
        ("pequena monta", "2026-09-09 11:24:00"),
        ("média monta", "2026-09-09 11:35:15"),
    ]
    for sinistro, end in cases:
        _score, _days, monta_class, excluded = compute_relevance(
            0.70,
            end,
            "2026-09-09 09:30:00",
            sinistro=sinistro,
            now=morning,
        )
        assert excluded is False
        assert parse_monta_class(sinistro) != "grande"
        assert monta_class != "grande"
