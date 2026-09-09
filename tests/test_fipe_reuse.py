from decimal import Decimal

from shared.fipe import stored_fipe_match


def _previous(**overrides):
    item = {
        "marca": "BMW",
        "modelo": "118I Ue71",
        "ano_mod": Decimal("2010"),
        "fipe_match": "exact",
        "fipe_codigo": "004283-0",
        "fipe_preco": Decimal("45231"),
        "fipe_texto": "BMW 118i 2010",
    }
    item.update(overrides)
    return item


def test_reuses_exact_match_when_vehicle_is_unchanged():
    result = stored_fipe_match(
        _previous(),
        marca="BMW",
        modelo="118i ue71",
        ano_mod=2010,
    )
    assert result is not None
    assert result.match == "exact"
    assert result.codigo == "004283-0"
    assert result.preco == 45231.0


def test_does_not_reuse_failed_match():
    result = stored_fipe_match(
        _previous(fipe_match="failed", fipe_codigo=None, fipe_preco=None),
        marca="BMW",
        modelo="118i ue71",
        ano_mod=2010,
    )
    assert result is None


def test_does_not_reuse_when_model_year_changes():
    result = stored_fipe_match(
        _previous(),
        marca="BMW",
        modelo="118i ue71",
        ano_mod=2011,
    )
    assert result is None


def test_returns_none_without_previous_item():
    assert stored_fipe_match(None, marca="BMW", modelo="118i", ano_mod=2010) is None
