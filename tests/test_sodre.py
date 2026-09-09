from collector.sodre import SodreClient


def test_search_payload_targets_open_running_lots():
    payload = SodreClient._build_search_payload(100, None)
    must = payload["query"]["bool"]["must"]
    assert {"term": {"auction_status": "aberto"}} in must
    assert {"term": {"lot_status": "andamento"}} in must
    assert payload["sort"] == [{"lot_id": "asc"}]
    assert payload["track_total_hits"] is True


def test_search_after_is_added_on_later_pages():
    payload = SodreClient._build_search_payload(100, [2801391])
    assert payload["search_after"] == [2801391]
