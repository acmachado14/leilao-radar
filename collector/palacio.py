from __future__ import annotations

import logging
import re
from datetime import datetime, timedelta, timezone
from typing import Any, Iterator
from zoneinfo import ZoneInfo

import httpx

from shared.dates import parse_datetime, to_iso_date
from shared.models import PalacioLotRaw
from shared.numbers import to_float

logger = logging.getLogger("collector.palacio")

BASE_URL = "https://www.palaciodosleiloes.com.br/site"
AJAX_LIST = f"{BASE_URL}/camada_ajax/coluna_esquerda_m.php"
AJAX_DETAIL = f"{BASE_URL}/camada_ajax/lotem.php"
LOT_URL_TEMPLATE = f"{BASE_URL}/lotem.php?cl={{lot_id}}"
CATEGORY_AUTOMOVEL = "1"
PAGE_SIZE = 8
USER_AGENT = (
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
    "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)


class PalacioClient:
    def __init__(self) -> None:
        self._client = httpx.Client(
            timeout=45.0,
            follow_redirects=True,
            headers={
                "User-Agent": USER_AGENT,
                "Accept": "text/html,application/xhtml+xml,application/json",
                "Accept-Language": "pt-BR,pt;q=0.9",
                "Referer": f"{BASE_URL}/",
                "Content-Type": "application/x-www-form-urlencoded",
                "X-Requested-With": "XMLHttpRequest",
            },
        )

    def close(self) -> None:
        self._client.close()

    def __enter__(self) -> "PalacioClient":
        return self

    def __exit__(self, *args: object) -> None:
        self.close()

    @staticmethod
    def lot_url(lot_id: int) -> str:
        return LOT_URL_TEMPLATE.format(lot_id=lot_id)

    def iter_open_vehicle_lots(self) -> Iterator[PalacioLotRaw]:
        offset = 0
        total: int | None = None
        seen: set[int] = set()

        while True:
            html, page_total = self._list_page(offset)
            if total is None:
                total = page_total
                logger.info("Palácio: %s lotes Automovel abertos", total)

            cards = self._parse_list_cards(html)
            if not cards:
                break

            for card in cards:
                lot_id = card["lot_id"]
                if lot_id in seen:
                    continue
                seen.add(lot_id)
                try:
                    detail = self._fetch_detail(lot_id, card["auction_id"])
                except Exception:
                    logger.exception("Failed to fetch Palácio lot detail %s", lot_id)
                    detail = {}
                yield self._merge_card_detail(card, detail)

            offset += PAGE_SIZE
            if total is not None and offset >= total:
                break
            if len(cards) < PAGE_SIZE:
                break

    def _list_page(self, offset: int) -> tuple[str, int]:
        response = self._client.post(
            AJAX_LIST,
            data={
                "opcao": "listar_lote",
                "categoria_pesquisa": CATEGORY_AUTOMOVEL,
                "e_categoria": "1",
                "paginacao": str(offset),
                "tipo_exibicao": "1",
                "somente_pesquisa": "0",
                "total_paginas": "1",
            },
        )
        response.raise_for_status()
        html = response.content.decode("latin-1", errors="replace")
        match = re.search(r"total_lotes' value='(\d+)'", html)
        total = int(match.group(1)) if match else 0
        return html, total

    def _parse_list_cards(self, html: str) -> list[dict[str, Any]]:
        chunks = re.split(r"exibir_lote\(", html)[1:]
        cards: list[dict[str, Any]] = []
        for chunk in chunks:
            ids = re.match(r"(\d+),(\d+)\)", chunk)
            if not ids:
                continue
            lot_id = int(ids.group(1))
            auction_id = int(ids.group(2))
            title_match = re.search(r"quebraln[^']*'>.*?</i>\s*([^<]+)", chunk)
            title = (title_match.group(1).strip() if title_match else "") or None
            img_match = re.search(r"src='(imagens_lote/[^']+)'", chunk)
            cover = img_match.group(1) if img_match else None
            lance_match = re.search(r"<i class='fas fa-gavel[^']*'></i>\s*([\d.,]+)", chunk)
            if not lance_match:
                lance_match = re.search(r"h3[^>]*>.*?</i>\s*([\d.,]+)", chunk, re.S)
            lance = to_float(lance_match.group(1)) if lance_match else None
            infs = dict(
                re.findall(
                    r"class='inf small[^']*'>\s*([^<]*?)\s*<div class='float-right'>([^<]*)</div>",
                    chunk,
                )
            )
            auction_date = None
            location = None
            for key, value in infs.items():
                key_l = key.strip().lower()
                if "leil" in key_l:
                    continue
                if re.match(r"\d{2}/\d{2}/\d{2,4}$", value.strip()):
                    auction_date = value.strip()
                    location = key.strip() or location
                elif key_l and value.strip() and not key_l.startswith("visual") and "lance" not in key_l:
                    if not location:
                        location = f"{key.strip()} {value.strip()}".strip()

            # Prefer date from float-right next to city row
            for key, value in infs.items():
                if re.match(r"\d{2}/\d{2}/\d{2,4}$", value.strip()):
                    auction_date = value.strip()
                    if key.strip() and "leil" not in key.strip().lower():
                        location = key.strip()
                    break

            brand, model = split_title(title)
            cards.append(
                {
                    "lot_id": lot_id,
                    "auction_id": auction_id,
                    "lot_title": title,
                    "lot_brand": brand,
                    "lot_model": model,
                    "bid_actual": lance,
                    "auction_date": to_iso_date(auction_date),
                    "lot_location": location,
                    "cover": cover,
                }
            )
        return cards

    def _fetch_detail(self, lot_id: int, auction_id: int) -> dict[str, Any]:
        body = f"opcao=exibir_lote_m&cod_lote={lot_id}&cod_leilao={auction_id}&num_lote="
        response = self._client.post(AJAX_DETAIL, content=body)
        response.raise_for_status()
        html = response.content.decode("latin-1", errors="replace")
        return parse_detail_html(html)

    def _merge_card_detail(self, card: dict[str, Any], detail: dict[str, Any]) -> PalacioLotRaw:
        detail_title = (detail.get("lot_title") or "").strip()
        incomplete = "constru" in detail_title.lower()
        title = card.get("lot_title") if incomplete else (detail_title or card.get("lot_title"))

        if incomplete:
            brand = card.get("lot_brand")
            model = card.get("lot_model")
        else:
            brand = detail.get("lot_brand") or card.get("lot_brand")
            model = detail.get("lot_model") or card.get("lot_model")

        if not brand or not model:
            b2, m2 = split_title(title)
            brand = brand or b2
            model = model or m2

        pictures = detail.get("lot_pictures") or []
        if card.get("cover") and card["cover"] not in pictures:
            pictures = [card["cover"], *pictures]

        lance_atual = detail.get("bid_actual")
        if lance_atual is None:
            lance_atual = card.get("bid_actual")

        return PalacioLotRaw(
            lot_id=card["lot_id"],
            auction_id=card["auction_id"],
            lot_title=title,
            lot_brand=brand,
            lot_model=model,
            lot_year_manufacture=detail.get("lot_year_manufacture"),
            lot_year_model=detail.get("lot_year_model"),
            lot_fuel=detail.get("lot_fuel"),
            lot_category="Automovel",
            bid_actual=lance_atual,
            bid_initial=detail.get("bid_initial"),
            lot_sinister=detail.get("lot_sinister"),
            lot_origin=detail.get("lot_origin") or None,
            lot_location=detail.get("lot_location") or card.get("lot_location"),
            auction_date=detail.get("auction_date") or card.get("auction_date"),
            lot_pictures=pictures,
            lot_url=self.lot_url(card["lot_id"]),
        )


def split_title(title: str | None) -> tuple[str | None, str | None]:
    if not title:
        return None, None
    text = title.strip()
    if "/" in text:
        left, right = text.split("/", 1)
        return left.strip() or None, right.strip() or None
    parts = text.split(None, 1)
    if len(parts) == 1:
        return parts[0], None
    return parts[0], parts[1]


def parse_detail_html(html: str) -> dict[str, Any]:
    fields: dict[str, str] = {}
    for match in re.finditer(
        r"<t[dh][^>]*>\s*([^<]{1,80}?)\s*</t[dh]>\s*<t[dh][^>]*>\s*([^<]{0,160}?)\s*</t[dh]>",
        html,
        re.I,
    ):
        key = match.group(1).strip()
        value = match.group(2).strip()
        if key and value and key.lower() not in {"lance"}:
            fields[key] = value

    # Situação often appears as label/value in loose markup
    situacao = fields.get("Situação veículo") or fields.get("Situacao veiculo")
    if not situacao:
        situ_match = re.search(
            r"Situa(?:ção|cao)\s*ve[ií]culo\s*</[^>]+>\s*<[^>]+>\s*([^<]+)",
            html,
            re.I,
        )
        if situ_match:
            situacao = situ_match.group(1).strip()
    if not situacao:
        monta_match = re.search(
            r"(pequena monta|m[eé]dia monta|grande monta|sem sinistro)",
            html,
            re.I,
        )
        if monta_match:
            situacao = monta_match.group(1)

    title_match = re.search(r"font-weight-bold text-dark[^>]*>\s*([^<]+)", html)
    title = title_match.group(1).strip() if title_match else None

    ano_raw = fields.get("Ano") or fields.get("Ano/Modelo")
    ano_fab = ano_mod = None
    if ano_raw:
        years = re.findall(r"((?:19|20)\d{2})", ano_raw)
        if len(years) >= 2:
            ano_fab, ano_mod = int(years[0]), int(years[1])
        elif len(years) == 1:
            ano_fab = ano_mod = int(years[0])

    hiddens: dict[str, str] = {}
    for tag in re.findall(r"<input[^>]+>", html, re.I):
        name_match = re.search(r"(?:name|id)=['\"]([^'\"]+)['\"]", tag, re.I)
        val_match = re.search(r"value=['\"]([^'\"]*)['\"]", tag, re.I)
        if name_match and val_match:
            hiddens[name_match.group(1)] = val_match.group(1)

    bid_actual = to_float(hiddens.get("ultimo_valor"))
    bid_initial = to_float(hiddens.get("lance_inicial")) if hiddens.get("lance_inicial") else None

    auction_date = None
    leilao_data = fields.get("Leilão e data") or fields.get("Leilao e data")
    if leilao_data:
        date_match = re.search(r"(\d{2}/\d{2}/\d{2,4})", leilao_data)
        if date_match:
            auction_date = to_iso_date(date_match.group(1))

    pictures = list(dict.fromkeys(re.findall(r"imagens_lote/[^'\"\s>]+\.(?:webp|jpg|jpeg|png)", html, re.I)))

    location = fields.get("Lote") if fields.get("Lote") not in {None, "-", ""} else None
    if not location:
        location = fields.get("Local do leilão") or fields.get("Local do leilao")

    brand, model = split_title(title)
    return {
        "lot_title": title,
        "lot_brand": brand,
        "lot_model": model,
        "lot_year_manufacture": ano_fab,
        "lot_year_model": ano_mod,
        "lot_fuel": fields.get("Combustível") or fields.get("Combustivel"),
        "lot_sinister": situacao,
        "lot_origin": fields.get("Origem"),
        "lot_location": location,
        "auction_date": auction_date,
        "bid_actual": bid_actual,
        "bid_initial": bid_initial,
        "lot_pictures": pictures,
    }


def compute_ttl(auction_date: str | None) -> int:
    end_dt = parse_datetime(auction_date)
    if end_dt is None:
        end_dt = datetime.now(tz=ZoneInfo("America/Sao_Paulo"))
    # End of auction day + 1 day retention
    expiry = end_dt.replace(hour=23, minute=59, second=59) + timedelta(days=1)
    return int(expiry.astimezone(timezone.utc).timestamp())
