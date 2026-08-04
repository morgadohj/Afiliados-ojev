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
        $nameLines = array_values(array_filter(array_map($this->cleanPersonName(...), $nameLines)));
        if (count($nameLines) >= 3) {
            $fields['paternal_last_name'] = $this->field($nameLines[0], 0.76);
            $fields['maternal_last_name'] = $this->field($nameLines[1], 0.76);
            $fields['first_name'] = $this->field(implode(' ', array_slice($nameLines, 2)), 0.76);
        } elseif (count($nameLines) === 2) {
            $fields['paternal_last_name'] = $this->field($nameLines[0], 0.58);
            $fields['first_name'] = $this->field($nameLines[1], 0.58);
        }

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

        return array_sum(array_map(
            fn (string $field): int => isset($fields[$field]) ? $weights[$field] : 0,
            array_keys($weights),
        ));
    }

    public function merge(array $results): array
    {
        usort(
            $results,
            fn (array $left, array $right): int => $this->score($right['fields']) <=> $this->score($left['fields']),
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
                    return array_slice($result, 0, 4);
                }
            }

            if (! $this->isNoise($line)) {
                $result[] = $line;
            }
        }

        return array_slice($result, 0, 4);
    }

    private function isNoise(string $line): bool
    {
        return preg_match('/^(INSTITUTO|NACIONAL|ELECTORAL|CREDENCIAL|PARA VOTAR|MEXICO|SEXO|VIGENCIA)$/', $line) === 1
            || $this->containsLabel($line, 'FECHA DE NACIMIENTO');
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
