<?php

namespace App\Services\Ine;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class OpenAiVisionIneExtractor
{
    public function __construct(private readonly IneTextParser $parser) {}

    public function available(): bool
    {
        return filled(config('services.openai_vision.api_key'));
    }

    public function extract(UploadedFile $front, ?UploadedFile $back = null): array
    {
        if (! $this->available()) {
            throw new \RuntimeException('El reconocimiento visual avanzado no está configurado.');
        }

        $content = [
            [
                'type' => 'input_text',
                'text' => implode(' ', [
                    'Lee esta credencial INE mexicana.',
                    'Copia únicamente texto visible; no completes ni adivines letras.',
                    'En el bloque NOMBRE, la INE imprime apellido paterno, apellido materno y después nombre(s).',
                    'Devuelve null para todo campo que no sea claramente legible.',
                ]),
            ],
            $this->imageContent($front),
        ];

        if ($back !== null) {
            $content[] = $this->imageContent($back);
        }

        $response = $this->client()->post('/responses', [
            'model' => (string) config('services.openai_vision.model', 'gpt-5.4-mini'),
            'store' => false,
            'max_output_tokens' => 700,
            'instructions' => implode(' ', [
                'Eres un lector preciso de credenciales INE mexicanas.',
                'No infieras nombres a partir de la CURP y no inventes caracteres.',
                'Respeta el orden impreso del bloque NOMBRE.',
            ]),
            'input' => [[
                'role' => 'user',
                'content' => $content,
            ]],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'ine_identity_fields',
                    'strict' => true,
                    'schema' => $this->schema(),
                ],
            ],
        ])->throw()->json();

        $json = $this->outputText($response);
        $fields = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($fields)) {
            throw new \RuntimeException('El reconocimiento visual devolvió una respuesta inválida.');
        }

        return $this->parser->parse($this->asOcrText($fields));
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl(rtrim((string) config('services.openai_vision.base_url'), '/'))
            ->withToken((string) config('services.openai_vision.api_key'))
            ->acceptJson()
            ->asJson()
            ->connectTimeout(10)
            ->timeout(75)
            ->retry(2, 400, throw: false);
    }

    /** @return array{type: string, image_url: string, detail: string} */
    private function imageContent(UploadedFile $image): array
    {
        $contents = file_get_contents($image->getRealPath());
        if ($contents === false) {
            throw new \RuntimeException('No fue posible leer la fotografía de la INE.');
        }

        return [
            'type' => 'input_image',
            'image_url' => 'data:'.$image->getMimeType().';base64,'.base64_encode($contents),
            'detail' => 'high',
        ];
    }

    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $item) {
            foreach ($item['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'output_text' && is_string($content['text'] ?? null)) {
                    return $content['text'];
                }
            }
        }

        throw new \RuntimeException('El reconocimiento visual no devolvió datos.');
    }

    private function asOcrText(array $fields): string
    {
        $value = fn (string $field): string => is_string($fields[$field] ?? null)
            ? trim($fields[$field])
            : '';
        $lines = [
            'NOMBRE',
            $value('paternal_last_name'),
            $value('maternal_last_name'),
            $value('first_name'),
            'DOMICILIO',
            $value('address_street'),
            $value('neighborhood'),
            $value('locality'),
        ];

        if ($value('postal_code') !== '') {
            $lines[] = 'C.P. '.$value('postal_code');
        }

        if ($value('curp') !== '') {
            $lines[] = 'CURP '.$value('curp');
        }

        if ($value('birth_date') !== '') {
            $lines[] = 'FECHA DE NACIMIENTO '.$value('birth_date');
        }

        return implode("\n", array_filter($lines, fn (string $line): bool => $line !== ''));
    }

    private function schema(): array
    {
        $nullableString = ['type' => ['string', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'first_name' => $nullableString,
                'paternal_last_name' => $nullableString,
                'maternal_last_name' => $nullableString,
                'curp' => $nullableString,
                'birth_date' => $nullableString,
                'address_street' => $nullableString,
                'neighborhood' => $nullableString,
                'locality' => $nullableString,
                'postal_code' => $nullableString,
            ],
            'required' => [
                'first_name',
                'paternal_last_name',
                'maternal_last_name',
                'curp',
                'birth_date',
                'address_street',
                'neighborhood',
                'locality',
                'postal_code',
            ],
        ];
    }
}
