from __future__ import annotations

import logging
import os
import time
from decimal import Decimal
from typing import Any

import pandas as pd
import streamlit as st
from dotenv import load_dotenv

from collector.dynamo import LotRepository, get_dynamodb_target
from shared.logging_config import setup_logging
from shared.monta import monta_label, parse_monta_class
from shared.scoring import compute_relevance

load_dotenv()
setup_logging()

logger = logging.getLogger("dashboard.app")

st.set_page_config(page_title="Leilão Radar", layout="wide")
st.title("Leilão Radar")
st.caption("Ranking por relevância: proximidade do leilão + desconto vs FIPE")


def log_startup_config() -> dict[str, str]:
    target = get_dynamodb_target()
    logger.info(
        "Dashboard iniciado | tabela=%s | endpoint=%s | region=%s",
        target["table_name"],
        target["endpoint_url"],
        target["region"],
    )
    return target


@st.cache_data(ttl=300, show_spinner="Carregando lotes do DynamoDB...")
def load_lots_with_meta(limit: int = 2000) -> tuple[list[dict[str, Any]], dict[str, Any]]:
    repo = LotRepository()
    started = time.perf_counter()
    method = "scan"
    error: str | None = None

    logger.info("Buscando lotes (limit=%d) via scan...", limit)
    try:
        items = repo.scan_all(limit=limit)
        if not items:
            method = "gsi"
            logger.info("Scan vazio — tentando GSI...")
            items = repo.query_top_deals(limit=min(limit, 500))
    except Exception as exc:
        method = "gsi"
        error = str(exc)
        logger.warning("Scan falhou (%s). Fallback para GSI.", exc)
        items = repo.query_top_deals(limit=min(limit, 500))

    elapsed_ms = round((time.perf_counter() - started) * 1000, 1)
    meta = {
        "method": method,
        "count": len(items),
        "elapsed_ms": elapsed_ms,
        "limit": limit,
        "error": error,
        "loaded_at": time.strftime("%Y-%m-%d %H:%M:%S"),
    }
    logger.info(
        "Carga concluída | %d lotes via %s em %sms",
        meta["count"],
        meta["method"],
        meta["elapsed_ms"],
    )
    if items:
        sample = items[0]
        logger.debug(
            "Amostra: lote_id=%s titulo=%s lance=%s desconto=%s",
            sample.get("lote_id"),
            sample.get("titulo"),
            sample.get("lance_atual"),
            sample.get("desconto_pct"),
        )
    return items, meta


def to_float(value: Any) -> float | None:
    if value is None:
        return None
    if isinstance(value, Decimal):
        return float(value)
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def normalize_marca_key(value: Any) -> str:
    if value is None:
        return ""
    return str(value).strip().casefold()


def normalize_rows(items: list[dict[str, Any]]) -> pd.DataFrame:
    rows = []
    for item in items:
        lance = to_float(item.get("lance_atual")) or 0.0
        fipe = to_float(item.get("fipe_preco"))
        desconto = to_float(item.get("desconto_pct"))
        relevance = to_float(item.get("relevance_score"))
        days_until = to_float(item.get("days_until_auction"))
        leilao_fim = item.get("leilao_fim")
        leilao_em = item.get("leilao_em")
        sinistro = item.get("sinistro")
        if relevance is None:
            relevance, days_until, _, _ = compute_relevance(
                desconto, leilao_fim, leilao_em, sinistro=sinistro
            )
        classificacao = item.get("classificacao_monta") or parse_monta_class(sinistro)
        custo_estimado = lance * 1.05 if lance else None
        rows.append(
            {
                "lote_id": item.get("lote_id"),
                "titulo": item.get("titulo"),
                "marca": item.get("marca"),
                "marca_key": normalize_marca_key(item.get("marca")),
                "modelo": item.get("modelo"),
                "ano_mod": item.get("ano_mod"),
                "lance_atual": lance,
                "fipe_preco": fipe,
                "desconto_pct": desconto,
                "desconto_label": f"{desconto * 100:.1f}%" if desconto is not None and desconto > -900 else "N/A",
                "relevance_score": relevance,
                "days_until_auction": days_until,
                "leilao_fim": leilao_fim,
                "custo_estimado_5pct": custo_estimado,
                "fipe_match": item.get("fipe_match"),
                "sinistro": sinistro,
                "classificacao_monta": classificacao,
                "sinistro_label": monta_label(sinistro),
                "patio": item.get("patio"),
                "leilao_em": leilao_em,
                "url": item.get("url"),
            }
        )
    df = pd.DataFrame(rows)
    if df.empty:
        return df
    return df.sort_values(by="relevance_score", ascending=False, na_position="last")


def apply_filters(
    df: pd.DataFrame,
    match_filter: list[str],
    min_desconto: float,
    marca_filter: list[str],
    monta_filter: list[str],
    exclude_grande: bool,
    limit: int,
) -> pd.DataFrame:
    if df.empty:
        return df

    mask = df["fipe_match"].isin(match_filter)
    mask &= df["desconto_pct"].fillna(-999) >= min_desconto
    if exclude_grande:
        mask &= df["classificacao_monta"] != "grande"
    if monta_filter:
        mask &= df["classificacao_monta"].isin(monta_filter)
    if marca_filter:
        selected_keys = {normalize_marca_key(marca) for marca in marca_filter}
        mask &= df["marca_key"].isin(selected_keys)

    filtered = df[mask].sort_values(by="relevance_score", ascending=False, na_position="last")
    return filtered.head(int(limit))


target = log_startup_config()

