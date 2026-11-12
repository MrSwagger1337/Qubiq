<?php

declare(strict_types=1);

namespace Tests\Feature;

use chillerlan\QRCode\QRCode;
use DOMDocument;
use Tests\TestCase;

final class ExportFormatsAndApiTest extends TestCase
{
    public function testWebPagesAndHealthAndOpenApiEndpointsRespondCleanly(): void
    {
        $home = $this->request('GET', '/');
        self::assertSame(200, $home->status());
        self::assertStringContainsString('Qubiq', $home->body());
        self::assertSame('nosniff', $home->headers()['X-Content-Type-Options'] ?? '');
        self::assertSame('no-referrer', $home->headers()['Referrer-Policy'] ?? '');
        self::assertStringContainsString("script-src 'self'", $home->headers()['Content-Security-Policy'] ?? '');

        $docs = $this->request('GET', '/docs');
        self::assertSame(200, $docs->status());

        $apiDocs = $this->request('GET', '/api-docs');
        self::assertSame(200, $apiDocs->status());

        $health = $this->request('GET', '/api/v1/health');
        self::assertSame(200, $health->status());
        $healthData = json_decode($health->body(), true);
        self::assertIsArray($healthData);
        self::assertTrue($healthData['ok']);
        self::assertSame('healthy', $healthData['data']['status']);

        $openApi = $this->request('GET', '/openapi.json');
        self::assertSame(200, $openApi->status());
        $spec = json_decode($openApi->body(), true);
        self::assertIsArray($spec);
        self::assertSame('3.1.0', $spec['openapi']);
    }

    public function testAllSixExportFormatsProduceAuthenticValidFiles(): void
    {
        $basePayload = [
            'type' => 'url',
            'data' => ['url' => 'https://qubiq.test/exports'],
            'design' => [
                'ecc' => 'H',
                'size' => 512,
                'foreground_mode' => 'linear',
                'foreground_color' => '#0f172a',
                'foreground_gradient_end' => '#059669',
                'background_mode' => 'transparent',
                'module_shape' => 'rounded',
                'finder_outer_shape' => 'rounded',
                'finder_inner_shape' => 'circle',
                'logo_data' => $this->createSamplePngDataUri(),
                'logo_scale' => 0.16,
            ],
            'filename' => 'custom-brand-qr',
        ];

        // 1. PNG
        $pngRes = $this->request('POST', '/api/v1/qr/export', array_merge($basePayload, ['format' => 'png']));
        self::assertSame(200, $pngRes->status());
        self::assertSame('image/png', $pngRes->headers()['Content-Type']);
        self::assertStringContainsString('filename="custom-brand-qr.png"', $pngRes->headers()['Content-Disposition']);
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $pngRes->body());
        $pngSize = getimagesizefromstring($pngRes->body());
        self::assertIsArray($pngSize);
        self::assertSame(512, $pngSize[0]);
        self::assertSame(512, $pngSize[1]);

        // 2. SVG
        $svgRes = $this->request('POST', '/api/v1/qr/export', array_merge($basePayload, ['format' => 'svg']));
        self::assertSame(200, $svgRes->status());
        self::assertSame('image/svg+xml', $svgRes->headers()['Content-Type']);
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($svgRes->body()));
        self::assertNotNull($dom->documentElement);
        self::assertSame('svg', $dom->documentElement->tagName);
        self::assertSame('512', $dom->documentElement->getAttribute('width'));

        // 3. PDF
        $pdfRes = $this->request('POST', '/api/v1/qr/export', array_merge($basePayload, ['format' => 'pdf']));
        self::assertSame(200, $pdfRes->status());
        self::assertSame('application/pdf', $pdfRes->headers()['Content-Type']);
        self::assertStringStartsWith('%PDF-1.', $pdfRes->body());
        self::assertStringContainsString('%%EOF', $pdfRes->body());

        // 4. EPS
        $epsRes = $this->request('POST', '/api/v1/qr/export', array_merge($basePayload, ['format' => 'eps']));
        self::assertSame(200, $epsRes->status());
        self::assertSame('application/postscript', $epsRes->headers()['Content-Type']);
        self::assertStringStartsWith('%!PS-Adobe-3.0 EPSF-3.0', $epsRes->body());
        self::assertStringContainsString('%%BoundingBox: 0 0 512 512', $epsRes->body());
        self::assertStringContainsString('%%EOF', $epsRes->body());

        // 5. WebP
        $webpRes = $this->request('POST', '/api/v1/qr/export', array_merge($basePayload, ['format' => 'webp']));
        self::assertSame(200, $webpRes->status());
        self::assertSame('image/webp', $webpRes->headers()['Content-Type']);
        self::assertSame('RIFF', substr($webpRes->body(), 0, 4));
        self::assertSame('WEBP', substr($webpRes->body(), 8, 4));

        // 6. JPEG (opaque white background fallback verified via ZXing scan)
        $jpgRes = $this->request('POST', '/api/v1/qr/export', array_merge($basePayload, ['format' => 'jpeg']));
        self::assertSame(200, $jpgRes->status());
        self::assertSame('image/jpeg', $jpgRes->headers()['Content-Type']);
        self::assertStringContainsString('filename="custom-brand-qr.jpg"', $jpgRes->headers()['Content-Disposition']);
        self::assertStringStartsWith("\xFF\xD8\xFF", $jpgRes->body());
        $decodedJpg = (new QRCode())->readFromBlob($jpgRes->body());
        self::assertSame('https://qubiq.test/exports', $decodedJpg->data);
    }

    public function testApiPreviewValidateAndGenerateJsonEndpoints(): void
    {
        $valRes = $this->request('POST', '/api/v1/qr/validate', [
            'type' => 'wifi',
            'data' => ['ssid' => 'LabNet', 'encryption' => 'WPA', 'password' => 'StrongPass123'],
        ]);
        self::assertSame(200, $valRes->status());
        $valJson = json_decode($valRes->body(), true);
        self::assertIsArray($valJson);
        self::assertTrue($valJson['data']['valid']);

        $genRes = $this->request('POST', '/api/v1/qr/generate', [
            'type' => 'url',
            'data' => ['url' => 'https://example.org'],
            'format' => 'svg',
        ]);
        self::assertSame(200, $genRes->status());
        $genJson = json_decode($genRes->body(), true);
        self::assertIsArray($genJson);
        self::assertStringStartsWith('data:image/svg+xml;base64,', $genJson['data']['data_uri']);
    }
}
