<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Support\DatabaseManager;
use Tests\TestCase;

final class SecurityAndDynamicQrTest extends TestCase
{
    public function testDynamicQrFullAnonymousLifecycleAndTokenSecurity(): void
    {
        // 1. Create dynamic link
        $createRes = $this->request('POST', '/api/v1/links', [
            'destination_url' => 'https://example.com/initial-offer',
        ]);
        self::assertSame(201, $createRes->status());
        $created = json_decode($createRes->body(), true)['data'];

        $code = (string) $created['short_code'];
        $token = (string) $created['ownership_token'];
        self::assertStringStartsWith('qubiq_own_', $token);
        self::assertStringContainsString('#token=' . $token, (string) $created['management_url']);
        self::assertStringNotContainsString('?token=', (string) $created['management_url']);

        // Verify raw token is NEVER persisted in SQLite — only its SHA-256 hash
        /** @var DatabaseManager $db */
        $db = $this->container['db'];
        $stmt = $db->connection()->prepare('SELECT token_hash FROM dynamic_links WHERE short_code = :c');
        $stmt->execute(['c' => $code]);
        $row = $stmt->fetch();
        self::assertIsArray($row);
        self::assertNotSame($token, $row['token_hash']);
        self::assertSame(hash('sha256', $token), $row['token_hash']);

        // 2. Public scan redirect records aggregated scan count
        $scanRes = $this->request('GET', '/r/' . $code, null, [
            'user-agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)',
        ]);
        self::assertSame(302, $scanRes->status());
        self::assertSame('https://example.com/initial-offer', $scanRes->headers()['Location']);

        // 3. Unauthorized access without token or with wrong token fails with 401
        $unauthRes = $this->request('GET', '/api/v1/links/' . $code . '/stats');
        self::assertSame(401, $unauthRes->status());

        $wrongTokenRes = $this->request('PATCH', '/api/v1/links/' . $code, [
            'destination_url' => 'https://attacker.example.com',
        ], ['authorization' => 'Bearer qubiq_own_wrong_secret_token_value_123456789']);
        self::assertSame(401, $wrongTokenRes->status());

        // 4. Authorized stats check shows 1 mobile scan
        $statsRes = $this->request('GET', '/api/v1/links/' . $code . '/stats', null, [
            'authorization' => 'Bearer ' . $token,
        ]);
        self::assertSame(200, $statsRes->status());
        $stats = json_decode($statsRes->body(), true)['data'];
        self::assertSame(1, $stats['total_scans']);
        self::assertSame(1, $stats['analytics']['today_scans']);
        self::assertSame('mobile', $stats['analytics']['devices'][0]['device']);

        // 5. Update destination and rotate ownership token
        $rotateRes = $this->request('PATCH', '/api/v1/links/' . $code, [
            'destination_url' => 'https://example.com/updated-offer',
            'rotate_token' => true,
        ], ['authorization' => 'Bearer ' . $token]);
        self::assertSame(200, $rotateRes->status());
        $rotatedData = json_decode($rotateRes->body(), true)['data'];
        $newToken = (string) $rotatedData['ownership_token'];
        self::assertNotSame($token, $newToken);

        // Old token must immediately stop working
        $oldTokenCheck = $this->request('GET', '/api/v1/links/' . $code . '/stats', null, [
            'authorization' => 'Bearer ' . $token,
        ]);
        self::assertSame(401, $oldTokenCheck->status());

        // Redirect now points to updated destination
        $scanUpdated = $this->request('GET', '/r/' . $code);
        self::assertSame('https://example.com/updated-offer', $scanUpdated->headers()['Location']);

        // 6. Pause link -> /r/{code} returns 404
        $pauseRes = $this->request('PATCH', '/api/v1/links/' . $code, [
            'is_active' => false,
        ], ['authorization' => 'Bearer ' . $newToken]);
        self::assertSame(200, $pauseRes->status());
        self::assertSame(404, $this->request('GET', '/r/' . $code)->status());

        // 7. Delete link permanently
        $delRes = $this->request('DELETE', '/api/v1/links/' . $code, null, [
            'authorization' => 'Bearer ' . $newToken,
        ]);
        self::assertSame(200, $delRes->status());
        self::assertSame(404, $this->request('GET', '/api/v1/links/' . $code . '/stats', null, [
            'authorization' => 'Bearer ' . $newToken,
        ])->status());
    }

    public function testMaliciousSvgAndSsrfLogoUploadsAreRejected(): void
    {
        $svgPayload = 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"/>');
        $resSvg = $this->request('POST', '/api/v1/qr/preview', [
            'type' => 'url',
            'data' => ['url' => 'https://example.com'],
            'design' => ['logo_data' => $svgPayload],
        ]);
        self::assertSame(422, $resSvg->status());

        // SVG disguised with data:image/png header
        $disguisedSvg = 'data:image/png;base64,' . base64_encode('<?xml version="1.0"?><svg onload="alert(1)"></svg>');
        $resDisguised = $this->request('POST', '/api/v1/qr/preview', [
            'type' => 'url',
            'data' => ['url' => 'https://example.com'],
            'design' => ['logo_data' => $disguisedSvg],
        ]);
        self::assertSame(422, $resDisguised->status());

        // Remote URL SSRF attempt in logo_data
        $resSsrf = $this->request('POST', '/api/v1/qr/preview', [
            'type' => 'url',
            'data' => ['url' => 'https://example.com'],
            'design' => ['logo_data' => 'http://169.254.169.254/latest/meta-data/'],
        ]);
        self::assertSame(422, $resSsrf->status());
    }

    public function testDynamicRedirectBlocksPhishingSchemesLoopbackAndPathTraversalFilenames(): void
    {
        foreach ([
            'javascript:alert(1)',
            'http://127.0.0.1:8080/admin',
            'http://169.254.169.254/latest/meta-data',
            'https://trusted-bank.com@evil.example.org/login',
            'https://qubiq.test/r/abcd1234',
        ] as $unsafeUrl) {
            $res = $this->request('POST', '/api/v1/links', ['destination_url' => $unsafeUrl]);
            self::assertSame(422, $res->status(), "Unsafe destination {$unsafeUrl} must be rejected.");
        }

        // Path traversal attempt in export filename is stripped safely
        $export = $this->request('POST', '/api/v1/qr/export', [
            'type' => 'url',
            'data' => ['url' => 'https://example.com'],
            'format' => 'png',
            'filename' => '../../../etc/passwd',
        ]);
        self::assertSame(200, $export->status());
        $disposition = $export->headers()['Content-Disposition'] ?? '';
        self::assertStringNotContainsString('/', $disposition);
        self::assertStringNotContainsString('..', $disposition);
    }

    public function testOversizedBodyReturns413(): void
    {
        $hugeBody = '{"type":"text","data":{"text":"' . str_repeat('A', 3200000) . '"}}';
        $res = $this->request('POST', '/api/v1/qr/preview', null, [
            'content-type' => 'application/json',
        ], [], $hugeBody);

        self::assertSame(413, $res->status());
    }
}
