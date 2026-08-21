<?php

namespace App\Services\Ine;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class PaddleIneExtractor
{
    public function __construct(private readonly IneTextParser $parser) {}

    public function available(): bool
    {
        if (blank(config('services.paddle_ocr.base_url'))) {
            return false;
        }

        try {
            return $this->client(timeout: 3)->get('/health')->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    public function extract(UploadedFile $front, ?UploadedFile $back = null): array
    {
        $request = $this->client(timeout: 90)
            ->attach('ine_front', fopen($front->getRealPath(), 'rb'), $front->getClientOriginalName());

        if ($back !== null) {
            $request->attach('ine_back', fopen($back->getRealPath(), 'rb'), $back->getClientOriginalName());
        }

        $payload = $request->post('/v1/ine/extract')->throw()->json();
        $rawText = $payload['raw_text'] ?? null;
        if (! is_string($rawText)) {
            throw new \RuntimeException('PaddleOCR devolvió una respuesta inválida.');
        }

        $result = $this->parser->parse($rawText);
        $qualityWarnings = array_values(array_filter(
            $payload['warnings'] ?? [],
            fn (mixed $warning): bool => is_string($warning) && $warning !== '',
        ));
        $result['warnings'] = array_values(array_unique([
            ...$qualityWarnings,
            ...$result['warnings'],
        ]));

        return $result;
    }

    private function client(int $timeout): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.paddle_ocr.base_url'), '/'))
            ->acceptJson()
            ->connectTimeout(3)
            ->timeout($timeout)
            ->retry(1, 250, throw: false);
    }
}
