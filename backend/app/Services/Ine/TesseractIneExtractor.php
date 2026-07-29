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
        $texts = [$this->recognize($front)];

        if ($back !== null) {
            $texts[] = $this->recognize($back);
        }

        return $this->parser->parse(implode("\n", $texts));
    }

    private function recognize(UploadedFile $file): string
    {
        $process = new Process([
            $this->binary(),
            $file->getRealPath(),
            'stdout',
            '-l',
            'spa+eng',
            '--psm',
            '6',
        ]);
        $process->setTimeout(45);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new \RuntimeException('El motor OCR no pudo procesar esta imagen.');
        }

        return $process->getOutput();
    }

    private function binary(): string
    {
        return (string) config('services.ine_ocr.tesseract_path', 'tesseract');
    }
}
