<?php

namespace App\Services\Ine;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class IneExtractor
{
    public function __construct(
        private readonly OpenAiVisionIneExtractor $vision,
        private readonly TesseractIneExtractor $tesseract,
        private readonly IneTextParser $parser,
    ) {}

    public function available(): bool
    {
        return $this->vision->available() || $this->tesseract->available();
    }

    public function extract(UploadedFile $front, ?UploadedFile $back = null): array
    {
        $results = [];

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
