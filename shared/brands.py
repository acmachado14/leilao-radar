from __future__ import annotations

BRAND_ALIASES = {
    "bmw": "BMW",
    "vw": "Volkswagen",
    "volks": "Volkswagen",
    "volkswagen": "Volkswagen",
    "mercedes": "Mercedes-Benz",
    "mercedes benz": "Mercedes-Benz",
    "mercedes-benz": "Mercedes-Benz",
    "m.benz": "Mercedes-Benz",
    "m benz": "Mercedes-Benz",
    "mb": "Mercedes-Benz",
    "gm": "Chevrolet",
    "chevy": "Chevrolet",
    "chevrolet": "Chevrolet",
    "citroen": "Citroën",
    "citroën": "Citroën",
    "land rover": "Land Rover",
    "mitsubish": "Mitsubishi",
    "mitsubishi": "Mitsubishi",
}

SHORT_BRANDS = {"r", "sr", "re", "reb"}


def normalize_marca(*, marca: str | None = None, titulo: str | None = None) -> str:
    brand = (marca or "").strip()
    title = (titulo or "").strip()

    alias = BRAND_ALIASES.get(brand.lower())
    if alias:
        return alias

    if brand.lower() in SHORT_BRANDS or len(brand) <= 2:
        inferred = infer_marca_from_title(title)
        if inferred:
            return inferred

    if not brand:
        inferred = infer_marca_from_title(title)
        return inferred or "Desconhecida"

    return brand.title()


def infer_marca_from_title(title: str) -> str | None:
    if not title:
        return None

    if "/" in title:
        left = title.split("/", 1)[0].strip()
        alias = BRAND_ALIASES.get(left.lower())
        if alias:
            return alias
        if len(left) > 1:
            return left.title()

    if "-" in title:
        after_dash = title.split("-", 1)[1].strip()
        if after_dash:
            candidate = after_dash.split()[0]
            if len(candidate) > 2 and candidate.lower() not in SHORT_BRANDS:
                return BRAND_ALIASES.get(candidate.lower(), candidate.title())

    first_word = title.split()[0]
    if len(first_word) > 2 and first_word.lower() not in SHORT_BRANDS:
        return BRAND_ALIASES.get(first_word.lower(), first_word.title())

    return None
