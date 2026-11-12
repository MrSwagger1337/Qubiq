<?php

declare(strict_types=1);

namespace App\Services\QR;

final readonly class ProcessedLogo
{
    public function __construct(
        public string $pngBinary,
        public int $width,
        public int $height,
        public string $originalMime
    ) {
    }

    public function dataUri(): string
    {
        return 'data:image/png;base64,' . base64_encode($this->pngBinary);
    }
}
