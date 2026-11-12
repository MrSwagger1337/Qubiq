<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class View
{
    public function __construct(
        private readonly string $viewsPath
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $clean = str_replace(['..', '\\'], ['', '/'], $template);
        $file = rtrim($this->viewsPath, '/\\') . '/' . ltrim($clean, '/') . '.php';

        if (!is_file($file)) {
            throw new RuntimeException(sprintf('View template "%s" not found.', $template));
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
