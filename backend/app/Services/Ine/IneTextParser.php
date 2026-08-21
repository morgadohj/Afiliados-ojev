<?php

namespace App\Services\Ine;

use Carbon\CarbonImmutable;

class IneTextParser
{
    private const CURP_STATE_CODES = [
        'AS' => 'Aguascalientes', 'BC' => 'Baja California',
        'BS' => 'Baja California Sur', 'CC' => 'Campeche',
        'CL' => 'Coahuila', 'CM' => 'Colima', 'CS' => 'Chiapas',
        'CH' => 'Chihuahua', 'DF' => 'Ciudad de México',
        'DG' => 'Durango', 'GT' => 'Guanajuato', 'GR' => 'Guerrero',
        'HG' => 'Hidalgo', 'JC' => 'Jalisco', 'MC' => 'Estado de México',
        'MN' => 'Michoacán', 'MS' => 'Morelos', 'NT' => 'Nayarit',
        'NL' => 'Nuevo León', 'OC' => 'Oaxaca', 'PL' => 'Puebla',
        'QT' => 'Querétaro', 'QR' => 'Quintana Roo',
        'SP' => 'San Luis Potosí', 'SL' => 'Sinaloa', 'SR' => 'Sonora',
        'TC' => 'Tabasco', 'TS' => 'Tamaulipas', 'TL' => 'Tlaxcala',
        'VZ' => 'Veracruz', 'YN' => 'Yucatán', 'ZS' => 'Zacatecas',
        'NE' => 'Nacido en el extranjero',
    ];

    public function parse(string $rawText): array
    {
        $normalized = $this->normalize($rawText);
        $lines = array_values(array_filter(array_map('trim', explode("\n", $normalized))));
        $fields = [];

        $curp = $this->extractCurp($lines);
        if ($curp !== null) {
            $fields['curp'] = $this->field($curp, 0.98);

            $birthDate = $this->birthDateFromCurp($curp);
            if ($birthDate !== null) {
                $fields['birth_date'] = $this->field($birthDate, 0.88);
            }

            $stateCode = substr($curp, 11, 2);
            if (isset(self::CURP_STATE_CODES[$stateCode])) {
                $fields['state'] = $this->field(self::CURP_STATE_CODES[$stateCode], 0.72);
            }
        }

        if (! isset($fields['birth_date'])
            && preg_match('/FECHA\s+DE\s+NACIMIENTO\s*[:\-]?\s*(\d{2})[\/\-.](\d{2})[\/\-.](\d{4})/', $normalized, $match)) {
            $fields['birth_date'] = $this->field("{$match[3]}-{$match[2]}-{$match[1]}", 0.9);
        }

        if (preg_match('/C\.?\s*P\.?\s*[:\-]?\s*(\d{5})/', $normalized, $match)) {
            $fields['postal_code'] = $this->field($match[1], 0.9);
        }

        $nameLines = $this->linesBetween($lines, 'NOMBRE', ['DOMICILIO', 'CLAVE DE ELECTOR', 'CURP']);
        $nameFields = $this->nameFieldsFromLines($nameLines, $curp, 0.76);

        if (! isset($nameFields['first_name'], $nameFields['paternal_last_name'])) {
            $fallbackLines = $this->nameLinesBeforeAddress($lines);
            $fallbackFields = $this->nameFieldsFromLines($fallbackLines, $curp, 0.62);
            $nameFields = [...$fallbackFields, ...$nameFields];
        }

        $fields = [...$fields, ...$nameFields];

        $addressLines = $this->linesBetween(
            $lines,
            'DOMICILIO',
            ['CLAVE DE ELECTOR', 'CURP', 'AÑO DE REGISTRO', 'FECHA DE NACIMIENTO'],
        );
        if ($addressLines !== []) {
            $fields['address_street'] = $this->field($this->cleanAddressLine($addressLines[0]), 0.6);

            if (isset($addressLines[1])) {
                $neighborhood = preg_replace('/\bC\.?\s*P\.?\s*\d{5}\b/', '', $addressLines[1]);
                $neighborhood = preg_replace('/\b\d{5}\b/', '', (string) $neighborhood);
                $neighborhood = preg_replace('/^(?:COL(?:ONIA)?\.?|FRACC(?:IONAMIENTO)?\.?)\s+/i', '', (string) $neighborhood);
                $fields['neighborhood'] = $this->field(trim((string) $neighborhood), 0.45);
            }

            if (isset($addressLines[2])) {
                $fields['locality'] = $this->field($this->cleanAddressLine($addressLines[2]), 0.42);
            }

            if (! isset($fields['postal_code'])) {
                foreach ($addressLines as $addressLine) {
                    if (preg_match('/\b(\d{5})\b/', $addressLine, $match)) {
                        $fields['postal_code'] = $this->field($match[1], 0.78);
                        break;
                    }
                }
            }
        }

        return [
            'fields' => $fields,
            'warnings' => $this->warnings($fields),
        ];
    }

