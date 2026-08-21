<?php

namespace App\Services\Ine;

use Illuminate\Http\UploadedFile;
use Symfony\Component\Process\Process;

class TesseractIneExtractor
{
    public function __construct(private readonly IneTextParser $parser) {}

    public function available(): bool
    {
        $process = new Process([$this->binary(), '--version']);
        $process->setTimeout(5);
        $process->run();

        return $process->isSuccessful();
    }

    public function extract(UploadedFile $front, ?UploadedFile $back = null): array
    {
        $temporaryImages = [];
        $preparedImage = $this->preprocess($front);
        if ($preparedImage !== null) {
            $temporaryImages[] = $preparedImage;
        }

        $imagePath = $preparedImage ?? $front->getRealPath();

        try {
            $results = [
                $this->parser->parse($this->recognize($imagePath, 11)),
            ];

            if ($this->parser->score($results[0]['fields']) < 14) {
                $results[] = $this->parser->parse($this->recognize($imagePath, 6));
            }

            $combined = $this->parser->merge($results);
            if (! isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
                foreach ([90, 270] as $rotation) {
                    $rotatedImage = $this->preprocess($front, rotation: $rotation);
                    if ($rotatedImage === null) {
                        continue;
                    }

                    $temporaryImages[] = $rotatedImage;
                    $results[] = $this->parser->parse($this->recognize($rotatedImage, 11));
                    $combined = $this->parser->merge($results);

                    if (isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
                        break;
                    }
                }
            }

            if (! isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
                foreach ([0, 90, 270] as $rotation) {
                    $detailImage = $this->preprocess($front, rotation: $rotation, preserveDetail: true);
                    if ($detailImage === null) {
                        continue;
                    }

                    $temporaryImages[] = $detailImage;
                    $this->appendFocusedNameReading($results, $detailImage, $combined);
                    $combined = $this->parser->merge($results);

                    if (isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
                        break;
                    }
                }
            }

            if (! isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
                foreach ([0, 90, 270] as $rotation) {
                    $highContrastImage = $this->preprocess($front, highContrast: true, rotation: $rotation);
                    if ($highContrastImage === null) {
                        continue;
                    }

                    $temporaryImages[] = $highContrastImage;
                    $results[] = $this->parser->parse($this->recognize($highContrastImage, 11));
                    $combined = $this->parser->merge($results);

                    if (isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
                        break;
                    }
                }
            }

            return $this->parser->merge($results);
        } finally {
            foreach ($temporaryImages as $temporaryImage) {
                if (is_file($temporaryImage)) {
                    @unlink($temporaryImage);
                }
            }
        }
    }

    private function appendFocusedNameReading(array &$results, string $imagePath, array $combined): void
    {
        if (isset($combined['fields']['first_name'], $combined['fields']['paternal_last_name'])) {
            return;
        }

        $curp = $combined['fields']['curp']['value'] ?? null;
        if (! is_string($curp) || strlen($curp) !== 18) {
            return;
        }

        $focusedText = $this->recognizeFocusedName($imagePath);
        if ($focusedText !== null) {
            // The CURP initials validate and order the three photographed
            // lines, preventing labels such as SEXO from becoming a surname.
            $candidate = $this->parser->parse($focusedText."\nCURP {$curp}");
            if (isset($candidate['fields']['first_name'])
                || isset($candidate['fields']['paternal_last_name'])) {
                $results[] = $candidate;
            }
        }
    }

