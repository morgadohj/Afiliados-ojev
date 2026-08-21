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

    private function preprocess(UploadedFile $file, bool $highContrast = false, int $rotation = 0): ?string
    {
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
        $outputPath = $temporaryBase.($highContrast ? '-contrast' : '').'-'.$rotation.'.png';
        $arguments = [
            $binary,
            $file->getRealPath().'[0]',
            '-auto-orient',
            '-strip',
            '-colorspace',
            'Gray',
            '-resize',
            $highContrast ? '3000x3000' : '2400x2400',
        ];

        if ($rotation !== 0) {
            array_push($arguments, '-background', 'white', '-rotate', (string) $rotation);
        }

        if ($highContrast) {
            array_push(
                $arguments,
                '-normalize',
                '-contrast-stretch',
                '4%x4%',
                '-adaptive-sharpen',
                '0x2',
            );
        } else {
            array_push(
                $arguments,
                '-contrast-stretch',
                '1%x1%',
                '-sharpen',
                '0x1',
            );
        }

        array_push(
            $arguments,
            '-deskew',
            '40%',
            '-bordercolor',
            'white',
            '-border',
            '20',
            $outputPath,
        );

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
