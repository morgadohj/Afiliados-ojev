from __future__ import annotations

from dataclasses import asdict, dataclass

import cv2
import numpy as np
from PIL import Image, ImageOps


CARD_RATIO = 85.60 / 53.98


@dataclass(frozen=True)
class ImageQuality:
    blur_score: float
    brightness: float
    glare_ratio: float
    dark_ratio: float
    document_coverage: float
    perspective_corrected: bool
    warnings: list[str]

    def json(self) -> dict:
        return asdict(self)


def decode_image(contents: bytes) -> np.ndarray:
    try:
        with Image.open(__import__("io").BytesIO(contents)) as source:
            image = ImageOps.exif_transpose(source).convert("RGB")
            return cv2.cvtColor(np.asarray(image), cv2.COLOR_RGB2BGR)
    except Exception as exc:
        raise ValueError("La fotografía no contiene una imagen válida.") from exc


def prepare_document(image: np.ndarray) -> tuple[np.ndarray, ImageQuality]:
    if min(image.shape[:2]) < 640:
        image = _resize_long_edge(image, 1800, allow_upscale=True)
    else:
        image = _resize_long_edge(image, 2200)

    document, coverage, corrected = _rectify_card(image)
    document = _normalize_card_orientation(document)
    document = _enhance_luminance(document)

    gray = cv2.cvtColor(document, cv2.COLOR_BGR2GRAY)
    blur_score = float(cv2.Laplacian(gray, cv2.CV_64F).var())
    brightness = float(np.mean(gray))
    glare_ratio = float(np.mean(gray >= 248))
    dark_ratio = float(np.mean(gray <= 28))
    warnings: list[str] = []

    if blur_score < 75:
        warnings.append("La fotografía está desenfocada; vuelve a capturarla con el teléfono inmóvil.")
    if brightness < 65:
        warnings.append("La fotografía está demasiado oscura.")
    if brightness > 225:
        warnings.append("La fotografía tiene demasiada iluminación.")
    if glare_ratio > 0.12:
        warnings.append("Hay reflejos que cubren parte de la credencial.")
    if dark_ratio > 0.30:
        warnings.append("Hay sombras fuertes sobre la credencial.")
    if coverage < 0.28:
        warnings.append("Acerca la credencial para que ocupe la mayor parte de la cámara.")
    if not corrected:
        warnings.append("No se detectaron claramente las cuatro esquinas de la credencial.")

    return document, ImageQuality(
        blur_score=round(blur_score, 2),
        brightness=round(brightness, 2),
        glare_ratio=round(glare_ratio, 4),
        dark_ratio=round(dark_ratio, 4),
        document_coverage=round(coverage, 4),
        perspective_corrected=corrected,
        warnings=warnings,
    )


def _resize_long_edge(image: np.ndarray, size: int, allow_upscale: bool = False) -> np.ndarray:
    height, width = image.shape[:2]
    longest = max(height, width)
    if longest <= size and not allow_upscale:
        return image

    scale = size / longest
    interpolation = cv2.INTER_CUBIC if scale > 1 else cv2.INTER_AREA
    return cv2.resize(image, (round(width * scale), round(height * scale)), interpolation=interpolation)


def _rectify_card(image: np.ndarray) -> tuple[np.ndarray, float, bool]:
    height, width = image.shape[:2]
    image_area = float(height * width)
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    gray = cv2.GaussianBlur(gray, (5, 5), 0)
    edges = cv2.Canny(gray, 45, 145)
    edges = cv2.morphologyEx(edges, cv2.MORPH_CLOSE, np.ones((7, 7), np.uint8), iterations=2)
    contours, _ = cv2.findContours(edges, cv2.RETR_LIST, cv2.CHAIN_APPROX_SIMPLE)

    for contour in sorted(contours, key=cv2.contourArea, reverse=True)[:20]:
        area = cv2.contourArea(contour)
        if area / image_area < 0.18:
            continue

        perimeter = cv2.arcLength(contour, True)
        polygon = cv2.approxPolyDP(contour, 0.025 * perimeter, True)
        if len(polygon) != 4 or not cv2.isContourConvex(polygon):
            continue

        points = _order_points(polygon.reshape(4, 2).astype(np.float32))
        warped = _warp_card(image, points)
        return warped, min(1.0, area / image_area), True

    return image, 1.0, False


def _order_points(points: np.ndarray) -> np.ndarray:
    ordered = np.zeros((4, 2), dtype=np.float32)
    sums = points.sum(axis=1)
    differences = np.diff(points, axis=1).reshape(-1)
    ordered[0] = points[np.argmin(sums)]
    ordered[2] = points[np.argmax(sums)]
    ordered[1] = points[np.argmin(differences)]
    ordered[3] = points[np.argmax(differences)]
    return ordered


def _warp_card(image: np.ndarray, points: np.ndarray) -> np.ndarray:
    top_left, top_right, bottom_right, bottom_left = points
    measured_width = max(
        np.linalg.norm(bottom_right - bottom_left),
        np.linalg.norm(top_right - top_left),
    )
    measured_height = max(
        np.linalg.norm(top_right - bottom_right),
        np.linalg.norm(top_left - bottom_left),
    )
    landscape = measured_width >= measured_height
    target_width, target_height = (1600, round(1600 / CARD_RATIO)) if landscape else (round(1600 / CARD_RATIO), 1600)
    destination = np.array([
        [0, 0],
        [target_width - 1, 0],
        [target_width - 1, target_height - 1],
        [0, target_height - 1],
    ], dtype=np.float32)
    matrix = cv2.getPerspectiveTransform(points, destination)
    return cv2.warpPerspective(image, matrix, (target_width, target_height), borderMode=cv2.BORDER_REPLICATE)


def _normalize_card_orientation(image: np.ndarray) -> np.ndarray:
    height, width = image.shape[:2]
    return cv2.rotate(image, cv2.ROTATE_90_CLOCKWISE) if height > width else image


def _enhance_luminance(image: np.ndarray) -> np.ndarray:
    lab = cv2.cvtColor(image, cv2.COLOR_BGR2LAB)
    luminance, channel_a, channel_b = cv2.split(lab)
    clahe = cv2.createCLAHE(clipLimit=1.8, tileGridSize=(8, 8))
    luminance = clahe.apply(luminance)
    return cv2.cvtColor(cv2.merge((luminance, channel_a, channel_b)), cv2.COLOR_LAB2BGR)
