from __future__ import annotations

from shared.models import SodreLotRaw

BRAND_ALIASES = {
    "bmw": "BMW",
    "vw": "Volkswagen",
    "mercedes": "Mercedes-Benz",
    "mercedes benz": "Mercedes-Benz",
}

SHORT_BRANDS = {"r", "sr", "re", "reb"}


def normalize_marca(raw: SodreLotRaw) -> str:
    brand = (raw.lot_brand or "").strip()
    title = (raw.lot_title or "").strip()

    if brand.lower() in BRAND_ALIASES:
        return BRAND_ALIASES[brand.lower()]

    if brand.lower() in SHORT_BRANDS or len(brand) <= 2:
        inferred = infer_marca_from_title(title)
        if inferred:
            return inferred

    if not brand:
        return "Desconhecida"

    return brand.title()


def infer_marca_from_title(title: str) -> str | None:
    if not title:
        return None

    if "-" in title:
        after_dash = title.split("-", 1)[1].strip()
        if after_dash:
            candidate = after_dash.split()[0]
            if len(candidate) > 2 and candidate.lower() not in SHORT_BRANDS:
                return candidate.title()

    first_word = title.split()[0]
    if len(first_word) > 2 and first_word.lower() not in SHORT_BRANDS:
        return first_word.title()

    return None
