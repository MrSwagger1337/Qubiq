<?php

declare(strict_types=1);

namespace App\Support;

final class HttpRequest
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $post
     * @param array<string, string> $headers
     * @param array<string, array<string, mixed>> $files
     * @param array<string, mixed> $server
     */
    public function __construct(
        private readonly string $method,
        private readonly string $path,
        private readonly string $basePath,
        private readonly array $query,
        private readonly array $post,
        private readonly array $headers,
        private readonly array $files,
        private readonly array $server,
        private readonly string $rawBody
    ) {
    }

    public static function capture(int $maxBodyBytes = 3145728): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $rawPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: '/');

        // Support shared hosting subdirectory installations seamlessly (e.g., /qubiq/api/v1/health)
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $rawScriptDir = str_replace('\\', '/', dirname($scriptName));
        $cleanedDir = (string) preg_replace('#/public$#', '', $rawScriptDir);
        $scriptDir = rtrim($cleanedDir, '/.');
        $basePath = ($scriptDir !== '' && $scriptDir !== '/') ? '/' . ltrim($scriptDir, '/') : '';

        if ($basePath !== '' && str_starts_with($rawPath, $basePath)) {
            $rawPath = substr($rawPath, strlen($basePath)) ?: '/';
        }
        $normalizedPath = '/' . ltrim($rawPath, '/');
        if ($normalizedPath !== '/') {
            $normalizedPath = rtrim($normalizedPath, '/');
        }

        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!is_string($value)) {
                continue;
            }
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = $value;
            }
        }

        // Fallback for Apache shared hosting where Authorization header is placed in REDIRECT_HTTP_AUTHORIZATION
        if (!isset($headers['authorization'])) {
            foreach (['REDIRECT_HTTP_AUTHORIZATION', 'PHP_AUTH_DIGEST'] as $altKey) {
                if (isset($_SERVER[$altKey]) && is_string($_SERVER[$altKey]) && $_SERVER[$altKey] !== '') {
                    $headers['authorization'] = $_SERVER[$altKey];
                    break;
                }
            }
            if (!isset($headers['authorization']) && function_exists('apache_request_headers')) {
                $apacheHeaders = apache_request_headers();
                foreach ($apacheHeaders as $k => $v) {
                    if (strtolower((string) $k) === 'authorization' && is_string($v)) {
                        $headers['authorization'] = $v;
                        break;
                    }
                }
            }
        }

        $rawBody = '';
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $stream = fopen('php://input', 'rb');
            if ($stream !== false) {
                $read = stream_get_contents($stream, $maxBodyBytes + 1);
                fclose($stream);
                $rawBody = is_string($read) ? $read : '';
            }
        }

        /** @var array<string, mixed> $query */
        $query = $_GET;
        /** @var array<string, mixed> $post */
        $post = $_POST;
        /** @var array<string, array<string, mixed>> $files */
        $files = $_FILES;
        /** @var array<string, mixed> $server */
        $server = $_SERVER;

        return new self($method, $normalizedPath, $basePath, $query, $post, $headers, $files, $server, $rawBody);
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    public function contentLength(): int
    {
        $headerLen = $this->header('content-length');
        if ($headerLen !== null && ctype_digit($headerLen)) {
            return (int) $headerLen;
        }

        return strlen($this->rawBody);
    }

    public function header(string $name, ?string $default = null): ?string
    {
        $key = strtolower($name);

        return $this->headers[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth === null) {
            return null;
        }

        if (preg_match('/^Bearer\s+([A-Za-z0-9._~+\/-]+=*)$/i', trim($auth), $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (trim($this->rawBody) === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    public function hasValidJsonOrEmpty(): bool
    {
        if (trim($this->rawBody) === '') {
            return true;
        }

        $decoded = json_decode($this->rawBody, true);

        return is_array($decoded) && json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Returns merged input from JSON payload or multipart/form-data POST fields.
     * If `payload` field in multipart contains JSON, decodes and merges it cleanly.
     *
     * @return array<string, mixed>
     */
    public function input(): array
    {
        $contentType = strtolower((string) $this->header('content-type', ''));
        if (str_contains($contentType, 'application/json')) {
            return $this->json();
        }

        if ($this->post !== []) {
            $data = $this->post;
            if (isset($data['config']) && is_string($data['config'])) {
                $decodedConfig = json_decode($data['config'], true);
                if (is_array($decodedConfig)) {
                    unset($data['config']);
                    /** @var array<string, mixed> $merged */
                    $merged = array_merge($decodedConfig, $data);

                    return $merged;
                }
            }

            return $data;
        }

        return $this->json();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(string $key): ?array
    {
        if (!isset($this->files[$key])) {
            return null;
        }

        $error = $this->files[$key]['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return $this->files[$key];
    }

    /**
     * @param list<string> $trustedProxies
     */
    public function clientIp(array $trustedProxies = []): string
    {
        $remoteAddr = (string) ($this->server['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($trustedProxies !== [] && in_array($remoteAddr, $trustedProxies, true)) {
            $forwarded = $this->header('x-forwarded-for');
            if ($forwarded !== null) {
                $parts = array_map('trim', explode(',', $forwarded));
                $candidate = $parts[0];
                if (filter_var($candidate, FILTER_VALIDATE_IP) !== false) {
                    return $candidate;
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) !== false ? $remoteAddr : '127.0.0.1';
    }

    /**
     * @param list<string> $trustedProxies
     */
    public function isHttps(array $trustedProxies = []): bool
    {
        $https = strtolower((string) ($this->server['HTTPS'] ?? ''));
        if ($https !== '' && $https !== 'off') {
            return true;
        }
        if ((int) ($this->server['SERVER_PORT'] ?? 80) === 443) {
            return true;
        }

        $remoteAddr = (string) ($this->server['REMOTE_ADDR'] ?? '');
        if ($trustedProxies !== [] && in_array($remoteAddr, $trustedProxies, true)) {
            return strtolower((string) $this->header('x-forwarded-proto', '')) === 'https';
        }

        return false;
    }

    /**
     * @param list<string> $trustedProxies
     */
    public function detectedBaseUrl(string $configuredAppUrl, array $trustedProxies = []): string
    {
        $host = $this->header('host');
        if ($host !== null && preg_match('/^[A-Za-z0-9.\-:]+$/', $host) === 1) {
            $scheme = $this->isHttps($trustedProxies) ? 'https' : 'http';

            return $scheme . '://' . $host . $this->basePath;
        }

        return rtrim($configuredAppUrl, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function server(): array
    {
        return $this->server;
    }
}
