FROM python:3.11-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PADDLE_PDX_MODEL_SOURCE=BOS \
    PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK=True \
    FLAGS_use_mkldnn=0 \
    OMP_NUM_THREADS=2 \
    OPENBLAS_NUM_THREADS=2

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        libgomp1 \
        libglib2.0-0 \
        libgl1 \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY docker/ocr/requirements.txt ./requirements.txt
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

COPY ocr_service/ ./ocr_service/

RUN useradd --create-home --uid 10001 ocr \
    && mkdir -p /models \
    && chown -R ocr:ocr /app /models

USER ocr
ENV HOME=/models \
    PADDLE_HOME=/models/paddle \
    PADDLEX_HOME=/models/paddlex

EXPOSE 8000

CMD ["uvicorn", "ocr_service.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
