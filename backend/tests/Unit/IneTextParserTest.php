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

it('recovers the name block when the small NOMBRE label is not recognized', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        INSTITUTO NACIONAL ELECTORAL
        CREDENCIAL PARA VOTAR
        GOMEZ
        MARTINEZ
        JUAN CARLOS
        DOMICILIO
        CALLE PRINCIPAL 123
        CURP GOMJ900101HVZMRS09
        TEXT);

    expect($result['fields']['first_name']['value'])->toBe('JUAN CARLOS')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('GOMEZ')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('MARTINEZ');
});

it('splits a name that OCR returns on one line using CURP initials', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        NOMBRE GOMEZ MARTINEZ JUAN CARLOS
        DOMICILIO
        CALLE PRINCIPAL 123
        CURP GOMJ900101HVZMRS09
        TEXT);

    expect($result['fields']['first_name']['value'])->toBe('JUAN CARLOS')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('GOMEZ')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('MARTINEZ');
});

it('separates both surnames when OCR groups them on one line', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        NOMBRE
        GOMEZ MARTINEZ
        JUAN CARLOS
        DOMICILIO
        CALLE PRINCIPAL 123
        CURP GOMJ900101HVZMRS09
        TEXT);

    expect($result['fields']['first_name']['value'])->toBe('JUAN CARLOS')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('GOMEZ')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('MARTINEZ');
});

it('orders name lines with the CURP and ignores sex labels from a vertical Android capture', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        INSTITUTO NACIONAL ELECTORAL
        CREDENCIAL PARA VOTAR
        NOMBRE
        SEXO M
        NERO
        AYUSO
        DELFIN
        NELLY GRACIELA
        DOMICILIO
        AV MIGUEL ALEMAN 434 DEP 8
        COL CENTRO 91700
        VERACRUZ, VER.
        CLAVE DE ELECTOR DLAYNL91052530M100
        CURP DEAN910525MVZLYL00
        TEXT);

    expect($result['fields']['first_name']['value'])->toBe('NELLY GRACIELA')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('DELFIN')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('AYUSO')
        ->and($result['fields']['paternal_last_name']['value'])->not->toContain('SEXO')
        ->and($result['fields']['maternal_last_name']['value'])->not->toBe('NERO');
});

it('prefers the OCR name reading whose initials agree with a CURP found in another pass', function () {
    $parser = app(IneTextParser::class);
    $result = $parser->merge([
        $parser->parse("NOMBRE\nAYUSO\nDELFIN\nNELLY GRACIELA\nDOMICILIO\n"),
        $parser->parse("CURP DEAN910525MVZLYL00\n"),
        $parser->parse("NOMBRE\nDELFIN\nAYUSO\nNELLY GRACIELA\nDOMICILIO\n"),
    ]);

    expect($result['fields']['first_name']['value'])->toBe('NELLY GRACIELA')
        ->and($result['fields']['paternal_last_name']['value'])->toBe('DELFIN')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('AYUSO');
});

it('keeps safe surname matches when one given-name initial is unreadable', function () {
    $result = app(IneTextParser::class)->parse(<<<'TEXT'
        NOMBRE
        DELFIN
        AYUSO
        MELLY GRACIELA
        DOMICILIO
        AV MIGUEL ALEMAN 434
        CURP DEAN910525MVZLYL00
        TEXT);

    expect($result['fields']['paternal_last_name']['value'])->toBe('DELFIN')
        ->and($result['fields']['maternal_last_name']['value'])->toBe('AYUSO')
        ->and($result['fields'])->not->toHaveKey('first_name');
});
