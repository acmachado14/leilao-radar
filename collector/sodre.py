from __future__ import annotations

import re
from datetime import datetime, timedelta, timezone
from typing import Any, Iterator
from zoneinfo import ZoneInfo

import httpx

from shared.dates import parse_datetime
from shared.models import SodreLotRaw

BOOTSTRAP_URL = "https://leilao.sodresantoro.com.br/"
LOT_URL_TEMPLATE = "https://leilao.sodresantoro.com.br/leilao/{auction_id}/lote/{lot_id}/"
USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)


class SodreClient:
    def __init__(self) -> None:
        self._client = httpx.Client(
            timeout=30.0,
            follow_redirects=True,
            headers={
                "User-Agent": USER_AGENT,
                "Accept": "text/html,application/xhtml+xml,application/json",
                "Accept-Language": "pt-BR,pt;q=0.9",
            },
        )
        self._elastic_url: str | None = None
        self._elastic_api_key: str | None = None

    def close(self) -> None:
        self._client.close()

    def __enter__(self) -> "SodreClient":
        return self

    def __exit__(self, *args: object) -> None:
        self.close()

    def bootstrap(self) -> None:
        response = self._client.get(BOOTSTRAP_URL)
        response.raise_for_status()
        html = response.text
        config = self._extract_public_config(html)
        self._elastic_url = config.get("elasticURL", "").rstrip("/")
        self._elastic_api_key = config.get("elasticApiKey")
        if not self._elastic_url or not self._elastic_api_key:
            raise RuntimeError("Could not extract Elasticsearch credentials from Sodré bootstrap HTML")

    @staticmethod
    def _extract_public_config(html: str) -> dict[str, str]:
        match = re.search(r"window\.__NUXT__\.config=\{public:(\{.*?\}),app:", html, re.S)
        if not match:
            raise RuntimeError("NUXT public config not found in bootstrap HTML")
        blob = match.group(1)
        return dict(re.findall(r'(\w+):"(.*?)"', blob))

    def iter_open_vehicle_lots(self, page_size: int = 100) -> Iterator[SodreLotRaw]:
        if not self._elastic_url or not self._elastic_api_key:
            self.bootstrap()

        search_after: list[Any] | None = None
        while True:
            payload = self._build_search_payload(page_size, search_after)
            response = self._client.post(
                f"{self._elastic_url}/veiculos/_search",
                json=payload,
                headers={
                    "Authorization": f"ApiKey {self._elastic_api_key}",
                    "Content-Type": "application/json",
                },
            )
            response.raise_for_status()
            data = response.json()
            hits = data.get("hits", {}).get("hits", [])
            if not hits:
                break

            for hit in hits:
                source = hit.get("_source", {})
                yield SodreLotRaw.model_validate(source)

            search_after = hits[-1].get("sort")
            if len(hits) < page_size:
                break

    @staticmethod
    def _build_search_payload(page_size: int, search_after: list[Any] | None) -> dict[str, Any]:
        payload: dict[str, Any] = {
            "size": page_size,
            "sort": [{"lot_id": "asc"}],
            "query": {
                "bool": {
                    "must": [
                        {"term": {"auction_status": "aberto"}},
                        {"term": {"lot_status": "andamento"}},
                    ]
                }
            },
        }
        if search_after:
            payload["search_after"] = search_after
        return payload

    @staticmethod
    def lot_url(auction_id: int, lot_id: int) -> str:
        return LOT_URL_TEMPLATE.format(auction_id=auction_id, lot_id=lot_id)


def compute_ttl(lot_date_end: str | None, auction_date_init: str | None) -> int:
    end_dt = parse_datetime(lot_date_end) or parse_datetime(auction_date_init)
    if end_dt is None:
        end_dt = datetime.now(tz=ZoneInfo("America/Sao_Paulo"))
    expiry = end_dt + timedelta(days=1)
    return int(expiry.astimezone(timezone.utc).timestamp())


def to_float(value: Any) -> float | None:
    if value is None:
        return None
    if isinstance(value, (int, float)):
        return float(value)
    text = str(value).strip()
    if not text:
        return None
    if "," in text:
        text = text.replace(".", "").replace(",", ".")
    try:
        return float(text)
    except ValueError:
        return None
