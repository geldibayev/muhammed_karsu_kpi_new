<?php

namespace App\Services;

use App\Models\Datum;
use Gemini\Enums\MimeType;
use Illuminate\Support\Facades\Storage;

class GeminiFileMimeTypeResolver
{
    public function handle(Datum $datum): ?MimeType
    {
        $storagePath = $datum->storagePath();

        if ($storagePath === null) {
            return null;
        }

        $disk = Storage::disk($datum->storageDisk());

        if (! $disk->exists($storagePath)) {
            return null;
        }

        $detectedMime = $disk->mimeType($storagePath);

        if (! is_string($detectedMime)) {
            return null;
        }

        return match (mb_strtolower(trim($detectedMime), 'UTF-8')) {
            'image/jpeg', 'image/jpg', 'image/pjpeg' => MimeType::IMAGE_JPEG,
            'image/png', 'image/x-png' => MimeType::IMAGE_PNG,
            'application/pdf', 'application/x-pdf' => MimeType::APPLICATION_PDF,
            default => null,
        };
    }
}
