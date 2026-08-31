from __future__ import annotations

import logging
import os
from typing import Any

from collector.dynamo import LotRepository, get_dynamodb_target
from collector.sodre import SodreClient, compute_ttl, to_float
from shared.brands import normalize_marca
from shared.fipe import FipeClient
from shared.logging_config import setup_logging
from shared.models import LotRecord, SodreLotRaw
from shared.scoring import compute_relevance

setup_logging()
logger = logging.getLogger("collector.handler")


def build_lot_record(raw: SodreLotRaw, fipe_client: FipeClient) -> LotRecord:
    lance_atual = to_float(raw.bid_actual) or 0.0
    lance_inicial = to_float(raw.bid_initial)

    fipe = fipe_client.match_vehicle(
        marca=raw.lot_brand or "",
        modelo=raw.lot_model or raw.lot_title or "",
        ano_mod=raw.lot_year_model,
        combustivel=raw.lot_fuel,
        categoria=raw.lot_category,
    )

    desconto_pct = -999.0
    if fipe.preco and fipe.preco > 0 and lance_atual > 0:
        desconto_pct = round(1 - (lance_atual / fipe.preco), 4)

    relevance_score, days_until = compute_relevance(
        desconto_pct,
        raw.lot_date_end,
        raw.auction_date_init,
    )

    titulo = raw.lot_title or f"{raw.lot_brand or ''} {raw.lot_model or ''}".strip()
    return LotRecord(
        lote_id=str(raw.lot_id),
        titulo=titulo.title(),
        marca=normalize_marca(raw),
        modelo=(raw.lot_model or "desconhecido").title(),
        ano_fab=raw.lot_year_manufacture,
        ano_mod=raw.lot_year_model,
        lance_atual=lance_atual,
        lance_inicial=lance_inicial,
        url=SodreClient.lot_url(raw.auction_id, raw.lot_id),
        leilao_id=str(raw.auction_id),
        leilao_em=raw.auction_date_init,
        leilao_fim=raw.lot_date_end,
        leilao_status=raw.auction_status,
        lot_status=raw.lot_status,
        sinistro=raw.lot_sinister,
        origem=raw.lot_origin,
        patio=raw.lot_location,
        fipe_codigo=fipe.codigo,
        fipe_preco=fipe.preco,
        fipe_texto=fipe.texto,
        fipe_match=fipe.match,
        desconto_pct=desconto_pct,
        relevance_score=relevance_score,
        days_until_auction=round(days_until, 2) if days_until is not None else None,
        ttl=compute_ttl(raw.lot_date_end, raw.auction_date_init),
    )


def run_collector() -> dict[str, Any]:
    table_name = os.environ.get("TABLE_NAME", "leilao-radar-lotes")
    target = get_dynamodb_target()
    logger.info(
        "Collector iniciado | tabela=%s | endpoint=%s | region=%s",
        target["table_name"],
        target["endpoint_url"],
        target["region"],
    )
    repo = LotRepository(table_name=table_name)
    processed = 0
    matched = 0
    closest = 0
    failed = 0

    with SodreClient() as sodre, FipeClient() as fipe:
        logger.info("Bootstrap Sodré (HTML Nuxt → Elasticsearch)...")
        sodre.bootstrap()
        logger.info("Coletando lotes abertos e gravando no DynamoDB...")
        for raw in sodre.iter_open_vehicle_lots():
            lot = build_lot_record(raw, fipe)
            repo.upsert_lot(lot)
            processed += 1
            if lot.fipe_match == "exact":
                matched += 1
            elif lot.fipe_match == "closest":
                closest += 1
            else:
                failed += 1
            fipe_label = f"R${lot.fipe_preco:,.0f}" if lot.fipe_preco else "N/A"
            desconto_label = (
                f"{lot.desconto_pct * 100:.1f}%"
                if lot.desconto_pct is not None and lot.desconto_pct > -900
                else "N/A"
            )
            days_label = (
                f"{lot.days_until_auction:.1f}d"
                if lot.days_until_auction is not None
                else "sem data"
            )
            logger.info(
                "[%d] lote %s | %s | lance R$%.0f | FIPE %s | desc %s | leilão %s | relevância %.3f",
                processed,
                lot.lote_id,
                lot.titulo[:40],
                lot.lance_atual,
                fipe_label,
                desconto_label,
                days_label,
                lot.relevance_score,
            )

    summary = {
        "processed": processed,
        "fipe_exact": matched,
        "fipe_closest": closest,
        "fipe_failed": failed,
        "table_name": table_name,
    }
    logger.info("Collector finished: %s", summary)
    return summary


def handler(event: dict[str, Any] | None, context: Any) -> dict[str, Any]:
    summary = run_collector()
    return {"statusCode": 200, "body": summary}


if __name__ == "__main__":
    summary = run_collector()
    print(summary)