    /**
     * Locate the NOMBRE label with Tesseract coordinates and reread each of
     * the three small identity lines independently. Faces and security marks
     * otherwise cause the full-card layout modes to omit these lines.
     */
    private function recognizeFocusedName(string $imagePath): ?string
    {
        $layout = $this->recognizeLayout($imagePath);
        if ($layout === null) {
            return null;
        }

        ['page_width' => $pageWidth, 'page_height' => $pageHeight, 'label' => $label] = $layout;
        [$left, $top, $width, $height] = $label;
        $blockX = max(0, $left - (int) round($width * 0.75));
        $blockY = max(0, $top - (int) round($width * 0.8));
        $blockWidth = min(
            $pageWidth - $blockX,
            (int) round($width * 6.1),
        );
        $blockHeight = min($pageHeight - $blockY, (int) round($width * 3.2));

        if ($blockWidth < 60 || $blockHeight < 60) {
            return null;
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'ojev-name-');
        if ($temporaryBase === false) {
            return null;
        }

        @unlink($temporaryBase);
        $blockPath = $temporaryBase.'-block.png';
        $linePaths = [];

        try {
            $blockProcess = new Process([
                (string) config('services.ine_ocr.imagemagick_path', 'convert'),
                $imagePath,
                '-crop',
                "{$blockWidth}x{$blockHeight}+{$blockX}+{$blockY}",
                '+repage',
                '-resize',
                '400%',
                '-normalize',
                '-contrast-stretch',
                '2%x2%',
                '-adaptive-sharpen',
                '0x1',
                $blockPath,
            ]);
            $blockProcess->setTimeout(20);
            $blockProcess->run();

            if (! $blockProcess->isSuccessful() || ! is_file($blockPath)) {
                return null;
            }

            $scale = 4;
            $scaledHeight = $height * $scale;
            $labelTop = ($top - $blockY) * $scale;
            $lineX = max(0, (int) round(($left - $blockX - ($width * 0.28)) * $scale));
            $lineDefinitions = [
                [0.58, 2.3, 1.1],
                [1.28, 2.3, 1.1],
                [2.44, 3.5, 1.2],
            ];
            $readings = [];

            foreach ($lineDefinitions as $index => [$topFactor, $widthFactor, $heightFactor]) {
                $lineTop = max(0, (int) round($labelTop + ($scaledHeight * $topFactor)));
                $lineWidth = min(
                    ($blockWidth * $scale) - $lineX,
                    (int) round($width * $scale * $widthFactor),
                );
                $lineHeight = min(
                    ($blockHeight * $scale) - $lineTop,
                    (int) round($scaledHeight * $heightFactor),
                );

                if ($lineWidth < 40 || $lineHeight < 12) {
                    continue;
                }

                $linePath = $temporaryBase.'-line-'.$index.'.png';
                $linePaths[] = $linePath;
                $lineProcess = new Process([
                    (string) config('services.ine_ocr.imagemagick_path', 'convert'),
                    $blockPath,
                    '-crop',
                    "{$lineWidth}x{$lineHeight}+{$lineX}+{$lineTop}",
                    '+repage',
                    '-colorspace',
                    'Gray',
                    '-contrast-stretch',
                    '1%x1%',
                    '-sharpen',
                    '0x1',
                    '-resize',
                    '200%',
                    '-bordercolor',
                    'white',
                    '-border',
                    '20',
                    $linePath,
                ]);
                $lineProcess->setTimeout(15);
                $lineProcess->run();

                if (! $lineProcess->isSuccessful() || ! is_file($linePath)) {
                    continue;
                }

                $reading = $this->recognizeNameLine($linePath);
                if ($reading !== null) {
                    $readings[] = $reading;
                }
            }

            if ($readings === []) {
                return null;
            }

            return "NOMBRE\n".implode("\n", $readings)."\nDOMICILIO";
        } finally {
            foreach ([$blockPath, ...$linePaths] as $temporaryImage) {
                if (is_file($temporaryImage)) {
                    @unlink($temporaryImage);
                }
            }
        }
    }

