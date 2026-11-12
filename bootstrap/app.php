<?php

declare(strict_types=1);

use App\Application;
use App\Controllers\Api\DynamicLinkController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\QrApiController;
use App\Controllers\WebController;
use App\Middleware\RateLimiter;
use App\Middleware\SecurityHeadersMiddleware;
use App\Payloads\PayloadEncoderFactory;
use App\Repositories\DynamicLinkRepository;
use App\Services\Dynamic\DynamicLinkService;
use App\Services\Export\QrExportService;
use App\Services\QR\LogoProcessorService;
use App\Services\QR\MatrixBuilderService;
use App\Services\QR\QrGenerationService;
use App\Services\QR\ReadabilityAdvisorService;
use App\Services\Rendering\EpsVectorRenderer;
use App\Services\Rendering\GdRasterRenderer;
use App\Services\Rendering\PdfVectorRenderer;
use App\Services\Rendering\SvgRenderer;
use App\Support\Config;
use App\Support\DatabaseManager;
use App\Support\Logger;
use App\Support\OpenApiSpecBuilder;
use App\Support\Router;
use App\Support\Translator;
use App\Support\View;
use Dotenv\Dotenv;

$basePath = dirname(__DIR__);
require_once $basePath . '/vendor/autoload.php';

if (is_file($basePath . '/.env')) {
    Dotenv::createImmutable($basePath)->safeLoad();
}

/** @var array<string, mixed> $rawConfig */
$rawConfig = require $basePath . '/config/app.php';
$config = new Config($rawConfig);

date_default_timezone_set($config->string('app.timezone', 'UTC'));

if (!$config->bool('app.debug', false)) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}

$logger = new Logger(
    logFile: $config->string('logging.file'),
    minLevel: $config->string('logging.level', 'info'),
    enabled: $config->bool('logging.enabled', true)
);

$translator = new Translator(
    langPath: $config->string('paths.lang'),
    locale: $config->string('app.locale', 'en')
);

$view = new View($config->string('paths.views'));
$db = new DatabaseManager($config);

$payloadFactory = new PayloadEncoderFactory();
$matrixBuilder = new MatrixBuilderService($config, $translator);
$logoProcessor = new LogoProcessorService($config, $translator);
$advisor = new ReadabilityAdvisorService($translator);
$qrService = new QrGenerationService($config, $payloadFactory, $matrixBuilder, $logoProcessor, $advisor);

$svgRenderer = new SvgRenderer();
$rasterRenderer = new GdRasterRenderer();
$pdfRenderer = new PdfVectorRenderer($config);
$epsRenderer = new EpsVectorRenderer();
$exportService = new QrExportService($svgRenderer, $rasterRenderer, $pdfRenderer, $epsRenderer);

$dynamicRepo = new DynamicLinkRepository($db);
$dynamicService = new DynamicLinkService($config, $dynamicRepo, $translator);
$rateLimiter = new RateLimiter($config, $translator);
$securityHeaders = new SecurityHeadersMiddleware($config);
$openApiBuilder = new OpenApiSpecBuilder($config, $payloadFactory);

$router = new Router();

$webController = new WebController(
    $config,
    $view,
    $translator,
    $payloadFactory,
    $qrService,
    $svgRenderer,
    $dynamicService,
    $openApiBuilder
);
$healthController = new HealthController($config);
$qrApiController = new QrApiController(
    $config,
    $qrService,
    $svgRenderer,
    $exportService,
    $dynamicService,
    $rateLimiter,
    $translator
);
$dynamicLinkController = new DynamicLinkController($config, $dynamicService, $rateLimiter, $translator);

/** @var callable $registerRoutes */
$registerRoutes = require $basePath . '/routes/web.php';
$registerRoutes([
    'router' => $router,
    'web' => $webController,
    'health' => $healthController,
    'qr_api' => $qrApiController,
    'dynamic_api' => $dynamicLinkController,
]);

$application = new Application($config, $router, $securityHeaders, $logger, $translator, $view);

return [
    'app' => $application,
    'config' => $config,
    'db' => $db,
    'logger' => $logger,
    'translator' => $translator,
    'payload_factory' => $payloadFactory,
    'qr_service' => $qrService,
    'export_service' => $exportService,
    'dynamic_service' => $dynamicService,
];
