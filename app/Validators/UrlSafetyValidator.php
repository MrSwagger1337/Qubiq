<?php

declare(strict_types=1);

namespace App\Validators;

use App\Exceptions\ValidationException;

final class UrlSafetyValidator
{
    private const BLOCKED_SCHEMES = [
        'javascript',
        'vbscript',
        'data',
        'file',
        'ftp',
        'jar',
        'view-source',
        'about',
        'blob',
        'chrome',
        'edge',
    ];

    /**
     * Validates and normalizes an HTTP/HTTPS URL, preserving path, query parameters, and fragments,
     * and normalizing internationalized domain names when possible.
     */
    public static function normalizeWebUrl(
        string $rawUrl,
        string $field = 'url',
        bool $autoHttps = true
    ): string {
        $trimmed = trim($rawUrl);
        if ($trimmed === '') {
            throw new ValidationException('Please provide a valid URL.', [
                $field => 'URL is required.',
            ]);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw new ValidationException('URL contains invalid control characters.', [
                $field => 'URL must not contain control characters.',
            ]);
        }

        // Reject dangerous schemes before auto-prefixing https://
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]*):/', $trimmed, $schemeMatch) === 1) {
            $scheme = strtolower($schemeMatch[1]);
            if (!in_array($scheme, ['http', 'https'], true)) {
                throw new ValidationException('Only http:// and https:// URLs are permitted.', [
                    $field => 'URL scheme must be http or https.',
                ]);
            }
        } elseif ($autoHttps) {
            $trimmed = 'https://' . ltrim($trimmed, '/');
        } else {
            throw new ValidationException('URL must begin with http:// or https://.', [
                $field => 'Include http:// or https:// at the start of the URL.',
            ]);
        }

        $parts = self::splitUrlPreservingUnicodeHost($trimmed, $field);
        $asciiHost = self::normalizeHost($parts['host'], $field);

        $rebuilt = $parts['scheme'] . '://';
        if ($parts['user'] !== '') {
            $rebuilt .= $parts['user'];
            if ($parts['pass'] !== '') {
                $rebuilt .= ':' . $parts['pass'];
            }
            $rebuilt .= '@';
        }
        $rebuilt .= $asciiHost;
        if ($parts['port'] !== null) {
            $rebuilt .= ':' . $parts['port'];
        }
        $rebuilt .= $parts['path'];
        if ($parts['query'] !== '') {
            $rebuilt .= '?' . $parts['query'];
        }
        if ($parts['fragment'] !== '') {
            $rebuilt .= '#' . $parts['fragment'];
        }

        if (filter_var($rebuilt, FILTER_VALIDATE_URL) === false) {
            throw new ValidationException('Please enter a valid web URL.', [
                $field => 'The URL format is invalid.',
            ]);
        }

        return $rebuilt;
    }

    /**
     * Validates a URL intended for public Dynamic QR redirection, enforcing extra anti-phishing,
     * SSRF/local-network target rejection, and self-redirect loop protection.
     *
     * @param list<string> $blockedDomains
     */
    public static function validatePublicRedirectTarget(
        string $rawUrl,
        string $appBaseUrl,
        array $blockedDomains = [],
        string $field = 'destination_url'
    ): string {
        $normalized = self::normalizeWebUrl($rawUrl, $field, false);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new ValidationException('Dynamic QR destination must be a public internet host.', [
                $field => 'Localhost and internal network hosts are not allowed for dynamic redirects.',
            ]);
        }

        // Prevent userinfo credential phishing URLs (https://bank.com@evil.example)
        if (parse_url($normalized, PHP_URL_USER) !== null) {
            throw new ValidationException('Destination URLs containing embedded credentials (@) are blocked to prevent phishing.', [
                $field => 'Remove embedded username/password segments from the destination URL.',
            ]);
        }

        // Block private, loopback, link-local, and metadata IP literals
        $cleanIp = trim($host, '[]');
        if (filter_var($cleanIp, FILTER_VALIDATE_IP) !== false) {
            $publicIp = filter_var(
                $cleanIp,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
            );
            if ($publicIp === false) {
                throw new ValidationException('Private or reserved IP addresses cannot be used as dynamic redirect targets.', [
                    $field => 'Destination IP address must be publicly routable.',
                ]);
            }
        }

        // Prevent redirect loops pointing back to /r/ on this application
        $appHost = strtolower((string) parse_url($appBaseUrl, PHP_URL_HOST));
        $targetPath = (string) (parse_url($normalized, PHP_URL_PATH) ?? '/');
        if ($appHost !== '' && $host === $appHost && str_contains($targetPath, '/r/')) {
            throw new ValidationException('Dynamic QR codes cannot redirect to another Qubiq redirect link.', [
                $field => 'Self-referencing redirect links are not permitted.',
            ]);
        }

        foreach ($blockedDomains as $blocked) {
            $blockedLower = strtolower(trim($blocked));
            if ($blockedLower !== '' && ($host === $blockedLower || str_ends_with($host, '.' . $blockedLower))) {
                throw new ValidationException('This destination domain is blocked by server policy.', [
                    $field => 'The destination domain is not permitted.',
                ]);
            }
        }

        return $normalized;
    }

    /**
     * @param list<string> $allowedDomains
     */
    public static function validateDomainScopedUrl(
        string $rawUrl,
        array $allowedDomains,
        string $platformLabel,
        string $field = 'url'
    ): string {
        $normalized = self::normalizeWebUrl($rawUrl, $field, true);
        $host = strtolower((string) parse_url($normalized, PHP_URL_HOST));

        foreach ($allowedDomains as $domain) {
            $d = strtolower($domain);
            if ($host === $d || str_ends_with($host, '.' . $d)) {
                return $normalized;
            }
        }

        throw new ValidationException(
            sprintf('Please enter a valid %s URL.', $platformLabel),
            [$field => sprintf('Host must belong to %s (%s).', $platformLabel, implode(', ', $allowedDomains))]
        );
    }

    public static function validateSafeCustomUri(string $rawUri, string $field = 'uri'): string
    {
        $trimmed = trim($rawUri);
        if ($trimmed === '') {
            throw new ValidationException('Custom URI cannot be empty.', [
                $field => 'Please enter a URI.',
            ]);
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $trimmed) === 1) {
            throw new ValidationException('Custom URI contains invalid control characters.', [
                $field => 'Control characters are not allowed.',
            ]);
        }

        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+.\-]{1,31}):(.+)$/s', $trimmed, $m) !== 1) {
            throw new ValidationException('Custom URI must include a valid scheme (e.g. myapp://open/item).', [
                $field => 'Format must be scheme:payload.',
            ]);
        }

        $scheme = strtolower($m[1]);
        if (in_array($scheme, self::BLOCKED_SCHEMES, true)) {
            throw new ValidationException(
                sprintf('The "%s:" URI scheme is blocked for security.', $scheme),
                [$field => sprintf('Unsafe or executable scheme "%s:" is not allowed.', $scheme)]
            );
        }

        return $scheme . ':' . trim($m[2]);
    }

    /**
     * @return array{scheme: string, user: string, pass: string, host: string, port: ?int, path: string, query: string, fragment: string}
     */
    private static function splitUrlPreservingUnicodeHost(string $url, string $field): array
    {
        $pattern = '~^(https?)://(?:([^:@/?#]+)(?::([^@/?#]*))?@)?(\[[0-9a-fA-F:]+\]|[^:/?#\s]+)(?::(\d{1,5}))?(/[^?#]*)?(?:\?([^#]*))?(?:#(.*))?$~u';
        if (preg_match($pattern, $url, $m) !== 1) {
            throw new ValidationException('Please enter a valid URL.', [
                $field => 'URL structure could not be parsed.',
            ]);
        }

        $port = ($m[5] ?? '') !== '' ? (int) $m[5] : null;
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new ValidationException('URL port must be between 1 and 65535.', [
                $field => 'Invalid port number.',
            ]);
        }

        return [
            'scheme' => strtolower($m[1]),
            'user' => $m[2],
            'pass' => $m[3],
            'host' => $m[4],
            'port' => $port,
            'path' => $m[6] ?? '',
            'query' => $m[7] ?? '',
            'fragment' => $m[8] ?? '',
        ];
    }

    private static function normalizeHost(string $host, string $field): string
    {
        $trimmed = trim($host);
        if ($trimmed === '') {
            throw new ValidationException('URL host cannot be empty.', [
                $field => 'Missing hostname.',
            ]);
        }

        // IPv6 literal
        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $ipv6 = substr($trimmed, 1, -1);
            if (filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new ValidationException('Invalid IPv6 host literal.', [
                    $field => 'Malformed IPv6 address.',
                ]);
            }

            return '[' . $ipv6 . ']';
        }

        // Internationalized domain name conversion if non-ASCII characters exist
        if (preg_match('/[^\x20-\x7E]/', $trimmed) === 1) {
            if (function_exists('idn_to_ascii')) {
                $converted = defined('INTL_IDNA_VARIANT_UTS46')
                    ? idn_to_ascii($trimmed, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46)
                    : idn_to_ascii($trimmed);
                if (is_string($converted) && $converted !== '') {
                    $trimmed = $converted;
                } else {
                    throw new ValidationException('Invalid internationalized domain name.', [
                        $field => 'Domain name could not be converted to Punycode.',
                    ]);
                }
            } else {
                throw new ValidationException('Internationalized domain names require ASCII/Punycode format on servers without ext-intl.', [
                    $field => 'Use the xn-- Punycode domain or enable PHP ext-intl.',
                ]);
            }
        }

        $lower = strtolower($trimmed);
        if (filter_var($lower, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $lower;
        }

        if ($lower !== 'localhost' && (!str_contains($lower, '.') || preg_match('/^[a-z0-9-]+(\.[a-z0-9-]+)+$/', $lower) !== 1)) {
            throw new ValidationException('URL must contain a valid domain name.', [
                $field => 'Enter a valid domain name (e.g. example.com).',
            ]);
        }

        return $lower;
    }
}
