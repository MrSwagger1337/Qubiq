<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Support\Config;
use App\Support\HttpRequest;
use App\Support\HttpResponse;

final class SecurityHeadersMiddleware
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function apply(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        $cspHeader = $this->config->bool('security.csp_report_only', false)
            ? 'Content-Security-Policy-Report-Only'
            : 'Content-Security-Policy';

        $cspValue = "default-src 'self'; "
            . "script-src 'self'; "
            . "style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; "
            . "font-src 'self' data:; "
            . "connect-src 'self'; "
            . "object-src 'none'; "
            . "base-uri 'self'; "
            . "frame-ancestors 'none'; "
            . "form-action 'self'";

        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()')
            ->withHeader($cspHeader, $cspValue);

        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        if ($request->isHttps($trustedProxies)) {
            $response = $response->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (str_starts_with($request->path(), '/api/')) {
            $response = $this->applyCors($request, $response);
        }

        return $response;
    }

    public function preflight(HttpRequest $request): HttpResponse
    {
        $response = new HttpResponse('', 204);

        return $this->apply($request, $response);
    }

    private function applyCors(HttpRequest $request, HttpResponse $response): HttpResponse
    {
        $origin = $request->header('origin');
        if ($origin === null || $origin === '') {
            return $response;
        }

        /** @var list<string> $allowedOrigins */
        $allowedOrigins = $this->config->array('security.cors_allowed_origins');
        if ($allowedOrigins === []) {
            return $response;
        }

        $matchedOrigin = null;
        if (in_array('*', $allowedOrigins, true)) {
            $matchedOrigin = '*';
        } elseif (in_array($origin, $allowedOrigins, true)) {
            $matchedOrigin = $origin;
        }

        if ($matchedOrigin === null) {
            return $response;
        }

        return $response
            ->withHeader('Access-Control-Allow-Origin', $matchedOrigin)
            ->withHeader('Vary', 'Origin')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept')
            ->withHeader('Access-Control-Max-Age', '600');
    }
}
