<?php

declare(strict_types=1);

namespace App\Services\Dynamic;

use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use App\Exceptions\UnauthorizedException;
use App\Repositories\DynamicLinkRepository;
use App\Support\Config;
use App\Support\HttpRequest;
use App\Support\Translator;
use App\Validators\UrlSafetyValidator;

final class DynamicLinkService
{
    private const ALPHABET = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    public function __construct(
        private readonly Config $config,
        private readonly DynamicLinkRepository $repository,
        private readonly Translator $translator
    ) {
    }

    /**
     * Creates a new anonymous dynamic QR redirect record and returns the one-time secret ownership token.
     *
     * @return array{
     *   short_code: string,
     *   short_url: string,
     *   destination_url: string,
     *   ownership_token: string,
     *   management_url: string,
     *   is_active: bool,
     *   created_at: string
     * }
     */
    public function create(string $rawDestinationUrl, string $baseUrl): array
    {
        $this->ensureEnabled();

        /** @var list<string> $blockedDomains */
        $blockedDomains = $this->config->array('dynamic.blocked_domains');
        $cleanUrl = UrlSafetyValidator::validatePublicRedirectTarget(
            $rawDestinationUrl,
            $baseUrl,
            $blockedDomains,
            'destination_url'
        );

        $shortCode = $this->generateUniqueShortCode();
        $ownerToken = $this->generateOwnershipToken();
        $tokenHash = $this->hashToken($ownerToken);

        $this->repository->create($shortCode, $cleanUrl, $tokenHash);
        $created = $this->repository->findByShortCode($shortCode);
        $createdAt = is_array($created) && isset($created['created_at']) ? (string) $created['created_at'] : gmdate(DATE_ATOM);

        $shortUrl = rtrim($baseUrl, '/') . '/r/' . $shortCode;
        // Secret token is placed ONLY in URL fragment (#token=...), never in query string
        $managementUrl = rtrim($baseUrl, '/') . '/manage/' . $shortCode . '#token=' . $ownerToken;

        return [
            'short_code' => $shortCode,
            'short_url' => $shortUrl,
            'destination_url' => $cleanUrl,
            'ownership_token' => $ownerToken,
            'management_url' => $managementUrl,
            'is_active' => true,
            'created_at' => $createdAt,
        ];
    }

