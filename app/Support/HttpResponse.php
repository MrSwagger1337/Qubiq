<?php

declare(strict_types=1);

namespace App\Support;

final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        private readonly string $body,
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public static function json(array $payload, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            $encoded = '{"ok":false,"error":{"code":"encoding_error","message":"Failed to encode JSON response."}}';
            $status = 500;
        }

        return new self($encoded, $status, array_merge([
            'Content-Type' => 'application/json; charset=utf-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ], $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function html(string $html, int $status = 200, array $headers = []): self
    {
        return new self($html, $status, array_merge([
            'Content-Type' => 'text/html; charset=utf-8',
        ], $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function download(
        string $binaryContent,
        string $mimeType,
        string $filename,
        array $headers = []
    ): self {
        $safeFilename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'qubiq-qr';
        $safeFilename = trim($safeFilename, '.-');
        if ($safeFilename === '') {
            $safeFilename = 'qubiq-qr';
        }

        return new self($binaryContent, 200, array_merge([
            'Content-Type' => $mimeType,
            'Content-Length' => (string) strlen($binaryContent),
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'Cache-Control' => 'private, no-store, max-age=0',
        ], $headers));
    }

    /**
     * @param array<string, string> $headers
     */
    public static function redirect(string $url, int $status = 302, array $headers = []): self
    {
        return new self('', $status, array_merge([
            'Location' => $url,
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ], $headers));
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->body, $this->status, $headers);
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        echo $this->body;
    }
}
