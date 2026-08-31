#!/usr/bin/env python3
"""Export DynamoDB lots to JSON for the static GitHub Pages dashboard."""

from __future__ import annotations

import json
import os
import sys
import time
from decimal import Decimal
from pathlib import Path
from typing import Any

from collector.dynamo import LotRepository
from shared.monta import monta_label, parse_monta_class
from shared.scoring import compute_relevance

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUT = ROOT / "docs" / "data" / "lotes.json"


def to_json_value(value: Any) -> Any:
    if isinstance(value, Decimal):
        return float(value)
    if isinstance(value, dict):
        return {k: to_json_value(v) for k, v in value.items()}
    if isinstance(value, list):
        return [to_json_value(v) for v in value]
    return value


def normalize_item(item: dict[str, Any]) -> dict[str, Any]:
    lance = float(item.get("lance_atual") or 0)
    fipe = item.get("fipe_preco")
    fipe_f = float(fipe) if fipe is not None else None
    desconto = item.get("desconto_pct")
    desconto_f = float(desconto) if desconto is not None else None
    relevance = item.get("relevance_score")
    days_until = item.get("days_until_auction")
    leilao_fim = item.get("leilao_fim")
    leilao_em = item.get("leilao_em")
    sinistro = item.get("sinistro")

    rel, days, monta_class, excluded = compute_relevance(
        desconto_f, leilao_fim, leilao_em, sinistro=sinistro
    )
    if relevance is None:
        relevance = rel
        days_until = days
    else:
        relevance = float(relevance)
        if days_until is not None:
            days_until = float(days_until)

    classificacao = item.get("classificacao_monta") or parse_monta_class(sinistro)
    if excluded or classificacao == "grande":
        return None

    desconto_label = (
        f"{desconto_f * 100:.1f}%"
        if desconto_f is not None and desconto_f > -900
        else "N/A"
    )

    return {
        "lote_id": item.get("lote_id"),
        "titulo": item.get("titulo"),
        "marca": item.get("marca"),
        "modelo": item.get("modelo"),
        "ano_mod": int(item["ano_mod"]) if item.get("ano_mod") is not None else None,
        "lance_atual": lance,
        "fipe_preco": fipe_f,
        "desconto_pct": desconto_f,
        "desconto_label": desconto_label,
        "relevance_score": relevance,
        "days_until_auction": days_until,
        "leilao_fim": leilao_fim,
        "leilao_em": leilao_em,
        "custo_estimado_5pct": round(lance * 1.05, 2) if lance else None,
        "fipe_match": item.get("fipe_match"),
        "sinistro": sinistro,
        "classificacao_monta": classificacao,
        "sinistro_label": monta_label(sinistro),
        "patio": item.get("patio"),
        "url": item.get("url"),
        "foto_capa": item.get("foto_capa"),
        "fotos": item.get("fotos") or [],
    }


def export_lotes(limit: int = 2000) -> dict[str, Any]:
    started = time.perf_counter()
    repo = LotRepository()
    items = repo.scan_all(limit=limit)
    if not items:
        items = repo.query_top_deals(limit=min(limit, 500))

    normalized = sorted(
        [
            row
            for item in items
            if (row := normalize_item(to_json_value(item))) is not None
        ],
        key=lambda row: row.get("relevance_score") or 0,
        reverse=True,
    )
    elapsed_ms = round((time.perf_counter() - started) * 1000, 1)
    return {
        "exported_at": time.strftime("%Y-%m-%d %H:%M:%S"),
        "region": os.environ.get("AWS_REGION", "sa-east-1"),
        "table_name": os.environ.get("TABLE_NAME", "leilao-radar-lotes"),
        "count": len(normalized),
        "elapsed_ms": elapsed_ms,
        "items": normalized,
    }


def main() -> int:
    out = Path(os.environ.get("EXPORT_JSON_PATH", DEFAULT_OUT))
    out.parent.mkdir(parents=True, exist_ok=True)
    payload = export_lotes()
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    print(f"Exported {payload['count']} lots to {out}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
