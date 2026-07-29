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

        if (preg_match('/\b([A-Z]{4}\d{6}[HM][A-Z]{5}[A-Z0-9]\d)\b/', $normalized, $match)) {
            $curp = $match[1];
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
            $fields['address_street'] = $this->field($addressLines[0], 0.6);

            if (isset($addressLines[1])) {
                $neighborhood = preg_replace('/\bC\.?\s*P\.?\s*\d{5}\b/', '', $addressLines[1]);
                $fields['neighborhood'] = $this->field(trim((string) $neighborhood), 0.45);
            }

            if (isset($addressLines[2])) {
                $fields['locality'] = $this->field($addressLines[2], 0.42);
            }
        }

        return [
            'fields' => $fields,
            'raw_text' => $rawText,
            'warnings' => $this->warnings($fields),
        ];
    }

    private function normalize(string $text): string
    {
        $text = mb_strtoupper($text, 'UTF-8');
        $text = strtr($text, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);

        return preg_replace('/[^\p{L}\p{N}\/.:\-\n ]+/u', ' ', $text) ?? $text;
    }

    private function linesBetween(array $lines, string $start, array $endLabels): array
    {
        $capturing = false;
        $result = [];

        foreach ($lines as $line) {
            if (! $capturing && str_contains($line, $start)) {
                $capturing = true;
                $inline = trim(str_replace($start, '', $line), ' :.-');
                if ($inline !== '') {
                    $result[] = $inline;
                }

                continue;
            }

            if (! $capturing) {
                continue;
            }

            foreach ($endLabels as $endLabel) {
                if (str_contains($line, $endLabel)) {
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
        return preg_match('/^(INSTITUTO|NACIONAL|ELECTORAL|CREDENCIAL|PARA VOTAR|MEXICO)$/', $line) === 1;
    }

    private function birthDateFromCurp(string $curp): ?string
    {
        $date = substr($curp, 4, 6);
        $century = ctype_digit($curp[16]) ? '19' : '20';

        try {
            return CarbonImmutable::createFromFormat('Ymd', $century.$date)->format('Y-m-d');
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
