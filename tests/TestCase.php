<?php

declare(strict_types=1);

namespace Tests;

use App\Application;
use App\Support\HttpRequest;
use App\Support\HttpResponse;
use GdImage;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $container;

    protected Application $app;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_DEBUG'] = 'true';
        $_ENV['APP_URL'] = 'https://qubiq.test';
        $_ENV['DB_DATABASE'] = ':memory:';
        $_ENV['LOG_CHANNEL'] = 'null';
        $_ENV['RATE_LIMIT_ENABLED'] = 'false';

        /** @var array<string, mixed> $boot */
        $boot = require dirname(__DIR__) . '/bootstrap/app.php';
        $this->container = $boot;
        /** @var Application $app */
        $app = $boot['app'];
        $this->app = $app;
    }

    /**
     * @param array<string, mixed>|null $jsonBody
     * @param array<string, string> $headers
     * @param array<string, array<string, mixed>> $files
     */
    protected function request(
        string $method,
        string $path,
        ?array $jsonBody = null,
        array $headers = [],
        array $files = [],
        string $rawBodyOverride = ''
    ): HttpResponse {
        $raw = $rawBodyOverride;
        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $raw = is_string($encoded) ? $encoded : '';
            $headers['content-type'] = $headers['content-type'] ?? 'application/json';
        }

        $normalizedHeaders = [];
        foreach ($headers as $k => $v) {
            $normalizedHeaders[strtolower($k)] = $v;
        }

        $req = new HttpRequest(
            method: strtoupper($method),
            path: $path,
            basePath: '',
            query: [],
            post: [],
            headers: $normalizedHeaders,
            files: $files,
            server: [
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTPS' => 'on',
                'HTTP_HOST' => 'qubiq.test',
            ],
            rawBody: $raw
        );

        return $this->app->handle($req);
    }

    protected function createSamplePngDataUri(int $w = 64, int $h = 64): string
    {
        $safeW = max(1, $w);
        $safeH = max(1, $h);
        $img = imagecreatetruecolor($safeW, $safeH);
        self::assertInstanceOf(GdImage::class, $img);
        $emerald = (int) imagecolorallocate($img, 16, 185, 129);
        $white = (int) imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, 0, 0, $safeW, $safeH, $emerald);
        imagefilledellipse($img, (int) ($safeW / 2), (int) ($safeH / 2), (int) ($safeW / 2), (int) ($safeH / 2), $white);

        ob_start();
        imagepng($img);
        $bin = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($bin);
    }
}
