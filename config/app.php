<?php

declare(strict_types=1);

use App\Support\Env;

$basePath = dirname(__DIR__);
$appKey = Env::string('APP_KEY', '');
if ($appKey === '') {
    $appKey = hash('sha256', $basePath . '|qubiq-default-rate-key');
}

return [
    'app' => [
        'name' => Env::string('APP_NAME', 'Qubiq'),
        'env' => Env::string('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG', false),
        'url' => rtrim(Env::string('APP_URL', 'https://qubiq.herculesprojects.net'), '/'),
        'locale' => Env::string('APP_LOCALE', 'en'),
        'timezone' => Env::string('APP_TIMEZONE', 'UTC'),
        'key' => $appKey,
        'version' => '1.0.0',
        'github_url' => Env::string('GITHUB_URL', 'https://github.com/MrSwagger1337/Qubiq'),
    ],
    'paths' => [
        'base' => $basePath,
        'public' => $basePath . '/public',
        'resources' => $basePath . '/resources',
        'views' => $basePath . '/resources/views',
        'lang' => $basePath . '/resources/lang',
        'storage' => $basePath . '/storage',
        'cache' => $basePath . '/storage/cache',
        'logs' => $basePath . '/storage/logs',
        'temp' => $basePath . '/storage/temp',
        'migrations' => $basePath . '/database/migrations',
    ],
    'qr' => [
        'max_payload_bytes' => Env::int('QR_MAX_PAYLOAD_BYTES', 2953),
        'min_size' => Env::int('QR_MIN_SIZE', 128),
        'max_size' => Env::int('QR_MAX_SIZE', 2048),
        'default_size' => Env::int('QR_DEFAULT_SIZE', 1000),
        'default_ecc' => strtoupper(Env::string('QR_DEFAULT_ECC', 'M')),
        'default_quiet_zone' => Env::int('QR_DEFAULT_QUIET_ZONE', 4),
        'request_max_body_bytes' => Env::int('REQUEST_MAX_BODY_BYTES', 3145728),
    ],
    'logo' => [
        'max_bytes' => Env::int('LOGO_MAX_BYTES', 2097152),
        'max_dimension' => Env::int('LOGO_MAX_DIMENSION', 2048),
        'max_pixels' => Env::int('LOGO_MAX_PIXELS', 4194304),
        'max_coverage_ratio' => Env::float('LOGO_MAX_COVERAGE_RATIO', 0.28),
        'allowed_mimes' => ['image/png', 'image/jpeg', 'image/webp'],
    ],
    'dynamic' => [
        'enabled' => Env::bool('DYNAMIC_QR_ENABLED', true),
        'safe_interstitial' => Env::bool('DYNAMIC_QR_SAFE_INTERSTITIAL', false),
        'blocked_domains' => Env::csv('DYNAMIC_QR_BLOCKED_DOMAINS'),
        'analytics_enabled' => Env::bool('ANALYTICS_ENABLED', true),
        'track_country' => Env::bool('ANALYTICS_TRACK_COUNTRY', false),
        'trusted_country_header' => Env::string('ANALYTICS_TRUSTED_COUNTRY_HEADER', 'HTTP_CF_IPCOUNTRY'),
        'retention_days' => Env::int('ANALYTICS_RETENTION_DAYS', 365),
    ],
    'database' => [
        'sqlite_path' => (static function (string $base): string {
            $configured = Env::string('DB_DATABASE', 'storage/database/qubiq.sqlite');
            if ($configured === ':memory:' || str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $configured) === 1) {
                return $configured;
            }

            return $base . '/' . ltrim($configured, '/\\');
        })($basePath),
    ],
    'logging' => [
        'enabled' => Env::string('LOG_CHANNEL', 'file') !== 'null',
        'level' => Env::string('LOG_LEVEL', 'info'),
        'file' => $basePath . '/storage/logs/qubiq.log',
    ],
    'security' => [
        'rate_limit_enabled' => Env::bool('RATE_LIMIT_ENABLED', true),
        'rate_limits' => [
            'preview' => Env::int('RATE_LIMIT_PREVIEW_PER_MINUTE', 120),
            'export' => Env::int('RATE_LIMIT_EXPORT_PER_MINUTE', 60),
            'api' => Env::int('RATE_LIMIT_API_PER_MINUTE', 60),
            'dynamic_create_hourly' => Env::int('RATE_LIMIT_DYNAMIC_CREATE_PER_HOUR', 20),
        ],
        'cors_allowed_origins' => Env::csv('CORS_ALLOWED_ORIGINS'),
        'trusted_proxies' => Env::csv('TRUSTED_PROXIES'),
        'csp_report_only' => Env::bool('CSP_REPORT_ONLY', false),
    ],
];
