<?php

namespace App\Services\Ine;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class IneExtractor
{
    public function __construct(
        private readonly PaddleIneExtractor $paddle,
        private readonly OpenAiVisionIneExtractor $vision,
        private readonly TesseractIneExtractor $tesseract,
        private readonly IneTextParser $parser,
    ) {}

    public function available(): bool
    {
        return $this->paddle->available() || $this->vision->available() || $this->tesseract->available();
    }

    public function extract(UploadedFile $front, ?UploadedFile $back = null): array
    {
        $results = [];

        if ($this->paddle->available()) {
            try {
                $paddleResult = $this->paddle->extract($front, $back);
                $results[] = $paddleResult;

                if (isset(
                    $paddleResult['fields']['curp'],
                    $paddleResult['fields']['first_name'],
                    $paddleResult['fields']['paternal_last_name'],
                    $paddleResult['fields']['maternal_last_name'],
                )) {
                    return $paddleResult;
                }
            } catch (\Throwable $exception) {
                Log::warning('PaddleOCR no pudo leer la INE; se usará el siguiente respaldo.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($this->vision->available()) {
            try {
                $visionResult = $this->vision->extract($front, $back);
                $results[] = $visionResult;

                if (isset(
                    $visionResult['fields']['curp'],
                    $visionResult['fields']['first_name'],
                    $visionResult['fields']['paternal_last_name'],
                    $visionResult['fields']['maternal_last_name'],
                )) {
                    return $visionResult;
                }
            } catch (\Throwable $exception) {
                Log::warning('El reconocimiento visual avanzado de INE falló; se usará el respaldo local.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($this->tesseract->available()) {
            try {
                $results[] = $this->tesseract->extract($front, $back);
            } catch (\Throwable $exception) {
                Log::warning('El reconocimiento local de INE falló.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        if ($results === []) {
            throw new \RuntimeException('Ningún motor pudo procesar la fotografía de la INE.');
        }

        return $this->parser->merge($results);
    }
}
