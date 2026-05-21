<?php

namespace App\Service;

final class CakePreviewStorage
{
    private const MAX_BYTES = 512000;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function storeFromDataUrl(?string $dataUrl): ?string
    {
        if ($dataUrl === null || $dataUrl === '') {
            return null;
        }

        if (!preg_match('#^data:image/(png|jpeg);base64,(.+)$#', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);
        if ($binary === false || strlen($binary) > self::MAX_BYTES) {
            return null;
        }

        $extension = $matches[1] === 'jpeg' ? 'jpg' : 'png';
        $directory = $this->projectDir.'/public/uploads/cake-previews';

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        $filename = 'cake-preview-'.bin2hex(random_bytes(8)).'.'.$extension;
        $path = $directory.'/'.$filename;

        if (file_put_contents($path, $binary) === false) {
            return null;
        }

        return $filename;
    }
}