    public function score(array $fields): int
    {
        $weights = [
            'curp' => 8,
            'first_name' => 4,
            'paternal_last_name' => 3,
            'maternal_last_name' => 2,
            'address_street' => 3,
            'postal_code' => 2,
            'birth_date' => 1,
            'state' => 1,
        ];

        $score = array_sum(array_map(
            fn (string $field): int => isset($fields[$field]) ? $weights[$field] : 0,
            array_keys($weights),
        ));

        $curp = $fields['curp']['value'] ?? null;
        if (is_string($curp) && strlen($curp) === 18) {
            $score += $this->curpNameScore($fields, $curp);
        }

        return $score;
    }

    public function merge(array $results): array
    {
        $sharedCurp = null;
        foreach ($results as $result) {
            $candidate = $result['fields']['curp']['value'] ?? null;
            if (is_string($candidate) && strlen($candidate) === 18) {
                $sharedCurp = $candidate;
                break;
            }
        }

        usort(
            $results,
            function (array $left, array $right) use ($sharedCurp): int {
                $score = function (array $result) use ($sharedCurp): int {
                    $fields = $result['fields'];
                    $score = $this->score($fields);

                    if ($sharedCurp !== null && ! isset($fields['curp'])) {
                        $score += $this->curpNameScore($fields, $sharedCurp);
                    }

                    return $score;
                };

                return $score($right) <=> $score($left);
            },
        );

        $fields = $results[0]['fields'] ?? [];
        foreach (array_slice($results, 1) as $result) {
            foreach ($result['fields'] as $name => $field) {
                if (! isset($fields[$name])) {
                    $fields[$name] = $field;
                }
            }
        }

        return [
            'fields' => $fields,
            'warnings' => $this->warnings($fields),
        ];
    }

    private function normalize(string $text): string
    {
        $text = mb_strtoupper($text, 'UTF-8');
        $text = strtr($text, [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U',
        ]);
        $text = preg_replace('/[^\p{L}\p{N}\/.:\-\n ]+/u', ' ', $text) ?? $text;

        return preg_replace('/[ \t]+/', ' ', $text) ?? $text;
    }

    private function linesBetween(array $lines, string $start, array $endLabels): array
    {
        $capturing = false;
        $result = [];

        foreach ($lines as $line) {
            if (! $capturing && $this->containsLabel($line, $start)) {
                $capturing = true;
                $inline = $this->contentAfterLabel($line, $start);
                if ($inline !== '') {
                    $result[] = $inline;
                }

                continue;
            }

            if (! $capturing) {
                continue;
            }

            foreach ($endLabels as $endLabel) {
                if ($this->containsLabel($line, $endLabel)) {
                    return array_slice($result, 0, 8);
                }
            }

            if (! $this->isNoise($line)) {
                $result[] = $line;
            }
        }

        return array_slice($result, 0, 8);
    }

    private function isNoise(string $line): bool
    {
        return preg_match('/^(INSTITUTO|NACIONAL|ELECTORAL|CREDENCIAL|PARA VOTAR|MEXICO|SEXO(?: [HM])?|GENERO(?: [HM])?|NERO(?: [HM])?|VIGENCIA)$/', $line) === 1
            || $this->containsLabel($line, 'FECHA DE NACIMIENTO');
    }

