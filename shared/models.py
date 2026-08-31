from __future__ import annotations

from datetime import datetime
from decimal import Decimal
from typing import Literal

from pydantic import BaseModel, Field


FipeMatchKind = Literal["exact", "closest", "failed"]


class FipeMatchResult(BaseModel):
    codigo: str | None = None
    texto: str | None = None
    preco: float | None = None
    match: FipeMatchKind = "failed"


class SodreLotRaw(BaseModel):
    lot_id: int
    auction_id: int
    lot_title: str | None = None
    lot_brand: str | None = None
    lot_model: str | None = None
    lot_year_manufacture: int | None = None
    lot_year_model: int | None = None
    lot_fuel: str | None = None
    lot_category: str | None = None
    lot_transmission: str | None = None
    bid_actual: str | float | None = None
    bid_initial: str | float | None = None
    auction_status: str | None = None
    lot_status: str | None = None
    lot_sinister: str | None = None
    lot_origin: str | None = None
    lot_location: str | None = None
    auction_date_init: str | None = None
    lot_date_end: str | None = None
    auction_name: str | None = None
    segment_slug: str | None = None
    segment_label: str | None = None


class LotRecord(BaseModel):
    lote_id: str
    titulo: str
    marca: str
    modelo: str
    ano_fab: int | None = None
    ano_mod: int | None = None
    lance_atual: float
    lance_inicial: float | None = None
    url: str
    leilao_id: str
    leilao_em: str | None = None
    leilao_fim: str | None = None
    leilao_status: str | None = None
    lot_status: str | None = None
    sinistro: str | None = None
    origem: str | None = None
    patio: str | None = None
    fipe_codigo: str | None = None
    fipe_preco: float | None = None
    fipe_texto: str | None = None
    fipe_match: FipeMatchKind = "failed"
    desconto_pct: float | None = None
    relevance_score: float = 0.0
    days_until_auction: float | None = None
    gsi_pk: str = "LIVE"
    updated_at: str = Field(default_factory=lambda: datetime.utcnow().isoformat() + "Z")
    ttl: int

    def to_dynamo_item(self) -> dict:
        item: dict = {
            "lote_id": self.lote_id,
            "titulo": self.titulo,
            "marca": self.marca,
            "modelo": self.modelo,
            "lance_atual": Decimal(str(round(self.lance_atual, 2))),
            "url": self.url,
            "leilao_id": self.leilao_id,
            "fipe_match": self.fipe_match,
            "gsi_pk": self.gsi_pk,
            "updated_at": self.updated_at,
            "ttl": self.ttl,
        }
        optional_fields = {
            "ano_fab": self.ano_fab,
            "ano_mod": self.ano_mod,
            "lance_inicial": self.lance_inicial,
            "leilao_em": self.leilao_em,
            "leilao_fim": self.leilao_fim,
            "leilao_status": self.leilao_status,
            "lot_status": self.lot_status,
            "sinistro": self.sinistro,
            "origem": self.origem,
            "patio": self.patio,
            "fipe_codigo": self.fipe_codigo,
            "fipe_preco": self.fipe_preco,
            "fipe_texto": self.fipe_texto,
            "desconto_pct": self.desconto_pct,
            "relevance_score": self.relevance_score,
            "days_until_auction": self.days_until_auction,
        }
        for key, value in optional_fields.items():
            if value is None:
                continue
            if isinstance(value, float):
                item[key] = Decimal(str(round(value, 4)))
            else:
                item[key] = value
        return item
