<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;
use Throwable;

final class Logger
{
    private const LEVELS = [
        'debug' => 10,
        'info' => 20,
        'warning' => 30,
        'error' => 40,
        'critical' => 50,
    ];

    public function __construct(
        private readonly string $logFile,
        private readonly string $minLevel = 'info',
        private readonly bool $enabled = true
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function exception(Throwable $exception, array $context = []): void
    {
        $this->log('error', $exception->getMessage(), array_merge($context, [
            'exception' => $exception::class,
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
        ]));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        $minWeight = self::LEVELS[strtolower($this->minLevel)] ?? 20;
        $weight = self::LEVELS[strtolower($level)] ?? 20;
        if ($weight < $minWeight) {
            return;
        }

        $sanitizedContext = $this->redactSensitive($context);
        $entry = [
            'timestamp' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'level' => strtolower($level),
            'message' => $message,
            'context' => $sanitizedContext,
        ];

        $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            return;
        }

        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        @file_put_contents($this->logFile, $encoded . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function redactSensitive(array $context): array
    {
        $redacted = [];
        $sensitiveKeys = ['token', 'secret', 'password', 'authorization', 'owner_token', 'payload', 'data'];

        foreach ($context as $key => $value) {
            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, $sensitiveKeys, true)) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }
            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;
                $redacted[$key] = $this->redactSensitive($nested);
                continue;
            }
            $redacted[$key] = $value;
        }

        return $redacted;
    }
}
