<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Support\Config;
use App\Support\HttpResponse;

final class HealthController
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    public function check(): HttpResponse
    {
        return HttpResponse::json([
            'ok' => true,
            'data' => [
                'status' => 'healthy',
                'service' => $this->config->string('app.name', 'Qubiq'),
                'version' => $this->config->string('app.version', '1.0.0'),
                'dynamic_qr_enabled' => $this->config->bool('dynamic.enabled', true) && extension_loaded('pdo_sqlite'),
                'formats' => ['png', 'svg', 'pdf', 'eps', 'webp', 'jpeg'],
            ],
        ]);
    }
}
