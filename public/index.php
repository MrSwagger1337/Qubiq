<?php

declare(strict_types=1);

use App\Application;
use App\Support\Config;
use App\Support\HttpRequest;

// Support PHP built-in development server: php -S localhost:8000 -t public public/index.php
if (PHP_SAPI === 'cli-server') {
    $uriPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $staticCandidate = realpath(__DIR__ . '/' . ltrim($uriPath, '/'));
    $publicRoot = realpath(__DIR__);

    if (
        $uriPath !== '/'
        && $staticCandidate !== false
        && $publicRoot !== false
        && str_starts_with($staticCandidate, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($staticCandidate)
    ) {
        return false;
    }
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';

/** @var Config $config */
$config = $container['config'];
/** @var Application $app */
$app = $container['app'];

$request = HttpRequest::capture($config->int('qr.request_max_body_bytes', 3145728));
$response = $app->handle($request);
$response->send();
