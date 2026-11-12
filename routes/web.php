<?php

declare(strict_types=1);

use App\Controllers\Api\DynamicLinkController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\QrApiController;
use App\Controllers\WebController;
use App\Support\HttpRequest;
use App\Support\HttpResponse;
use App\Support\Router;

/**
 * @param array{
 *   router: Router,
 *   web: WebController,
 *   health: HealthController,
 *   qr_api: QrApiController,
 *   dynamic_api: DynamicLinkController
 * } $c
 */
return static function (array $c): void {
    $router = $c['router'];
    $web = $c['web'];
    $health = $c['health'];
    $qrApi = $c['qr_api'];
    $dynamicApi = $c['dynamic_api'];

    // Web UI & Public Redirects
    $router->get('/', static fn (HttpRequest $req): HttpResponse => $web->home($req));
    $router->get('/docs', static fn (HttpRequest $req): HttpResponse => $web->docs($req));
    $router->get('/api-docs', static fn (HttpRequest $req): HttpResponse => $web->apiDocs($req));
    $router->get('/openapi.json', static fn (HttpRequest $req): HttpResponse => $web->openApiSpec($req));
    $router->get('/manage/{code}', static fn (HttpRequest $req, array $p): HttpResponse => $web->manageDynamic($req, $p));
    $router->get('/r/{code}', static fn (HttpRequest $req, array $p): HttpResponse => $web->redirectDynamic($req, $p));

    // Public Developer REST API v1
    $router->get('/api/v1/health', static fn (): HttpResponse => $health->check());
    $router->post('/api/v1/qr/validate', static fn (HttpRequest $req): HttpResponse => $qrApi->validate($req));
    $router->post('/api/v1/qr/preview', static fn (HttpRequest $req): HttpResponse => $qrApi->preview($req));
    $router->post('/api/v1/qr/generate', static fn (HttpRequest $req): HttpResponse => $qrApi->generate($req));
    $router->post('/api/v1/qr/export', static fn (HttpRequest $req): HttpResponse => $qrApi->export($req));

    // Anonymous Dynamic QR API v1
    $router->post('/api/v1/links', static fn (HttpRequest $req): HttpResponse => $dynamicApi->store($req));
    $router->get('/api/v1/links/{id}/stats', static fn (HttpRequest $req, array $p): HttpResponse => $dynamicApi->stats($req, $p));
    $router->patch('/api/v1/links/{id}', static fn (HttpRequest $req, array $p): HttpResponse => $dynamicApi->update($req, $p));
    $router->delete('/api/v1/links/{id}', static fn (HttpRequest $req, array $p): HttpResponse => $dynamicApi->destroy($req, $p));
};