    /** @return array{page_width: int, page_height: int, label: array{int, int, int, int}}|null */
    private function recognizeLayout(string $imagePath): ?array
    {
        $process = new Process([
            $this->binary(),
            $imagePath,
            'stdout',
            '-l',
            'spa+eng',
            '--psm',
            '11',
            'tsv',
        ]);
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $pageWidth = 0;
        $pageHeight = 0;
        $label = null;

        foreach (preg_split('/\R/', $process->getOutput()) ?: [] as $row) {
            $columns = explode("\t", $row, 12);
            if (count($columns) < 12 || ! ctype_digit($columns[0])) {
                continue;
            }

            if ((int) $columns[0] === 1) {
                $pageWidth = (int) $columns[8];
                $pageHeight = (int) $columns[9];
            }

            if ((int) $columns[0] !== 5) {
                continue;
            }

            $word = strtoupper(preg_replace('/[^A-Z0-9]/', '', $columns[11] ?? '') ?? '');
            $word = strtr($word, ['0' => 'O', '8' => 'B']);
            if ($word === 'NOMBRE' || levenshtein($word, 'NOMBRE') <= 1) {
                $label = [(int) $columns[6], (int) $columns[7], (int) $columns[8], (int) $columns[9]];
                break;
            }
        }

        if ($pageWidth <= 0 || $pageHeight <= 0 || $label === null) {
            return null;
        }

        return ['page_width' => $pageWidth, 'page_height' => $pageHeight, 'label' => $label];
    }

    private function recognizeNameLine(string $imagePath): ?string
    {
        foreach ([7, 8] as $pageSegmentationMode) {
            $text = trim($this->recognize($imagePath, $pageSegmentationMode));
            $text = mb_strtoupper($text, 'UTF-8');
            $text = trim(preg_replace('/[^A-ZÑ\-\' ]/u', ' ', $text) ?? '');
            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

            if (mb_strlen($text) >= 3 && mb_strlen($text) <= 80) {
                return $text;
            }
        }

        return null;
    }

    private function recognize(string $imagePath, int $pageSegmentationMode): string
    {
        $process = new Process([
            $this->binary(),
            $imagePath,
            'stdout',
            '-l',
            'spa+eng',
            '--psm',
            (string) $pageSegmentationMode,
            '--dpi',
            '300',
            '-c',
            'preserve_interword_spaces=1',
        ]);
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('El motor OCR no pudo procesar esta imagen.');
        }

        return $process->getOutput();
    }

    private function preprocess(
        UploadedFile $file,
        bool $highContrast = false,
        int $rotation = 0,
        bool $preserveDetail = false,
    ): ?string {
        $binary = (string) config('services.ine_ocr.imagemagick_path', 'convert');
        $version = new Process([$binary, '-version']);
        $version->setTimeout(5);
        $version->run();

        if (! $version->isSuccessful()) {
            return null;
        }

        $temporaryBase = tempnam(sys_get_temp_dir(), 'ojev-ine-');
        if ($temporaryBase === false) {
            return null;
        }

        @unlink($temporaryBase);
        $outputPath = $temporaryBase
            .($highContrast ? '-contrast' : '')
            .($preserveDetail ? '-detail' : '')
            .'-'.$rotation.'.png';
        $arguments = [
            $binary,
            $file->getRealPath().'[0]',
            '-auto-orient',
            '-strip',
            '-resize',
            $highContrast ? '3000x3000' : '2400x2400',
        ];

        if ($rotation !== 0) {
            array_push($arguments, '-background', 'white', '-rotate', (string) $rotation);
        }

        if ($preserveDetail) {
            // Keep the original color and fine strokes for locating NOMBRE.
        } elseif ($highContrast) {
            array_push($arguments, '-colorspace', 'Gray');
            array_push(
                $arguments,
                '-normalize',
                '-contrast-stretch',
                '4%x4%',
                '-adaptive-sharpen',
                '0x2',
            );
        } else {
            array_push($arguments, '-colorspace', 'Gray');
            array_push(
                $arguments,
                '-contrast-stretch',
                '1%x1%',
                '-sharpen',
                '0x1',
            );
        }

        if (! $preserveDetail) {
            array_push($arguments, '-deskew', '40%');
        }

        array_push($arguments, '-bordercolor', 'white', '-border', '20', $outputPath);

        $process = new Process($arguments);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful() || ! is_file($outputPath)) {
            @unlink($outputPath);

            return null;
        }

        return $outputPath;
    }

    private function binary(): string
    {
        return (string) config('services.ine_ocr.tesseract_path', 'tesseract');
    }
}
