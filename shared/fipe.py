from __future__ import annotations

import re
import time
import unicodedata
from typing import Any, Literal
from urllib.parse import quote

import httpx

from shared.models import FipeMatchResult


TABELAFIPE_BASE = "https://api.tabelafipe.info/api/v1"
PARALLELUM_BASE = "https://parallelum.com.br/fipe/api/v1"

FipeTipo = Literal["carros", "motos", "caminhoes"]

# tabelafipe.info score is 0..1; below this we reject the match
MIN_TABELAFIPE_SCORE = 0.45
MIN_PARALLELUM_SCORE = 55

CATEGORY_TO_TIPO: dict[str, FipeTipo] = {
    "carros": "carros",
    "automovel": "carros",
    "automóveis": "carros",
    "motos": "motos",
    "caminhões": "caminhoes",
    "caminhoes": "caminhoes",
    "utilitarios leves": "carros",
    "utilitários leves": "carros",
    "implementos rod.": "caminhoes",
    "implementos agrícolas": "caminhoes",
}

MODEL_SYNONYMS: list[tuple[re.Pattern[str], str]] = [
    (re.compile(r"\bspb\b", re.I), "sportback"),
    (re.compile(r"\bsed\b", re.I), "sedan"),
    (re.compile(r"\bhb\b", re.I), "hatch"),
    (re.compile(r"\bfsl\b", re.I), "freestyle"),
    (re.compile(r"\bfreest\b", re.I), "freestyle"),
    (re.compile(r"\bmt\b", re.I), "mec"),
    (re.compile(r"\bat\b", re.I), "aut"),
    (re.compile(r"\bseg\b", re.I), "se-g"),
    (re.compile(r"\bxei\b", re.I), "xei"),
    (re.compile(r"\bgli\b", re.I), "gli"),
    (re.compile(r"\bcdi\b", re.I), "cdi"),
    # Sodré "LM" = linha média; drop as noise (hurts scoring)
    (re.compile(r"\blm\b", re.I), " "),
]


def _normalize(text: str) -> str:
    text = unicodedata.normalize("NFKD", text)
    text = "".join(ch for ch in text if not unicodedata.combining(ch))
    return re.sub(r"\s+", " ", text.strip().lower())


def detect_fipe_tipo(category: str | None, marca: str = "", modelo: str = "") -> FipeTipo:
    cat = _normalize(category or "")
    blob = _normalize(f"{marca} {modelo}")
    if re.search(r"\b(cg|biz|bros|xre|fator|twister|ninja|r1|r3|mt[- ]?0?7)\b", blob):
        return "motos"
    if re.search(
        r"\b(cargo|constellation|daily|atego|accelo|actros|axor|stralis|atron|"
        r"fh\b|fm\b|\d{2}[.\-]\d{3}|truck|toco|bitruck|cavalo)\b",
        blob,
    ):
        return "caminhoes"
    if cat in CATEGORY_TO_TIPO:
        return CATEGORY_TO_TIPO[cat]
    return "carros"


def expand_model_query(modelo: str) -> str:
    """Turn auction shorthand into tokens the FIPE APIs understand better."""
    text = _normalize(modelo)
    # Keep compact model codes: a3, x1, q5, r3, m3
    text = re.sub(r"\b([a-z])(\d)\b", r"\1\2", text)
    # Insert spaces for glued tokens: fsl1.6flex → fsl 1.6 flex
    text = re.sub(r"([a-z]{2,})(\d)", r"\1 \2", text)
    text = re.sub(r"(\d)([a-z]{2,})", r"\1 \2", text)
    text = re.sub(r"([a-z]{2,})(flex|diesel|gasolina|aut|mec|mt|at)\b", r"\1 \2", text)
    text = re.sub(r"\s+", " ", text).strip()
    # Engine size without decimal: "18 flex" / "16" → 1.8 / 1.6 (not "16v")
    text = re.sub(
        r"\b([1-2])(\d)\b(?!\s*v\b)(?=\s*(?:flex|diesel|gasolina|aut|mec|$))",
        r"\1.\2",
        text,
    )
    for pattern, replacement in MODEL_SYNONYMS:
        text = pattern.sub(replacement, text)
    # Common Land Rover / SUV shorthand
    text = re.sub(r"\bdisc\b", "discovery", text)
    text = re.sub(r"\bspt\b", "sport", text)
    text = re.sub(r"\br\.?\s*r\b", "range rover", text)
    text = re.sub(r"\brr\b", "range rover", text)
    # Horsepower noise hurts tabelafipe scoring (150 cv → wrong cabriolet)
    text = re.sub(r"\b\d{2,4}\s*cv\b", " ", text)
    return re.sub(r"\s+", " ", text).strip()