    /**
     * @return array<string, array{value: string, confidence: float, source: string}>
     */
    private function nameFieldsFromLines(array $lines, ?string $curp, float $confidence): array
    {
        $nameLines = array_values(array_filter(
            array_map($this->cleanPersonName(...), $lines),
            fn (string $line): bool => $line !== '' && ! $this->isNameNoise($line),
        ));

        if (count($nameLines) >= 3) {
            if ($curp !== null) {
                $curpMatchedFields = $this->nameFieldsMatchingCurp($nameLines, $curp, $confidence);
                if ($curpMatchedFields !== []) {
                    return $curpMatchedFields;
                }

                return [];
            }

            return [
                'paternal_last_name' => $this->field($nameLines[0], $confidence),
                'maternal_last_name' => $this->field($nameLines[1], $confidence),
                'first_name' => $this->field(implode(' ', array_slice($nameLines, 2)), $confidence),
            ];
        }

        if (count($nameLines) === 2) {
            $surnameParts = preg_split('/\s+/', $nameLines[0]) ?: [];
            if ($curp !== null && count($surnameParts) >= 2) {
                $maternalIndex = $this->tokenIndexWithInitial($surnameParts, $curp[2], 1);
                if ($maternalIndex !== null) {
                    return [
                        'paternal_last_name' => $this->field(implode(' ', array_slice($surnameParts, 0, $maternalIndex)), $confidence),
                        'maternal_last_name' => $this->field(implode(' ', array_slice($surnameParts, $maternalIndex)), $confidence),
                        'first_name' => $this->field($nameLines[1], $confidence),
                    ];
                }
            }

            return [
                'paternal_last_name' => $this->field($nameLines[0], $confidence - 0.12),
                'first_name' => $this->field($nameLines[1], $confidence - 0.12),
            ];
        }

        if (count($nameLines) !== 1 || $curp === null) {
            return [];
        }

        $tokens = preg_split('/\s+/', $nameLines[0]) ?: [];
        if (count($tokens) < 3 || ! str_starts_with($tokens[0], $curp[0])) {
            return [];
        }

        $maternalIndex = $this->tokenIndexWithInitial($tokens, $curp[2], 1);
        $givenNameIndex = $maternalIndex === null
            ? null
            : $this->tokenIndexWithInitial($tokens, $curp[3], $maternalIndex + 1);

        if ($maternalIndex === null || $givenNameIndex === null) {
            return [];
        }

        return [
            'paternal_last_name' => $this->field(implode(' ', array_slice($tokens, 0, $maternalIndex)), $confidence - 0.12),
            'maternal_last_name' => $this->field(implode(' ', array_slice($tokens, $maternalIndex, $givenNameIndex - $maternalIndex)), $confidence - 0.12),
            'first_name' => $this->field(implode(' ', array_slice($tokens, $givenNameIndex)), $confidence - 0.12),
        ];
    }

