<?php

use App\Services\Ine\OpenAiVisionIneExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

it('extracts and validates structured INE fields with vision', function () {
    config()->set('services.openai_vision.api_key', 'test-key');
    config()->set('services.openai_vision.base_url', 'https://api.openai.test/v1');
    config()->set('services.openai_vision.model', 'gpt-5.4-mini');

    Http::fake([
        'api.openai.test/v1/responses' => Http::response([
            'output' => [[
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode([
                        'first_name' => 'NELLY GRACIELA',
                        'paternal_last_name' => 'DELFIN',
                        'maternal_last_name' => 'AYUSO',
                        'curp' => 'DEAN910525MVZLYL00',
                        'birth_date' => '25/05/1991',
                        'address_street' => 'AV MIGUEL ALEMAN 434 DEP 8',
                        'neighborhood' => 'CENTRO',
                        'locality' => 'VERACRUZ',
                        'postal_code' => '91700',
                    ], JSON_THROW_ON_ERROR),
                ]],
            ]],
        ]),
    ]);

    $result = app(OpenAiVisionIneExtractor::class)->extract(
        UploadedFile::fake()->image('ine.jpg', 1200, 800),
    );

    expect($result['fields']['first_name']['value'])->toBe('NELLY GRACIELA')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('DELFIN')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('AYUSO')
        ->and($result['fields']['curp']['value'])->toBe('DEAN910525MVZLYL00');

    Http::assertSent(fn ($request): bool => $request['store'] === false
        && $request['text']['format']['type'] === 'json_schema'
        && str_starts_with($request['input'][0]['content'][1]['image_url'], 'data:image/jpeg;base64,'));
});

it('is unavailable until an API key is configured', function () {
    config()->set('services.openai_vision.api_key', null);

    expect(app(OpenAiVisionIneExtractor::class)->available())->toBeFalse();
});