    /**
     * Resolves a public scan redirect and records privacy-respecting aggregate analytics.
     *
     * @return array{destination_url: string, safe_interstitial: bool}
     */
    public function resolveScan(string $shortCode, HttpRequest $request): array
    {
        $this->ensureEnabled();

        $record = $this->findValidCodeOrFail($shortCode);
        if ((int) ($record['is_active'] ?? 0) !== 1) {
            throw new NotFoundException('This dynamic QR code has been disabled by its owner.');
        }

        $linkId = (int) $record['id'];
        $deviceCategory = self::classifyCoarseDevice((string) ($request->header('user-agent', '') ?? ''));
        $countryCode = $this->resolveOptionalCountry($request);

        $this->repository->recordScan(
            linkId: $linkId,
            analyticsEnabled: $this->config->bool('dynamic.analytics_enabled', true),
            deviceCategory: $deviceCategory,
            countryCode: $countryCode,
            retentionDays: $this->config->int('dynamic.retention_days', 365)
        );

        return [
            'destination_url' => (string) $record['destination_url'],
            'safe_interstitial' => $this->config->bool('dynamic.safe_interstitial', false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inspectWithStats(string $shortCode, ?string $bearerToken, string $baseUrl): array
    {
        $this->ensureEnabled();
        $record = $this->authorizeOwner($shortCode, $bearerToken);
        $analytics = $this->repository->getAggregatedAnalytics((int) $record['id']);

        return [
            'short_code' => (string) $record['short_code'],
            'short_url' => rtrim($baseUrl, '/') . '/r/' . (string) $record['short_code'],
            'destination_url' => (string) $record['destination_url'],
            'is_active' => (int) ($record['is_active'] ?? 0) === 1,
            'total_scans' => (int) ($record['scan_count'] ?? 0),
            'created_at' => (string) ($record['created_at'] ?? ''),
            'updated_at' => (string) ($record['updated_at'] ?? ''),
            'last_scanned_at' => $record['last_scanned_at'] ?? null,
            'analytics' => $analytics,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $shortCode, ?string $bearerToken, array $payload, string $baseUrl): array
    {
        $this->ensureEnabled();
        $record = $this->authorizeOwner($shortCode, $bearerToken);
        $id = (int) $record['id'];

        $newDestination = isset($payload['destination_url']) && is_scalar($payload['destination_url'])
            ? (string) $payload['destination_url']
            : (string) $record['destination_url'];

        /** @var list<string> $blockedDomains */
        $blockedDomains = $this->config->array('dynamic.blocked_domains');
        $cleanUrl = UrlSafetyValidator::validatePublicRedirectTarget(
            $newDestination,
            $baseUrl,
            $blockedDomains,
            'destination_url'
        );

        $isActive = array_key_exists('is_active', $payload)
            ? filter_var($payload['is_active'], FILTER_VALIDATE_BOOLEAN)
            : ((int) ($record['is_active'] ?? 1) === 1);

        $this->repository->updateDestinationAndStatus($id, $cleanUrl, $isActive);

        $rotatedToken = null;
        if (filter_var($payload['rotate_token'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $rotatedToken = $this->generateOwnershipToken();
            $this->repository->updateTokenHash($id, $this->hashToken($rotatedToken));
        }

        $activeToken = $rotatedToken ?? (string) $bearerToken;
        $updated = $this->inspectWithStats($shortCode, $activeToken, $baseUrl);

        if ($rotatedToken !== null) {
            $updated['ownership_token'] = $rotatedToken;
            $updated['management_url'] = rtrim($baseUrl, '/') . '/manage/' . $shortCode . '#token=' . $rotatedToken;
        }

        return $updated;
    }

    public function delete(string $shortCode, ?string $bearerToken): void
    {
        $this->ensureEnabled();
        $record = $this->authorizeOwner($shortCode, $bearerToken);
        $this->repository->delete((int) $record['id']);
    }

    /**
     * Coarse, non-fingerprinting User-Agent categorization.
     */
    public static function classifyCoarseDevice(string $userAgent): string
    {
        $ua = strtolower(substr($userAgent, 0, 256));
        if ($ua === '') {
            return 'other';
        }
        if (preg_match('/bot|crawl|spider|slurp|curl|wget|headless/', $ua) === 1) {
            return 'bot';
        }
        if (preg_match('/ipad|tablet|kindle|playbook|silk/', $ua) === 1) {
            return 'tablet';
        }
        if (preg_match('/mobi|iphone|ipod|android/', $ua) === 1) {
            return 'mobile';
        }
        if (preg_match('/mozilla|chrome|safari|firefox|edg|opera|macintosh|windows|linux/', $ua) === 1) {
            return 'desktop';
        }

        return 'other';
    }

    private function ensureEnabled(): void
    {
        if (!$this->config->bool('dynamic.enabled', true)) {
            throw new ForbiddenException($this->translator->get('errors.dynamic_disabled'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function findValidCodeOrFail(string $shortCode): array
    {
        if (preg_match('/^[0-9A-Za-z]{6,16}$/', $shortCode) !== 1) {
            throw new NotFoundException($this->translator->get('errors.not_found'));
        }

        $record = $this->repository->findByShortCode($shortCode);
        if ($record === null) {
            throw new NotFoundException($this->translator->get('errors.not_found'));
        }

        return $record;
    }

    /**
     * @return array<string, mixed>
     */
    private function authorizeOwner(string $shortCode, ?string $bearerToken): array
    {
        if ($bearerToken === null || strlen($bearerToken) < 24 || strlen($bearerToken) > 128) {
            throw new UnauthorizedException($this->translator->get('errors.unauthorized'));
        }

        $record = $this->findValidCodeOrFail($shortCode);
        $storedHash = (string) ($record['token_hash'] ?? '');
        $candidateHash = $this->hashToken($bearerToken);

        if ($storedHash === '' || !hash_equals($storedHash, $candidateHash)) {
            throw new UnauthorizedException($this->translator->get('errors.unauthorized'));
        }

        return $record;
    }

    private function generateUniqueShortCode(int $length = 8): string
    {
        $maxIdx = strlen(self::ALPHABET) - 1;
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::ALPHABET[random_int(0, $maxIdx)];
            }
            if ($this->repository->findByShortCode($code) === null) {
                return $code;
            }
        }

        return bin2hex(random_bytes(6));
    }

    private function generateOwnershipToken(): string
    {
        $raw = random_bytes(32); // 256 bits of cryptographic entropy
        $b64Url = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

        return 'qubiq_own_' . $b64Url;
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function resolveOptionalCountry(HttpRequest $request): string
    {
        if (!$this->config->bool('dynamic.track_country', false)) {
            return 'XX';
        }

        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $server = $request->server();
        $remoteAddr = (string) ($server['REMOTE_ADDR'] ?? '');

        if ($trustedProxies !== [] && !in_array($remoteAddr, $trustedProxies, true)) {
            return 'XX';
        }

        $headerServerKey = strtoupper($this->config->string('dynamic.trusted_country_header', 'HTTP_CF_IPCOUNTRY'));
        $rawCountry = isset($server[$headerServerKey]) && is_string($server[$headerServerKey])
            ? strtoupper(trim($server[$headerServerKey]))
            : '';

        if (preg_match('/^[A-Z]{2}$/', $rawCountry) === 1 && $rawCountry !== 'T1') {
            return $rawCountry;
        }

        return 'XX';
    }
}