def model_query_variants(modelo: str) -> list[str]:
    """Progressively simpler queries when the full string scores poorly."""
    primary = expand_model_query(modelo)
    variants = [primary]
    stripped = re.sub(r"\b(flex|diesel|gasolina|aut|mec|tiptronic|s-?tronic)\b", " ", primary)
    stripped = re.sub(r"\s+", " ", stripped).strip()
    if stripped and stripped not in variants:
        variants.append(stripped)
    tokens = stripped.split()
    engine = next((t for t in tokens if re.fullmatch(r"\d+\.\d+", t)), None)
    family = [t for t in tokens if not re.fullmatch(r"\d+(\.\d+)?", t)][:2]
    if family:
        core = " ".join(family + ([engine] if engine else []))
        if core and core not in variants:
            variants.append(core)
    return variants


def _tokenize(text: str) -> set[str]:
    return {t for t in re.split(r"[^a-z0-9.]+", _normalize(text)) if t and t not in {"e", "de", "da", "do"}}


class FipeClient:
    def __init__(self, min_interval_seconds: float = 1.0) -> None:
        self._client = httpx.Client(timeout=30.0)
        self._min_interval = min_interval_seconds
        self._last_request_at = 0.0
        self._cache: dict[tuple[str, str, str, int | None, str], FipeMatchResult] = {}
        self._parallelum_brands: dict[FipeTipo, list[dict[str, Any]]] = {}

    def close(self) -> None:
        self._client.close()

    def __enter__(self) -> "FipeClient":
        return self

    def __exit__(self, *args: object) -> None:
        self.close()

    def _throttle(self) -> None:
        elapsed = time.monotonic() - self._last_request_at
        if elapsed < self._min_interval:
            time.sleep(self._min_interval - elapsed)
        self._last_request_at = time.monotonic()

    def _get_json(self, url: str) -> Any:
        self._throttle()
        response = self._client.get(url, headers={"Accept": "application/json"})
        response.raise_for_status()
        return response.json()

    def match_vehicle(
        self,
        marca: str,
        modelo: str,
        ano_mod: int | None,
        combustivel: str | None = None,
        categoria: str | None = None,
    ) -> FipeMatchResult:
        tipo = detect_fipe_tipo(categoria, marca, modelo)
        variants = model_query_variants(modelo)
        cache_key = (
            _normalize(marca),
            variants[0] if variants else "",
            _normalize(combustivel or ""),
            ano_mod,
            tipo,
        )
        if cache_key in self._cache:
            return self._cache[cache_key]

        result = FipeMatchResult(match="failed")
        for modelo_q in variants:
            result = self._match_tabelafipe(tipo, marca, modelo_q, ano_mod)
            if result.match != "failed":
                break

        if result.match == "failed":
            result = self._match_parallelum(
                tipo, marca, variants[0] if variants else modelo, ano_mod, combustivel
            )

        self._cache[cache_key] = result
        return result

    def _match_tabelafipe(
        self,
        tipo: FipeTipo,
        marca: str,
        modelo: str,
        ano_mod: int | None,
    ) -> FipeMatchResult:
        try:
            query = (
                f"{TABELAFIPE_BASE}/match?"
                f"tipo={tipo}&marca={quote(marca)}&modelo={quote(modelo)}"
            )
            if ano_mod:
                query += f"&ano={ano_mod}"
            payload = self._get_json(query)
            return self._parse_tabelafipe_payload(payload)
        except Exception:
            return FipeMatchResult(match="failed")

    def _parse_tabelafipe_payload(self, payload: Any) -> FipeMatchResult:
        if not isinstance(payload, dict):
            return FipeMatchResult(match="failed")

        # New API shape: { melhor: {...}, candidatos: [...] }
        melhor = payload.get("melhor")
        if isinstance(melhor, dict) and melhor.get("codigo_fipe"):
            score = float(melhor.get("score") or 0)
            if score < MIN_TABELAFIPE_SCORE:
                return FipeMatchResult(match="failed")
            preco = self._parse_price(melhor.get("valor"))
            if preco is None and melhor.get("valor_centavos") is not None:
                try:
                    preco = float(melhor["valor_centavos"]) / 100.0
                except (TypeError, ValueError):
                    preco = None
            if not preco:
                return FipeMatchResult(match="failed")
            match_kind = "exact" if score >= 0.85 else "closest"
            return FipeMatchResult(
                codigo=str(melhor["codigo_fipe"]),
                texto=str(melhor.get("modelo") or ""),
                preco=preco,
                match=match_kind,
            )

        # Legacy flat shape fallback
        codigo = payload.get("codigoFipe") or payload.get("codigo") or payload.get("fipe")
        texto = payload.get("modelo") or payload.get("texto") or payload.get("descricao")
        preco = self._parse_price(payload.get("preco") or payload.get("valor"))
        if not codigo or not preco:
            return FipeMatchResult(match="failed")
        return FipeMatchResult(
            codigo=str(codigo),
            texto=str(texto) if texto else None,
            preco=preco,
            match="closest",
        )

    def _match_parallelum(
        self,
        tipo: FipeTipo,
        marca: str,
        modelo: str,
        ano_mod: int | None,
        combustivel: str | None,
    ) -> FipeMatchResult:
        try:
            marcas = self._parallelum_brands.get(tipo)
            if marcas is None:
                marcas = self._get_json(f"{PARALLELUM_BASE}/{tipo}/marcas")
                self._parallelum_brands[tipo] = marcas

            marca_entry, marca_score = self._find_best_label(marcas, marca, min_score=40)
            if not marca_entry:
                return FipeMatchResult(match="failed")

            marca_code = marca_entry["codigo"]
            modelos_payload = self._get_json(f"{PARALLELUM_BASE}/{tipo}/marcas/{marca_code}/modelos")
            modelo_list = modelos_payload.get("modelos", modelos_payload)
            modelo_entry, modelo_score = self._find_best_label(
                modelo_list, modelo, min_score=MIN_PARALLELUM_SCORE
            )
            if not modelo_entry:
                return FipeMatchResult(match="failed")

            modelo_code = modelo_entry["codigo"]
            anos = self._get_json(
                f"{PARALLELUM_BASE}/{tipo}/marcas/{marca_code}/modelos/{modelo_code}/anos"
            )
            ano_entry = self._pick_year(anos, ano_mod, combustivel)
            if not ano_entry:
                return FipeMatchResult(match="failed")

            preco_payload = self._get_json(
                f"{PARALLELUM_BASE}/{tipo}/marcas/{marca_code}/modelos/{modelo_code}/anos/{ano_entry['codigo']}"
            )
            preco = self._parse_price(preco_payload.get("Valor") or preco_payload.get("valor"))
            texto = preco_payload.get("Modelo") or preco_payload.get("modelo") or modelo_entry.get("nome")
            codigo = preco_payload.get("CodigoFipe") or preco_payload.get("codigoFipe")
            if not preco:
                return FipeMatchResult(match="failed")

            # Combined confidence: brand + model fuzzy scores
            confidence = (marca_score + modelo_score) / 2
            if confidence < MIN_PARALLELUM_SCORE:
                return FipeMatchResult(match="failed")
            match_kind = "exact" if modelo_score >= 90 else "closest"
            return FipeMatchResult(
                codigo=str(codigo) if codigo else None,
                texto=str(texto) if texto else None,
                preco=preco,
                match=match_kind,
            )
        except Exception:
            return FipeMatchResult(match="failed")

    def _find_best_label(
        self,
        items: list[dict[str, Any]] | dict[str, Any],
        target: str,
        min_score: int = 1,
    ) -> tuple[dict[str, Any] | None, int]:
        if isinstance(items, dict):
            items = items.get("modelos") or items.get("marcas") or []
        if not items:
            return None, 0

        target_norm = _normalize(target)
        target_tokens = _tokenize(target)
        best: dict[str, Any] | None = None
        best_score = -1

        for item in items:
            label = str(item.get("nome") or item.get("name") or "")
            label_norm = _normalize(label)
            label_tokens = _tokenize(label)
            score = self._score_labels(target_norm, target_tokens, label_norm, label_tokens)
            if score > best_score:
                best_score = score
                best = item

        if best is None or best_score < min_score:
            return None, best_score if best_score > 0 else 0
        return best, best_score

    @staticmethod
    def _score_labels(
        target_norm: str,
        target_tokens: set[str],
        label_norm: str,
        label_tokens: set[str],
    ) -> int:
        if not target_norm or not label_norm:
            return 0
        if label_norm == target_norm:
            return 100
        if target_norm in label_norm or label_norm in target_norm:
            return 85

        if not target_tokens:
            return 0

        overlap = target_tokens & label_tokens
        if not overlap:
            return 0

        # Require the main model family token (usually first meaningful token) to match.
        # Avoids VW 26.260 → Gol via weak token overlap.
        significant = [t for t in sorted(target_tokens, key=len, reverse=True) if len(t) >= 2 or "." in t]
        if significant:
            head = significant[0]
            if head not in label_tokens:
                fuzzy_ok = False
                for lt in label_tokens:
                    if len(head) >= 4 and (head in lt or lt in head):
                        fuzzy_ok = True
                        break
                    if len(head) >= 3 and head == lt[: len(head)]:
                        fuzzy_ok = True
                        break
                if not fuzzy_ok:
                    return 0

        recall = len(overlap) / len(target_tokens)
        precision = len(overlap) / max(len(label_tokens), 1)
        return int(round(100 * (0.65 * recall + 0.35 * precision)))

    def _pick_year(
        self,
        anos: list[dict[str, Any]],
        ano_mod: int | None,
        combustivel: str | None,
    ) -> dict[str, Any] | None:
        if not anos:
            return None

        fuel_norm = _normalize(combustivel or "")
        fuel_aliases = {
            "flex": ("flex",),
            "gasolina": ("gasolina", "gas"),
            "diesel": ("diesel",),
            "etanol": ("etanol", "alcool", "álcool"),
            "híbrido": ("hibrido", "híbrido"),
            "eletrico": ("eletrico", "elétrico"),
            "elétrico": ("eletrico", "elétrico"),
        }
        wanted_fuels = fuel_aliases.get(fuel_norm, ())

        year_matches: list[dict[str, Any]] = []
        for item in anos:
            nome = str(item.get("nome") or item.get("name") or "")
            if ano_mod is not None and str(ano_mod) not in nome:
                continue
            year_matches.append(item)

        pool = year_matches or (anos if ano_mod is None else [])
        if not pool:
            return None

        if wanted_fuels:
            for item in pool:
                nome = _normalize(str(item.get("nome") or ""))
                if any(f in nome for f in wanted_fuels):
                    return item

        return pool[0]

    @staticmethod
    def _parse_price(value: Any) -> float | None:
        if value is None:
            return None
        if isinstance(value, (int, float)):
            return float(value)
        text = str(value)
        digits = re.sub(r"[^\d,]", "", text)
        if not digits:
            return None
        if "," in digits:
            digits = digits.replace(".", "").replace(",", ".")
        try:
            return float(digits)
        except ValueError:
            return None
