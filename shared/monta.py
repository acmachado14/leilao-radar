from __future__ import annotations

import re
import unicodedata
from typing import Literal

MontaClass = Literal["sem_sinistro", "pequena", "media", "grande", "outro"]

MONTA_LABELS: dict[MontaClass, str] = {
    "sem_sinistro": "sem sinistro",
    "pequena": "pequena monta",
    "media": "média monta",
    "grande": "grande monta",
    "outro": "outro",
}


def normalize_sinistro_text(value: str | None) -> str:
    text = unicodedata.normalize("NFKD", (value or "").strip().lower())
    return "".join(ch for ch in text if not unicodedata.combining(ch))


def parse_monta_class(sinistro: str | None) -> MontaClass:
    text = normalize_sinistro_text(sinistro)
    if not text:
        return "outro"
    if re.search(r"grande\s*monta", text):
        return "grande"
    if re.search(r"media\s*monta", text):
        return "media"
    if re.search(r"pequena\s*monta", text):
        return "pequena"
    if "sem sinistro" in text:
        return "sem_sinistro"
    return "outro"


def monta_label(sinistro: str | None) -> str:
    cls = parse_monta_class(sinistro)
    if cls == "outro" and sinistro and sinistro.strip():
        return sinistro.strip()
    return MONTA_LABELS[cls]


def monta_component(sinistro: str | None) -> tuple[float, MontaClass, bool]:
    """Score for ranking; grande monta is excluded."""
    cls = parse_monta_class(sinistro)
    if cls == "grande":
        return 0.0, cls, True
    scores: dict[MontaClass, float] = {
        "sem_sinistro": 1.0,
        "pequena": 1.0,
        "media": 0.55,
        "grande": 0.0,
        "outro": 0.4,
    }
    return scores[cls], cls, False
