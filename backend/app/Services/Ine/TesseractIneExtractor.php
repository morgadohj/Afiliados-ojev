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
        $preparedImage = $this->preprocess($front);
        $imagePath = $preparedImage ?? $front->getRealPath();

        try {
            $results = [
                $this->parser->parse($this->recognize($imagePath, 11)),
            ];

            if ($this->parser->score($results[0]['fields']) < 14) {
                $results[] = $this->parser->parse($this->recognize($imagePath, 6));
            }

            return $this->parser->merge($results);
        } finally {
            if ($preparedImage !== null && is_file($preparedImage)) {
                @unlink($preparedImage);
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

    private function preprocess(UploadedFile $file): ?string
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
        $outputPath = $temporaryBase.'.png';
        $process = new Process([
            $binary,
            $file->getRealPath().'[0]',
            '-auto-orient',
            '-strip',
            '-colorspace',
            'Gray',
            '-resize',
            '2400x2400',
            '-contrast-stretch',
            '1%x1%',
            '-sharpen',
            '0x1',
            '-deskew',
            '40%',
            '-bordercolor',
            'white',
            '-border',
            '20',
            $outputPath,
        ]);
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