with st.sidebar:
    st.header("Status")
    st.caption(f"Tabela: `{target['table_name']}`")
    st.caption(f"Dynamo: `{target['endpoint_url']}`")
    st.caption(f"Região: `{target['region']}`")

    if st.button("Recarregar dados", type="primary"):
        load_lots_with_meta.clear()
        logger.info("Cache limpo pelo usuário — recarregando lotes")
        st.rerun()

items, load_meta = load_lots_with_meta()

with st.sidebar:
    st.caption(f"Última carga: {load_meta['loaded_at']}")
    st.caption(f"Fonte: `{load_meta['method']}` ({load_meta['elapsed_ms']} ms)")
    st.caption(f"Lotes lidos: **{load_meta['count']}**")
    if load_meta.get("error"):
        st.warning(f"GSI indisponível; usado scan. Detalhe: {load_meta['error']}")

if not items:
    logger.warning("Nenhum lote no DynamoDB — rode make local-collect")
    st.warning(
        "Nenhum lote encontrado no DynamoDB. Execute `make local-collect` "
        "ou aguarde o job diário da Lambda."
    )
    st.info(
        f"Conectado em `{target['endpoint_url']}` tabela `{target['table_name']}`. "
        "Se LocalStack está vazio, rode `make local-init` e depois `make local-collect`."
    )
    st.stop()

df = normalize_rows(items)
logger.info(
    "DataFrame pronto | linhas=%d | match exact=%d closest=%d failed=%d",
    len(df),
    int((df["fipe_match"] == "exact").sum()),
    int((df["fipe_match"] == "closest").sum()),
    int((df["fipe_match"] == "failed").sum()),
)

with st.sidebar:
    st.header("Ranking")
    st.caption(
        "Relevância = 40% desconto vs FIPE + 35% proximidade do leilão + 25% classificação "
        "(pequena/sem sinistro > média monta; grande monta excluída)."
    )

    st.header("Filtros")
    match_filter = st.multiselect(
        "Tipo de match FIPE",
        options=["exact", "closest", "failed"],
        default=["exact", "closest"],
        key="filtro_fipe_match",
    )
    min_desconto = st.slider("Desconto mínimo (%)", min_value=-50, max_value=80, value=0) / 100
    marcas = sorted(
        {marca for marca in df["marca"].dropna().astype(str).tolist() if marca.strip()},
        key=str.casefold,
    )
    marca_filter = st.multiselect(
        "Marca",
        options=marcas,
        key="filtro_marca",
        placeholder="Todas as marcas",
    )
    monta_options = ["sem_sinistro", "pequena", "media", "grande", "outro"]
    monta_labels = {
        "sem_sinistro": "sem sinistro",
        "pequena": "pequena monta",
        "media": "média monta",
        "grande": "grande monta",
        "outro": "outro",
    }
    monta_filter = st.multiselect(
        "Classificação",
        options=monta_options,
        format_func=lambda key: monta_labels[key],
        default=["sem_sinistro", "pequena", "media"],
        key="filtro_monta",
    )
    exclude_grande = st.checkbox("Excluir grande monta", value=True, key="filtro_excluir_grande")
    limit = st.number_input("Máximo de linhas", min_value=10, max_value=500, value=500, step=10)

filtered = apply_filters(
    df, match_filter, min_desconto, marca_filter, monta_filter, exclude_grande, int(limit)
)
logger.info(
    "Filtros aplicados | marcas=%s | exibindo %d de %d lotes",
    marca_filter or "todas",
    len(filtered),
    len(df),
)

col1, col2, col3, col4 = st.columns(4)
col1.metric("Lotes (filtrados)", len(filtered))
col2.metric("Match FIPE exato", int((filtered["fipe_match"] == "exact").sum()))
with_fipe = filtered.loc[filtered["desconto_pct"] > -900, "desconto_pct"]
col3.metric(
    "Desconto médio (com FIPE)",
    f"{with_fipe.mean() * 100:.1f}%" if not with_fipe.empty else "N/A",
)
col4.metric("Lance médio", f"R$ {filtered['lance_atual'].mean():,.0f}" if not filtered.empty else "N/A")

if marca_filter:
    st.caption(f"Filtro de marca ativo: {', '.join(marca_filter)}")

st.subheader("Ofertas mais relevantes")
if filtered.empty:
    st.info("Nenhum lote com os filtros atuais. Tente outra marca ou reduza o desconto mínimo.")
else:
    st.dataframe(
        filtered[
            [
                "titulo",
                "marca",
                "modelo",
                "ano_mod",
                "lance_atual",
                "fipe_preco",
                "desconto_label",
                "relevance_score",
                "sinistro_label",
                "days_until_auction",
                "leilao_fim",
                "custo_estimado_5pct",
                "fipe_match",
                "sinistro_label",
                "patio",
                "url",
            ]
        ],
        use_container_width=True,
        hide_index=True,
    )

st.subheader("Distribuição de desconto por marca")
chart_df = filtered[filtered["desconto_pct"] > -900].groupby("marca", as_index=False)["desconto_pct"].mean()
if not chart_df.empty:
    chart_df["desconto_pct"] = chart_df["desconto_pct"] * 100
    st.bar_chart(chart_df, x="marca", y="desconto_pct")
else:
    st.info("Sem dados suficientes para o gráfico com os filtros atuais.")

st.subheader("Histograma de desconto")
hist_df = filtered[filtered["desconto_pct"] > -900][["desconto_pct"]].copy()
if not hist_df.empty:
    hist_df["desconto_pct"] = hist_df["desconto_pct"] * 100
    st.bar_chart(hist_df["desconto_pct"].value_counts(bins=10).sort_index())
else:
    st.info("Sem descontos calculados para exibir histograma.")
