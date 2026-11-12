<?php

declare(strict_types=1);

namespace App;

use App\Exceptions\NotFoundException;
use App\Exceptions\QubiqException;
use App\Exceptions\RateLimitException;
use App\Middleware\SecurityHeadersMiddleware;
use App\Support\Config;
use App\Support\HttpRequest;
use App\Support\HttpResponse;
use App\Support\Logger;
use App\Support\Router;
use App\Support\Translator;
use App\Support\View;
use Throwable;

final class Application
{
    public function __construct(
        private readonly Config $config,
        private readonly Router $router,
        private readonly SecurityHeadersMiddleware $securityHeaders,
        private readonly Logger $logger,
        private readonly Translator $translator,
        private readonly View $view
    ) {
    }

    public function handle(HttpRequest $request): HttpResponse
    {
        if ($request->method() === 'OPTIONS') {
            return $this->securityHeaders->preflight($request);
        }

        $maxBody = $this->config->int('qr.request_max_body_bytes', 3145728);
        if ($request->contentLength() > $maxBody || strlen($request->rawBody()) > $maxBody) {
            $errResponse = HttpResponse::json([
                'ok' => false,
                'error' => [
                    'code' => 'payload_too_large',
                    'message' => $this->translator->get('errors.payload_too_large'),
                ],
            ], 413);

            return $this->securityHeaders->apply($request, $errResponse);
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (QubiqException $e) {
            $response = $this->renderHandledException($request, $e);
        } catch (Throwable $e) {
            $this->logger->exception($e, [
                'method' => $request->method(),
                'path' => $request->path(),
            ]);
            $response = $this->renderUnhandledException($request, $e);
        }

        return $this->securityHeaders->apply($request, $response);
    }

    private function renderHandledException(HttpRequest $request, QubiqException $e): HttpResponse
    {
        $headers = [];
        if ($e instanceof RateLimitException) {
            $headers['Retry-After'] = (string) $e->retryAfterSeconds();
        }

        if ($this->wantsJson($request)) {
            $errorPayload = [
                'code' => $e->errorCode(),
                'message' => $e->getMessage(),
            ];
            $details = $e->details();
            if ($details !== []) {
                $errorPayload['details'] = $details;
            }

            return HttpResponse::json([
                'ok' => false,
                'error' => $errorPayload,
            ], $e->statusCode(), $headers);
        }

        if ($e instanceof NotFoundException) {
            $html = $this->view->render('error', [
                'appName' => $this->config->string('app.name', 'Qubiq'),
                'basePath' => $request->basePath(),
                'statusCode' => 404,
                'title' => 'Page or QR Link Not Found',
                'message' => $e->getMessage(),
            ]);

            return HttpResponse::html($html, 404, $headers);
        }

        $html = $this->view->render('error', [
            'appName' => $this->config->string('app.name', 'Qubiq'),
            'basePath' => $request->basePath(),
            'statusCode' => $e->statusCode(),
            'title' => 'Request Could Not Be Completed',
            'message' => $e->getMessage(),
        ]);

        return HttpResponse::html($html, $e->statusCode(), $headers);
    }

    private function renderUnhandledException(HttpRequest $request, Throwable $e): HttpResponse
    {
        $debug = $this->config->bool('app.debug', false);
        $message = $debug ? $e->getMessage() : 'An unexpected internal error occurred.';

        if ($this->wantsJson($request)) {
            return HttpResponse::json([
                'ok' => false,
                'error' => [
                    'code' => 'internal_error',
                    'message' => $message,
                ],
            ], 500);
        }

        $html = $this->view->render('error', [
            'appName' => $this->config->string('app.name', 'Qubiq'),
            'basePath' => $request->basePath(),
            'statusCode' => 500,
            'title' => 'Unexpected Server Error',
            'message' => $message,
        ]);

        return HttpResponse::html($html, 500);
    }

    private function wantsJson(HttpRequest $request): bool
    {
        if (str_starts_with($request->path(), '/api/')) {
            return true;
        }
        $accept = strtolower((string) $request->header('accept', ''));

        return str_contains($accept, 'application/json');
    }
}
