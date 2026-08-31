from __future__ import annotations

import logging
import os


def setup_logging(default_level: str | None = None) -> None:
    level_name = (default_level or os.environ.get("LOG_LEVEL", "INFO")).upper()
    level = getattr(logging, level_name, logging.INFO)

    root = logging.getLogger()
    if root.handlers:
        root.setLevel(level)
        return

    logging.basicConfig(
        level=level,
        format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
        datefmt="%H:%M:%S",
    )

    # Streamlit spawns child processes; keep our app logs visible in the terminal.
    for name in ("dashboard", "collector", "shared"):
        logging.getLogger(name).setLevel(level)