    private function tokenIndexWithInitial(array $tokens, string $initial, int $start): ?int
    {
        for ($index = $start; $index < count($tokens); $index++) {
            if (str_starts_with($tokens[$index], $initial)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Tesseract may return the three name lines in visual-column order instead
     * of reading order. The CURP provides reliable initials for paternal
     * surname, maternal surname and given name, so use them to restore order.
     *
     * @return array<string, array{value: string, confidence: float, source: string}>
     */
    private function nameFieldsMatchingCurp(array $nameLines, string $curp, float $confidence): array
    {
        $bestPartial = [];
        $bestPartialScore = 0;

        foreach ($nameLines as $paternalIndex => $paternal) {
            foreach ($nameLines as $maternalIndex => $maternal) {
                if ($maternalIndex === $paternalIndex) {
                    continue;
                }

                foreach ($nameLines as $givenIndex => $givenName) {
                    if (in_array($givenIndex, [$paternalIndex, $maternalIndex], true)) {
                        continue;
                    }

                    $paternalMatches = $this->startsWithInitial($paternal, $curp[0]);
                    $maternalMatches = $this->startsWithInitial($maternal, $curp[2]);
                    $givenMatches = $this->givenNameMatchesInitial($givenName, $curp[3]);
                    $matchScore = ($paternalMatches ? 3 : 0)
                        + ($maternalMatches ? 2 : 0)
                        + ($givenMatches ? 3 : 0);

                    if ($matchScore === 8) {
                        return [
                            'paternal_last_name' => $this->field($paternal, $confidence + 0.08),
                            'maternal_last_name' => $this->field($maternal, $confidence + 0.08),
                            'first_name' => $this->field($givenName, $confidence + 0.08),
                        ];
                    }

                    if ($matchScore <= $bestPartialScore) {
                        continue;
                    }

                    $partial = [];
                    if ($paternalMatches) {
                        $partial['paternal_last_name'] = $this->field($paternal, $confidence - 0.08);
                    }

                    if ($maternalMatches) {
                        $partial['maternal_last_name'] = $this->field($maternal, $confidence - 0.08);
                    }

                    if ($givenMatches) {
                        $partial['first_name'] = $this->field($givenName, $confidence - 0.08);
                    }

                    $bestPartial = $partial;
                    $bestPartialScore = $matchScore;
                }
            }
        }

        return $bestPartialScore >= 3 ? $bestPartial : [];
    }

    private function startsWithInitial(string $value, string $initial): bool
    {
        return str_starts_with($value, $initial);
    }

    private function givenNameMatchesInitial(string $value, string $initial): bool
    {
        $tokens = preg_split('/\s+/', $value) ?: [];

        return collect($tokens)->contains(
            fn (string $token): bool => $this->startsWithInitial($token, $initial),
        );
    }

    private function curpNameScore(array $fields, string $curp): int
    {
        $checks = [
            'paternal_last_name' => [$curp[0], 3, false],
            'maternal_last_name' => [$curp[2], 2, false],
            'first_name' => [$curp[3], 3, true],
        ];
        $score = 0;

        foreach ($checks as $field => [$initial, $weight, $givenName]) {
            $value = $fields[$field]['value'] ?? null;
            if (! is_string($value) || $value === '') {
                continue;
            }

            $matches = $givenName
                ? $this->givenNameMatchesInitial($value, $initial)
                : $this->startsWithInitial($value, $initial);
            $score += $matches ? $weight : -$weight;
        }

        return $score;
    }

    /**
     * Recover the name block when OCR misses the small NOMBRE label. On current
     * INE layouts, the name is immediately before the DOMICILIO block.
     */
    private function nameLinesBeforeAddress(array $lines): array
    {
        foreach ($lines as $index => $line) {
            if (! $this->containsLabel($line, 'DOMICILIO')) {
                continue;
            }

            $candidates = array_slice($lines, max(0, $index - 6), min(6, $index));
            $candidates = array_values(array_filter(
                $candidates,
                fn (string $candidate): bool => preg_match('/\d/', $candidate) !== 1
                    && ! $this->isNameNoise($this->cleanPersonName($candidate)),
            ));

            return array_slice($candidates, -3);
        }

        return [];
    }

    private function isNameNoise(string $line): bool
    {
        return $line === ''
            || preg_match('/^(?:INSTITUTO(?: NACIONAL)?(?: ELECTORAL)?|NACIONAL ELECTORAL|CREDENCIAL(?: PARA VOTAR)?|PARA VOTAR|ESTADOS UNIDOS MEXICANOS|MEXICO|NOMBRE|SEXO(?: [HM])?|GENERO(?: [HM])?|NERO(?: [HM])?|HOMBRE|MUJER|VIGENCIA)$/', $line) === 1
            || $this->containsLabel($line, 'FECHA DE NACIMIENTO')
            || $this->containsLabel($line, 'CLAVE DE ELECTOR')
            || $this->containsLabel($line, 'CURP');
    }

    private function extractCurp(array $lines): ?string
    {
        foreach ($lines as $line) {
            $compact = preg_replace('/[^A-Z0-9]/', '', $line) ?? '';
            $compact = preg_replace('/^C[U0]RP/', '', $compact) ?? $compact;

            if (strlen($compact) < 18) {
                continue;
            }

            for ($offset = 0; $offset <= strlen($compact) - 18; $offset++) {
                $candidate = $this->normalizeCurpCandidate(substr($compact, $offset, 18));
                if ($this->validCurpCandidate($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    private function normalizeCurpCandidate(string $candidate): string
    {
        $letterMap = ['0' => 'O', '1' => 'I', '2' => 'Z', '4' => 'A', '5' => 'S', '6' => 'G', '8' => 'B'];
        $numberMap = ['O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1', 'Z' => '2', 'S' => '5', 'G' => '6', 'B' => '8'];
        $characters = str_split($candidate);

        foreach ($characters as $index => $character) {
            if (in_array($index, [4, 5, 6, 7, 8, 9, 17], true)) {
                $characters[$index] = $numberMap[$character] ?? $character;
            } elseif (in_array($index, [0, 1, 2, 3, 10, 11, 12, 13, 14, 15], true)) {
                $characters[$index] = $letterMap[$character] ?? $character;
            }
        }

        $twoDigitYear = (int) implode('', array_slice($characters, 4, 2));
        $currentTwoDigitYear = (int) now()->format('y');
        if ($twoDigitYear > $currentTwoDigitYear) {
            $characters[16] = $numberMap[$characters[16]] ?? $characters[16];
        }

        return implode('', $characters);
    }

    private function validCurpCandidate(string $candidate): bool
    {
        if (preg_match('/^[A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d$/', $candidate) !== 1) {
            return false;
        }

        if (! isset(self::CURP_STATE_CODES[substr($candidate, 11, 2)])) {
            return false;
        }

        return $this->birthDateFromCurp($candidate) !== null
            && $this->hasValidCurpCheckDigit($candidate);
    }

    private function hasValidCurpCheckDigit(string $curp): bool
    {
        $dictionary = '0123456789ABCDEFGHIJKLMNÑOPQRSTUVWXYZ';
        $sum = 0;

        for ($index = 0; $index < 17; $index++) {
            $value = mb_strpos($dictionary, $curp[$index]);
            if ($value === false) {
                return false;
            }

            $sum += $value * (18 - $index);
        }

        return (int) $curp[17] === (10 - ($sum % 10)) % 10;
    }

    private function containsLabel(string $line, string $label): bool
    {
        return preg_match($this->labelPattern($label), $line) === 1;
    }

    private function contentAfterLabel(string $line, string $label): string
    {
        if (preg_match($this->labelPattern($label), $line, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return '';
        }

        $matchedText = $match[0][0];
        $offset = $match[0][1] + strlen($matchedText);

        return trim(substr($line, $offset), ' :.-');
    }

    private function labelPattern(string $label): string
    {
        $characters = preg_split('//u', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $alternatives = [
            'A' => '[A4]', 'B' => '[B8]', 'G' => '[G6]', 'I' => '[I1L]',
            'L' => '[L1I]', 'O' => '[O0]', 'S' => '[S5]', 'Z' => '[Z2]',
            ' ' => '[\\s.:_\\-]*',
        ];

        $pattern = implode('', array_map(
            fn (string $character): string => $alternatives[$character] ?? preg_quote($character, '/'),
            $characters,
        ));

        return '/'.$pattern.'/u';
    }

    private function cleanPersonName(string $line): string
    {
        $line = strtr($line, ['0' => 'O', '1' => 'I', '2' => 'Z', '4' => 'A', '5' => 'S', '6' => 'G', '8' => 'B']);
        $line = preg_replace('/[^A-ZÑ\-\' ]/u', ' ', $line) ?? $line;

        return trim(preg_replace('/\s+/', ' ', $line) ?? $line);
    }

    private function cleanAddressLine(string $line): string
    {
        return trim(preg_replace('/\s+/', ' ', $line) ?? $line, ' ,.-');
    }

    private function birthDateFromCurp(string $curp): ?string
    {
        $date = substr($curp, 4, 6);
        $century = ctype_digit($curp[16]) ? '19' : '20';

        try {
            $birthDate = CarbonImmutable::createFromFormat('!Ymd', $century.$date);
            if ($birthDate->format('Ymd') !== $century.$date || $birthDate->isFuture()) {
                return null;
            }

            return $birthDate->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function field(string $value, float $confidence): array
    {
        return [
            'value' => trim($value),
            'confidence' => $confidence,
            'source' => 'ine_ocr',
        ];
    }

    private function warnings(array $fields): array
    {
        $warnings = [];

        if (! isset($fields['curp'])) {
            $warnings[] = 'No se pudo leer una CURP completa. Captúrala manualmente.';
        }

        if (! isset($fields['first_name'])) {
            $warnings[] = 'El nombre no se distinguió con suficiente claridad.';
        }

        $warnings[] = 'Confirma cada dato sugerido contra la credencial antes de enviar.';

        return $warnings;
    }
}
