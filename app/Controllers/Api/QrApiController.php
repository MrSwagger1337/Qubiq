<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Exceptions\ValidationException;
use App\Middleware\RateLimiter;
use App\Services\Dynamic\DynamicLinkService;
use App\Services\Export\QrExportService;
use App\Services\QR\QrGenerationService;
use App\Services\Rendering\SvgRenderer;
use App\Support\Config;
use App\Support\HttpRequest;
use App\Support\HttpResponse;
use App\Support\Translator;

final class QrApiController
{
    public function __construct(
        private readonly Config $config,
        private readonly QrGenerationService $qrService,
        private readonly SvgRenderer $svgRenderer,
        private readonly QrExportService $exportService,
        private readonly DynamicLinkService $dynamicService,
        private readonly RateLimiter $rateLimiter,
        private readonly Translator $translator
    ) {
    }

    public function validate(HttpRequest $request): HttpResponse
    {
        $this->enforceRateLimit($request, 'api', $this->config->int('security.rate_limits.api', 60));
        $input = $this->extractValidatedInput($request);
        $result = $this->qrService->prepare($input, $request->file('logo'));

        return HttpResponse::json([
            'ok' => true,
            'data' => [
                'valid' => true,
                'encoded_payload' => $result->encodedPayload,
                'meta' => $result->metadata(),
            ],
        ]);
    }

    public function preview(HttpRequest $request): HttpResponse
    {
        $this->enforceRateLimit($request, 'preview', $this->config->int('security.rate_limits.preview', 120));
        $input = $this->extractValidatedInput($request);

        $overridePayload = null;
        if (isset($input['dynamic_short_url']) && is_string($input['dynamic_short_url']) && trim($input['dynamic_short_url']) !== '') {
            $overridePayload = trim($input['dynamic_short_url']);
        }

        $result = $this->qrService->prepare($input, $request->file('logo'), $overridePayload);
        $svg = $this->svgRenderer->render($result);

        return HttpResponse::json([
            'ok' => true,
            'data' => [
                'svg' => $svg,
                'encoded_payload' => $result->encodedPayload,
                'meta' => $result->metadata(),
            ],
        ]);
    }

    public function generate(HttpRequest $request): HttpResponse
    {
        $this->enforceRateLimit($request, 'api', $this->config->int('security.rate_limits.api', 60));
        $input = $this->extractValidatedInput($request);

        $dynamicInfo = null;
        $overridePayload = null;

        if (filter_var($input['dynamic'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $this->enforceRateLimit($request, 'dynamic_create', $this->config->int('security.rate_limits.dynamic_create_hourly', 20), 3600);
            $contentData = isset($input['data']) && is_array($input['data']) ? $input['data'] : $input;
            $rawDest = isset($contentData['url']) && is_scalar($contentData['url'])
                ? (string) $contentData['url']
                : (isset($input['destination_url']) && is_scalar($input['destination_url']) ? (string) $input['destination_url'] : '');

            /** @var list<string> $trustedProxies */
            $trustedProxies = $this->config->array('security.trusted_proxies');
            $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);
            $dynamicInfo = $this->dynamicService->create($rawDest, $baseUrl);
            $overridePayload = $dynamicInfo['short_url'];
            $input['type'] = 'url';
            $input['data'] = ['url' => $dynamicInfo['destination_url']];
        }

        $format = isset($input['format']) && is_scalar($input['format']) ? (string) $input['format'] : 'svg';
        $filename = isset($input['filename']) && is_scalar($input['filename']) ? (string) $input['filename'] : null;

        $result = $this->qrService->prepare($input, $request->file('logo'), $overridePayload);
        $exported = $this->exportService->export($result, $format, $filename);

        $responseData = [
            'format' => $exported->format,
            'mime_type' => $exported->mimeType,
            'filename' => $exported->filename,
            'encoded_payload' => $result->encodedPayload,
            'data_uri' => 'data:' . $exported->mimeType . ';base64,' . base64_encode($exported->content),
            'meta' => $result->metadata(),
        ];

        if ($exported->format === 'svg') {
            $responseData['svg'] = $exported->content;
        }
        if ($dynamicInfo !== null) {
            $responseData['dynamic'] = $dynamicInfo;
        }

        return HttpResponse::json([
            'ok' => true,
            'data' => $responseData,
        ], $dynamicInfo !== null ? 201 : 200);
    }

    public function export(HttpRequest $request): HttpResponse
    {
        $this->enforceRateLimit($request, 'export', $this->config->int('security.rate_limits.export', 60));
        $input = $this->extractValidatedInput($request);

        $overridePayload = null;
        if (isset($input['dynamic_short_url']) && is_string($input['dynamic_short_url']) && trim($input['dynamic_short_url']) !== '') {
            $overridePayload = trim($input['dynamic_short_url']);
        }

        $format = isset($input['format']) && is_scalar($input['format']) ? (string) $input['format'] : 'png';
        $filename = isset($input['filename']) && is_scalar($input['filename']) ? (string) $input['filename'] : null;

        $result = $this->qrService->prepare($input, $request->file('logo'), $overridePayload);
        $exported = $this->exportService->export($result, $format, $filename);

        return HttpResponse::download($exported->content, $exported->mimeType, $exported->filename);
    }

    /**
     * @return array<string, mixed>
     */
    private function extractValidatedInput(HttpRequest $request): array
    {
        $contentType = strtolower((string) $request->header('content-type', ''));
        if (str_contains($contentType, 'application/json') && !$request->hasValidJsonOrEmpty()) {
            throw new ValidationException($this->translator->get('errors.invalid_json'));
        }

        return $request->input();
    }

    private function enforceRateLimit(HttpRequest $request, string $bucket, int $limit, int $window = 60): void
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $ip = $request->clientIp($trustedProxies);
        $this->rateLimiter->enforce($bucket, $ip, $limit, $window);
    }
}
