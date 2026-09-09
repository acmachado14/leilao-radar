from collector.handler import build_lot_record
from shared.models import FipeMatchResult, SodreLotRaw


class _BoomFipe:
    def match_vehicle(self, **kwargs):
        raise AssertionError("FIPE should be reused for an unchanged vehicle")


def _raw() -> SodreLotRaw:
    return SodreLotRaw(
        lot_id=2801452,
        auction_id=28998,
        lot_title="bmw - 118i ue71",
        lot_brand="bmw",
        lot_model="118i ue71",
        lot_year_model=2010,
        lot_fuel="gasolina",
        lot_category="carros",
        bid_actual="13200.00",
        auction_status="aberto",
        lot_status="andamento",
        lot_sinister="pequena monta",
        auction_date_init="2026-09-09 09:30:00",
        lot_date_end="2026-09-09 11:24:00",
    )


def test_build_lot_record_reuses_stored_fipe():
    previous = {
        "marca": "BMW",
        "modelo": "118I Ue71",
        "ano_mod": 2010,
        "fipe_match": "closest",
        "fipe_codigo": "004283-0",
        "fipe_preco": 45000,
        "fipe_texto": "BMW 118i",
    }
    lot, reused = build_lot_record(_raw(), _BoomFipe(), previous=previous)
    assert reused is True
    assert lot.lote_id == "2801452"
    assert lot.leilao_id == "28998"
    assert lot.fipe_match == "closest"
    assert lot.fipe_preco == 45000
    assert lot.lance_atual == 13200.0
    assert lot.desconto_pct == round(1 - (13200 / 45000), 4)
    assert lot.gsi_pk == "LIVE"


def test_build_lot_record_calls_fipe_when_unknown():
    class _StubFipe:
        called = False

        def match_vehicle(self, **kwargs):
            self.called = True
            return FipeMatchResult(match="failed")

    stub = _StubFipe()
    lot, reused = build_lot_record(_raw(), stub, previous=None)
    assert reused is False
    assert stub.called is True
    assert lot.fipe_match == "failed"
    assert lot.url.endswith("/leilao/28998/lote/2801452/")
