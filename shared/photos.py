from __future__ import annotations

from typing import Any

SODRE_HOST = "https://leilao.sodresantoro.com.br"
PHOTOS_HOST = "https://photos.sodresantoro.com.br"
MAX_PHOTOS = 8


def _extract_url(value: Any) -> str | None:
    if value is None:
        return None
    if isinstance(value, str):
        text = value.strip()
        return text or None
    if isinstance(value, dict):
        for key in ("url", "src", "path", "href", "picture", "image"):
            nested = value.get(key)
            if nested:
                return _extract_url(nested)
    return None


def normalize_photo_url(url: str) -> str | None:
    text = url.strip()
    if not text:
        return None
    if text.startswith("//"):
        return f"https:{text}"
    if text.startswith("http://") or text.startswith("https://"):
        return text
    if text.startswith("/"):
        if text.startswith("/veiculos/") or text.startswith("/photos/"):
            return f"{PHOTOS_HOST}{text}"
        return f"{SODRE_HOST}{text}"
    return f"{PHOTOS_HOST}/{text.lstrip('/')}"


def normalize_lot_pictures(raw: Any, max_photos: int = MAX_PHOTOS) -> tuple[str | None, list[str]]:
    if raw is None:
        return None, []

    items: list[Any]
    if isinstance(raw, list):
        items = raw
    else:
        items = [raw]

    seen: set[str] = set()
    photos: list[str] = []
    for item in items:
        url = _extract_url(item)
        if not url:
            continue
        normalized = normalize_photo_url(url)
        if not normalized or normalized in seen:
            continue
        seen.add(normalized)
        photos.append(normalized)
        if len(photos) >= max_photos:
            break

    cover = photos[0] if photos else None
    return cover, photos
