from __future__ import annotations

import logging
import os
from typing import Any

from collector.dynamo import LotRepository, get_dynamodb_target
from collector.palacio import PalacioClient, compute_ttl
from shared.brands import normalize_marca
from shared.fipe import FipeClient
from shared.logging_config import setup_logging
from shared.models import LotRecord, PalacioLotRaw
from shared.photos import PALACIO_HOST, normalize_lot_pictures
from shared.scoring import compute_relevance

setup_logging()
logger = logging.getLogger("collector.palacio_handler")


def build_lot_record(raw: PalacioLotRaw, fipe_client: FipeClient) -> LotRecord:
    lance_atual = float(raw.bid_actual or 0.0)
    lance_inicial = float(raw.bid_initial) if raw.bid_initial is not None else None
    titulo = raw.lot_title or f"{raw.lot_brand or ''} {raw.lot_model or ''}".strip()
    marca = normalize_marca(marca=raw.lot_brand, titulo=titulo)
    modelo = (raw.lot_model or "desconhecido").strip()

    fipe = fipe_client.match_vehicle(
        marca=marca,
        modelo=modelo or titulo,
        ano_mod=raw.lot_year_model,
        combustivel=raw.lot_fuel,
        categoria=raw.lot_category,
    )

    desconto_pct = -999.0
    if fipe.preco and fipe.preco > 0 and lance_atual > 0:
        desconto_pct = round(1 - (lance_atual / fipe.preco), 4)

    relevance_score, days_until, monta_class, excluded = compute_relevance(
        desconto_pct,
        raw.auction_date,
        raw.auction_date,
        sinistro=raw.lot_sinister,
    )

    foto_capa, fotos = normalize_lot_pictures(raw.lot_pictures, relative_base=PALACIO_HOST)
    return LotRecord(
        lote_id=f"palacio:{raw.lot_id}",
        titulo=titulo.title() if titulo else "Sem título",
        marca=marca,
        modelo=modelo.title(),
        ano_fab=raw.lot_year_manufacture,
        ano_mod=raw.lot_year_model,
        lance_atual=lance_atual,
        lance_inicial=lance_inicial,
        url=raw.lot_url or PalacioClient.lot_url(raw.lot_id),
        leilao_id=str(raw.auction_id),
        leilao_em=raw.auction_date,
        leilao_fim=raw.auction_date,
        leilao_status="aberto",
        lot_status="andamento",
        sinistro=raw.lot_sinister,
        classificacao_monta=monta_class,
        origem=raw.lot_origin,
        patio=raw.lot_location,
        fonte="palacio",
        fipe_codigo=fipe.codigo,
        fipe_preco=fipe.preco,
        fipe_texto=fipe.texto,
        fipe_match=fipe.match,
        desconto_pct=desconto_pct,
        relevance_score=relevance_score,
        days_until_auction=round(days_until, 2) if days_until is not None else None,
        foto_capa=foto_capa,
        fotos=fotos,
        gsi_pk="EXCLUDED" if excluded else "LIVE",
        ttl=compute_ttl(raw.auction_date),
    )


def run_collector() -> dict[str, Any]:
    table_name = os.environ.get("TABLE_NAME", "leilao-radar-lotes")
    target = get_dynamodb_target()
    logger.info(
        "Collector Palácio iniciado | tabela=%s | endpoint=%s | region=%s",
        target["table_name"],
        target["endpoint_url"],
        target["region"],
    )
    repo = LotRepository(table_name=table_name)
    processed = 0
    matched = 0
    closest = 0
    failed = 0

    with PalacioClient() as palacio, FipeClient() as fipe:
        logger.info("Coletando Automovel no Palácio dos Leilões...")
        for raw in palacio.iter_open_vehicle_lots():
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
        "fonte": "palacio",
        "processed": processed,
        "fipe_exact": matched,
        "fipe_closest": closest,
        "fipe_failed": failed,
        "table_name": table_name,
    }
    logger.info("Collector Palácio finished: %s", summary)
    return summary


def handler(event: dict[str, Any] | None, context: Any) -> dict[str, Any]:
    summary = run_collector()
    return {"statusCode": 200, "body": summary}


if __name__ == "__main__":
    summary = run_collector()
    print(summary)
