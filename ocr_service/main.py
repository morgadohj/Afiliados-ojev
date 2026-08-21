from __future__ import annotations

import time

from fastapi import FastAPI, File, HTTPException, UploadFile

from .engine import PaddleEngine
from .image_pipeline import decode_image, prepare_document


app = FastAPI(title="OJEV INE OCR", version="1.0.0", docs_url=None, redoc_url=None)
engine = PaddleEngine()


@app.get("/health")
def health() -> dict:
    return {"status": "ok", "engine": "paddleocr", "model_loaded": engine.ready()}


@app.post("/v1/ine/extract")
async def extract_ine(
    ine_front: UploadFile = File(...),
    ine_back: UploadFile | None = File(default=None),
) -> dict:
    started = time.monotonic()
    documents = [("front", ine_front)]
    if ine_back is not None:
        documents.append(("back", ine_back))

    all_lines = []
    quality = {}
    warnings: list[str] = []

    for side, upload in documents:
        if upload.content_type not in {"image/jpeg", "image/png", "image/webp"}:
            raise HTTPException(status_code=422, detail="El archivo debe ser una fotografía JPG, PNG o WebP.")

        contents = await upload.read()
        if len(contents) > 10 * 1024 * 1024:
            raise HTTPException(status_code=422, detail="La fotografía supera el límite de 10 MB.")

        try:
            image = decode_image(contents)
            document, report = prepare_document(image)
            lines = engine.recognize(document)
        except ValueError as exc:
            raise HTTPException(status_code=422, detail=str(exc)) from exc
        except Exception as exc:
            raise HTTPException(status_code=503, detail="El motor OCR local no pudo procesar la credencial.") from exc

        quality[side] = report.json()
        warnings.extend(report.warnings)
        all_lines.extend(lines)

    if not all_lines:
        warnings.append("No se encontró texto legible en la fotografía.")

    return {
        "engine": "paddleocr",
        "raw_text": "\n".join(line.text for line in all_lines),
        "lines": [line.json() for line in all_lines],
        "quality": quality,
        "warnings": list(dict.fromkeys(warnings)),
        "processing_ms": round((time.monotonic() - started) * 1000),
    }
