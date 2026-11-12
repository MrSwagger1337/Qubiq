<?php

declare(strict_types=1);

namespace App\Services\Export;

final readonly class ExportedFile
{
    public function __construct(
        public string $content,
        public string $format,
        public string $mimeType,
        public string $extension,
        public string $filename
    ) {
    }
}
