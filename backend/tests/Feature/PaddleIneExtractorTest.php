<?php

use App\Services\Ine\PaddleIneExtractor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.paddle_ocr.base_url', 'http://ocr.test:8000');
});

it('detects when the local PaddleOCR service is available', function () {
    Http::fake([
        'ocr.test:8000/health' => Http::response(['status' => 'ok']),
    ]);

    expect(app(PaddleIneExtractor::class)->available())->toBeTrue();
});

it('extracts INE fields with PaddleOCR and preserves quality warnings', function () {
    Http::fake([
        'ocr.test:8000/v1/ine/extract' => Http::response([
            'engine' => 'paddleocr',
            'raw_text' => implode("\n", [
                'INSTITUTO NACIONAL ELECTORAL',
                'NOMBRE',
                'DELFIN',
                'AYUSO',
                'NELLY GRACIELA',
                'DOMICILIO',
                'AV MIGUEL ALEMAN 434 DEP 8',
                'COL CENTRO C.P. 91700',
                'VERACRUZ VER',
                'CURP DEAN910525MVZLYL00',
            ]),
            'warnings' => ['Hay un reflejo ligero en la credencial.'],
        ]),
    ]);

    $result = app(PaddleIneExtractor::class)->extract(
        UploadedFile::fake()->image('ine.jpg', 1600, 1000),
    );

    expect($result['fields']['first_name']['value'])->toBe('NELLY GRACIELA')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('DELFIN')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('AYUSO')
        ->and($result['fields']['curp']['value'])->toBe('DEAN910525MVZLYL00')
        ->and($result['warnings'])->toContain('Hay un reflejo ligero en la credencial.');

    Http::assertSent(fn ($request): bool => $request->url() === 'http://ocr.test:8000/v1/ine/extract'
        && $request->method() === 'POST');
});
