<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Exceptions\RateLimitException;
use App\Support\Config;
use App\Support\Translator;

final class RateLimiter
{
    public function __construct(
        private readonly Config $config,
        private readonly Translator $translator
    ) {
    }

    public function enforce(string $bucket, string $clientIp, int $maxAttempts, int $windowSeconds = 60): void
    {
        if (!$this->config->bool('security.rate_limit_enabled', true) || $maxAttempts <= 0) {
            return;
        }

        $cacheDir = rtrim($this->config->string('paths.cache'), '/\\') . DIRECTORY_SEPARATOR . 'ratelimit';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0775, true);
        }

        $hmacKey = $this->config->string('app.key', 'qubiq-rate-key');
        $hashedClient = hash_hmac('sha256', $bucket . '|' . $clientIp, $hmacKey);
        $file = $cacheDir . DIRECTORY_SEPARATOR . substr($hashedClient, 0, 32) . '.json';

        $now = time();
        $handle = @fopen($file, 'c+');
        if ($handle === false) {
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                return;
            }

            $raw = stream_get_contents($handle);
            $state = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;

            $resetAt = $now + $windowSeconds;
            $count = 0;

            if (is_array($state) && isset($state['reset_at'], $state['count']) && (int) $state['reset_at'] > $now) {
                $resetAt = (int) $state['reset_at'];
                $count = (int) $state['count'];
            }

            if ($count >= $maxAttempts) {
                $retryAfter = max(1, $resetAt - $now);
                throw new RateLimitException(
                    $this->translator->get('errors.rate_limited', ['seconds' => $retryAfter]),
                    $retryAfter
                );
            }

            $newState = json_encode([
                'count' => $count + 1,
                'reset_at' => $resetAt,
            ]);

            if (is_string($newState)) {
                ftruncate($handle, 0);
                rewind($handle);
                fwrite($handle, $newState);
                fflush($handle);
            }
        } finally {
            @flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
