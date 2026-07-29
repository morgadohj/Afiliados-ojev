<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateDocumentStorage
{
    public function storeEncrypted(UploadedFile $file, string $directory): string
    {
        $contents = file_get_contents($file->getRealPath());

        if ($contents === false) {
            throw new \RuntimeException('No fue posible leer el documento cargado.');
        }

        $path = sprintf(
            '%s/%s.%s.enc',
            trim($directory, '/'),
            Str::uuid(),
            strtolower($file->getClientOriginalExtension() ?: 'bin'),
        );

        Storage::disk('local')->put($path, Crypt::encryptString(base64_encode($contents)));

        return $path;
    }
}
