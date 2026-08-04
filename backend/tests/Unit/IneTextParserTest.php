<?php

use App\Services\Ine\IneTextParser;

it('extracts reviewable identity fields from INE text', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        INSTITUTO NACIONAL ELECTORAL
        CREDENCIAL PARA VOTAR
        NOMBRE
        GOMEZ
        MARTINEZ
        JUAN CARLOS
        DOMICILIO
        CALLE PRINCIPAL 123
        COL CENTRO C.P. 91000
        XALAPA, VER.
        CLAVE DE ELECTOR GMJRJN90010130H100
        CURP GOMJ900101HVZMRS09
        TEXT);

    expect($result['fields']['first_name']['value'])->toBe('JUAN CARLOS')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('GOMEZ')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('MARTINEZ')
        ->and($result['fields']['curp']['value'])->toBe('GOMJ900101HVZMRS09')
        ->and($result['fields']['birth_date']['value'])->toBe('1990-01-01')
        ->and($result['fields']['state']['value'])->toBe('Veracruz')
        ->and($result['fields']['postal_code']['value'])->toBe('91000')
        ->and($result['fields']['address_street']['value'])->toBe('CALLE PRINCIPAL 123')
        ->and($result)->not->toHaveKey('raw_text');
});

it('tolerates common OCR substitutions in INE labels names and CURP', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        INSTITUTO NACIONAL ELECTORAL
        N0M8RE
        G0MEZ
        MARTINEZ
        JUAN CARL05
        D0MICILI0
        AV REFORMA 123
        COL. CENTRO 91000
        XALAPA, VER.
        CLAVE DE ELECTOR GMJRJN90010130H100
        CURP GOMJ9OO1O1HVZMRSO9
        TEXT);

    expect($result['fields']['first_name']['value'])->toBe('JUAN CARLOS')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('GOMEZ')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('MARTINEZ')
        ->and($result['fields']['curp']['value'])->toBe('GOMJ900101HVZMRS09')
        ->and($result['fields']['birth_date']['value'])->toBe('1990-01-01')
        ->and($result['fields']['address_street']['value'])->toBe('AV REFORMA 123')
        ->and($result['fields']['neighborhood']['value'])->toBe('CENTRO')
        ->and($result['fields']['postal_code']['value'])->toBe('91000');
});

it('combines complementary readings from different Tesseract layout modes', function () {
    $parser = app(IneTextParser::class);
    $result = $parser->merge([
        $parser->parse("CURP GOMJ900101HVZMRS09\n"),
        $parser->parse("NOMBRE\nGOMEZ\nMARTINEZ\nJUAN CARLOS\nDOMICILIO\n"),
    ]);

    expect($result['fields']['curp']['value'])->toBe('GOMJ900101HVZMRS09')
        ->and($result['fields']['first_name']['value'])->toBe('JUAN CARLOS')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('GOMEZ');
});
