<?php

declare(strict_types=1);

namespace App\Support;

final class Translator
{
    /**
     * @var array<string, mixed>
     */
    private array $messages;

    public function __construct(
        private readonly string $langPath,
        private readonly string $locale = 'en'
    ) {
        $safeLocale = preg_replace('/[^a-zA-Z0-9_-]/', '', $this->locale) ?: 'en';
        $file = rtrim($this->langPath, '/\\') . DIRECTORY_SEPARATOR . $safeLocale . '.php';

        if (!is_file($file)) {
            $file = rtrim($this->langPath, '/\\') . DIRECTORY_SEPARATOR . 'en.php';
        }

        /** @var array<string, mixed> $loaded */
        $loaded = is_file($file) ? require $file : [];
        $this->messages = $loaded;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * @param array<string, string|int|float> $replacements
     */
    public function get(string $key, array $replacements = []): string
    {
        $segments = explode('.', $key);
        $current = $this->messages;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $key;
            }
            $current = $current[$segment];
        }

        if (!is_string($current)) {
            return $key;
        }

        foreach ($replacements as $placeholder => $value) {
            $current = str_replace(':' . $placeholder, (string) $value, $current);
        }

        return $current;
    }

    /**
     * @return array<string, mixed>
     */
    public function catalog(): array
    {
        return $this->messages;
    }
}
