from __future__ import annotations

import re
import threading
from dataclasses import dataclass
from typing import Any

import numpy as np


@dataclass(frozen=True)
class TextLine:
    text: str
    confidence: float
    polygon: list[list[float]]

    @property
    def top(self) -> float:
        return min((point[1] for point in self.polygon), default=0.0)

    @property
    def left(self) -> float:
        return min((point[0] for point in self.polygon), default=0.0)

    def json(self) -> dict:
        return {
            "text": self.text,
            "confidence": round(self.confidence, 4),
            "polygon": self.polygon,
        }


class PaddleEngine:
    def __init__(self) -> None:
        self._engine: Any | None = None
        self._lock = threading.Lock()

    def ready(self) -> bool:
        return self._engine is not None

    def recognize(self, image: np.ndarray) -> list[TextLine]:
        engine = self._get_engine()
        with self._lock:
            results = list(engine.predict(image))

        lines: list[TextLine] = []
        for result in results:
            payload = getattr(result, "json", result)
            if callable(payload):
                payload = payload()
            if isinstance(payload, dict) and "res" in payload:
                payload = payload["res"]
            if not isinstance(payload, dict):
                continue

            texts = payload.get("rec_texts", [])
            scores = payload.get("rec_scores", [])
            polygons = payload.get("rec_polys", payload.get("dt_polys", []))
            for index, text in enumerate(texts):
                cleaned = _clean_text(str(text))
                score = float(scores[index]) if index < len(scores) else 0.0
                polygon = _polygon(polygons[index]) if index < len(polygons) else []
                if cleaned and score >= 0.28:
                    lines.append(TextLine(cleaned, score, polygon))

        return _reading_order(lines)

    def _get_engine(self):
        if self._engine is not None:
            return self._engine

        with self._lock:
            if self._engine is None:
                from paddleocr import PaddleOCR

                self._engine = PaddleOCR(
                    text_detection_model_name="PP-OCRv5_mobile_det",
                    text_recognition_model_name="latin_PP-OCRv5_mobile_rec",
                    use_doc_orientation_classify=True,
                    use_doc_unwarping=False,
                    use_textline_orientation=True,
                    device="cpu",
                    enable_mkldnn=False,
                    cpu_threads=2,
                )

        return self._engine


def _clean_text(text: str) -> str:
    text = text.upper().strip()
    return re.sub(r"\s+", " ", text)


def _polygon(value: Any) -> list[list[float]]:
    if hasattr(value, "tolist"):
        value = value.tolist()
    if not isinstance(value, list):
        return []
    return [[float(point[0]), float(point[1])] for point in value if isinstance(point, (list, tuple)) and len(point) >= 2]


def _reading_order(lines: list[TextLine]) -> list[TextLine]:
    if not lines:
        return []

    median_height = np.median([
        max((point[1] for point in line.polygon), default=0.0) - line.top
        for line in lines if line.polygon
    ] or [20.0])
    row_height = max(12.0, float(median_height) * 0.72)
    return sorted(lines, key=lambda line: (round(line.top / row_height), line.left))
