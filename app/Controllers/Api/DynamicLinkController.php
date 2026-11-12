<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\ValidationException;
use App\Middleware\RateLimiter;
use App\Services\Dynamic\DynamicLinkService;
use App\Support\Config;
use App\Support\HttpRequest;
use App\Support\HttpResponse;
use App\Support\Translator;

final class DynamicLinkController
{
    public function __construct(
        private readonly Config $config,
        private readonly DynamicLinkService $dynamicService,
        private readonly RateLimiter $rateLimiter,
        private readonly Translator $translator
    ) {
    }

    public function store(HttpRequest $request): HttpResponse
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $ip = $request->clientIp($trustedProxies);
        $this->rateLimiter->enforce(
            'dynamic_create',
            $ip,
            $this->config->int('security.rate_limits.dynamic_create_hourly', 20),
            3600
        );

        if (!$request->hasValidJsonOrEmpty()) {
            throw new ValidationException($this->translator->get('errors.invalid_json'));
        }

        $input = $request->input();
        $destination = isset($input['destination_url']) && is_scalar($input['destination_url'])
            ? (string) $input['destination_url']
            : '';

        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);
        $created = $this->dynamicService->create($destination, $baseUrl);

        return HttpResponse::json([
            'ok' => true,
            'data' => $created,
        ], 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function stats(HttpRequest $request, array $params): HttpResponse
    {
        $this->enforceManageRateLimit($request);
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        $shortCode = $params['id'] ?? '';
        $stats = $this->dynamicService->inspectWithStats($shortCode, $request->bearerToken(), $baseUrl);

        return HttpResponse::json([
            'ok' => true,
            'data' => $stats,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(HttpRequest $request, array $params): HttpResponse
    {
        $this->enforceManageRateLimit($request);
        if (!$request->hasValidJsonOrEmpty()) {
            throw new ValidationException($this->translator->get('errors.invalid_json'));
        }

        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        $shortCode = $params['id'] ?? '';
        $updated = $this->dynamicService->update($shortCode, $request->bearerToken(), $request->input(), $baseUrl);

        return HttpResponse::json([
            'ok' => true,
            'data' => $updated,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(HttpRequest $request, array $params): HttpResponse
    {
        $this->enforceManageRateLimit($request);
        $shortCode = $params['id'] ?? '';
        $this->dynamicService->delete($shortCode, $request->bearerToken());

        return HttpResponse::json([
            'ok' => true,
            'data' => [
                'deleted' => true,
                'short_code' => $shortCode,
            ],
        ]);
    }

    private function enforceManageRateLimit(HttpRequest $request): void
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $ip = $request->clientIp($trustedProxies);
        $this->rateLimiter->enforce('dynamic_manage', $ip, $this->config->int('security.rate_limits.api', 60), 60);
    }
}
