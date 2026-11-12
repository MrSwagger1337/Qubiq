<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Payloads\PayloadEncoderFactory;
use App\Services\Dynamic\DynamicLinkService;
use App\Services\QR\QrGenerationService;
use App\Services\Rendering\SvgRenderer;
use App\Support\Config;
use App\Support\HttpRequest;
use App\Support\HttpResponse;
use App\Support\OpenApiSpecBuilder;
use App\Support\Translator;
use App\Support\View;

final class WebController
{
    public function __construct(
        private readonly Config $config,
        private readonly View $view,
        private readonly Translator $translator,
        private readonly PayloadEncoderFactory $payloadFactory,
        private readonly QrGenerationService $qrService,
        private readonly SvgRenderer $svgRenderer,
        private readonly DynamicLinkService $dynamicService,
        private readonly OpenApiSpecBuilder $openApiBuilder
    ) {
    }

    public function home(HttpRequest $request): HttpResponse
    {
        // Render an immediate server-side initial QR preview so the page loads with a real scannable QR immediately
        $initialInput = [
            'type' => 'url',
            'data' => ['url' => 'https://qubiq.herculesprojects.net'],
            'design' => [
                'ecc' => 'M',
                'size' => 1000,
                'foreground_mode' => 'linear',
                'foreground_color' => '#0f172a',
                'foreground_gradient_end' => '#047857',
                'gradient_angle' => 135,
                'module_shape' => 'rounded',
                'finder_outer_shape' => 'rounded',
                'finder_inner_shape' => 'rounded',
            ],
        ];
        $initialResult = $this->qrService->prepare($initialInput);
        $initialSvg = $this->svgRenderer->render($initialResult);

        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        $html = $this->view->render('home', [
            'appName' => $this->config->string('app.name', 'Qubiq'),
            'appVersion' => $this->config->string('app.version', '1.0.0'),
            'githubUrl' => $this->config->string('app.github_url'),
            'basePath' => $request->basePath(),
            'baseUrl' => $baseUrl,
            'dynamicEnabled' => $this->config->bool('dynamic.enabled', true) && extension_loaded('pdo_sqlite'),
            'typesCatalog' => $this->payloadFactory->metadataCatalog(),
            'i18n' => $this->translator->catalog(),
            'initialSvg' => $initialSvg,
            'initialMeta' => $initialResult->metadata(),
            'initialPayload' => $initialResult->encodedPayload,
            'limits' => [
                'min_size' => $this->config->int('qr.min_size', 128),
                'max_size' => $this->config->int('qr.max_size', 2048),
                'default_size' => $this->config->int('qr.default_size', 1000),
                'logo_max_kb' => (int) round($this->config->int('logo.max_bytes', 2097152) / 1024),
            ],
        ]);

        return HttpResponse::html($html);
    }

    public function docs(HttpRequest $request): HttpResponse
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        $html = $this->view->render('docs', [
            'appName' => $this->config->string('app.name', 'Qubiq'),
            'githubUrl' => $this->config->string('app.github_url'),
            'basePath' => $request->basePath(),
            'baseUrl' => $baseUrl,
            'typesCatalog' => $this->payloadFactory->metadataCatalog(),
        ]);

        return HttpResponse::html($html);
    }

    public function apiDocs(HttpRequest $request): HttpResponse
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        $html = $this->view->render('api-docs', [
            'appName' => $this->config->string('app.name', 'Qubiq'),
            'githubUrl' => $this->config->string('app.github_url'),
            'basePath' => $request->basePath(),
            'baseUrl' => $baseUrl,
            'rateLimits' => $this->config->array('security.rate_limits'),
        ]);

        return HttpResponse::html($html);
    }

    public function openApiSpec(HttpRequest $request): HttpResponse
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        return HttpResponse::json($this->openApiBuilder->build($baseUrl));
    }

    /**
     * @param array<string, string> $params
     */
    public function manageDynamic(HttpRequest $request, array $params): HttpResponse
    {
        /** @var list<string> $trustedProxies */
        $trustedProxies = $this->config->array('security.trusted_proxies');
        $baseUrl = $request->detectedBaseUrl($this->config->string('app.url'), $trustedProxies);

        $html = $this->view->render('manage', [
            'appName' => $this->config->string('app.name', 'Qubiq'),
            'githubUrl' => $this->config->string('app.github_url'),
            'basePath' => $request->basePath(),
            'baseUrl' => $baseUrl,
            'shortCode' => $params['code'] ?? '',
        ]);

        return HttpResponse::html($html);
    }

    /**
     * @param array<string, string> $params
     */
    public function redirectDynamic(HttpRequest $request, array $params): HttpResponse
    {
        $shortCode = $params['code'] ?? '';
        $resolved = $this->dynamicService->resolveScan($shortCode, $request);

        if ($resolved['safe_interstitial']) {
            $html = $this->view->render('interstitial', [
                'appName' => $this->config->string('app.name', 'Qubiq'),
                'basePath' => $request->basePath(),
                'destinationUrl' => $resolved['destination_url'],
            ]);

            return HttpResponse::html($html);
        }

        return HttpResponse::redirect($resolved['destination_url'], 302);
    }
}
