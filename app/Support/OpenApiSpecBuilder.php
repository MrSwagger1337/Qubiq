<?php

declare(strict_types=1);

namespace App\Support;

use App\Payloads\PayloadEncoderFactory;

final class OpenApiSpecBuilder
{
    public function __construct(
        private readonly Config $config,
        private readonly PayloadEncoderFactory $payloadFactory
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $serverUrl): array
    {
        $supportedTypes = array_keys($this->payloadFactory->all());

        return [
            'openapi' => '3.1.0',
            'info' => [
                'title' => $this->config->string('app.name', 'Qubiq') . ' REST API',
                'version' => $this->config->string('app.version', '1.0.0'),
                'description' => 'Free, accountless REST API for generating customized static QR codes in PNG, SVG, PDF, EPS, WebP, and JPEG, plus optional token-protected dynamic QR redirects.',
                'license' => [
                    'name' => 'MIT',
                    'identifier' => 'MIT',
                ],
            ],
            'servers' => [
                ['url' => rtrim($serverUrl, '/'), 'description' => 'Active Qubiq instance'],
            ],
            'components' => [
                'securitySchemes' => [
                    'OwnershipToken' => [
                        'type' => 'http',
                        'scheme' => 'bearer',
                        'description' => 'One-time secret ownership token returned when creating an anonymous dynamic QR code.',
                    ],
                ],
                'schemas' => [
                    'DesignOptions' => [
                        'type' => 'object',
                        'properties' => [
                            'ecc' => ['type' => 'string', 'enum' => ['L', 'M', 'Q', 'H'], 'default' => 'M'],
                            'version' => ['type' => ['integer', 'string'], 'default' => 'auto'],
                            'mask_pattern' => ['type' => ['integer', 'string'], 'default' => 'auto'],
                            'quiet_zone' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 12, 'default' => 4],
                            'size' => ['type' => 'integer', 'minimum' => 128, 'maximum' => 2048, 'default' => 1000],
                            'foreground_mode' => ['type' => 'string', 'enum' => ['solid', 'linear', 'radial'], 'default' => 'solid'],
                            'foreground_color' => ['type' => 'string', 'default' => '#0f172a'],
                            'foreground_gradient_end' => ['type' => 'string', 'default' => '#059669'],
                            'gradient_angle' => ['type' => 'number', 'default' => 135],
                            'finder_outer_color' => ['type' => ['string', 'null']],
                            'finder_inner_color' => ['type' => ['string', 'null']],
                            'background_mode' => ['type' => 'string', 'enum' => ['solid', 'transparent', 'linear', 'radial'], 'default' => 'solid'],
                            'background_color' => ['type' => 'string', 'default' => '#ffffff'],
                            'module_shape' => ['type' => 'string', 'enum' => ['square', 'rounded', 'circle', 'dots', 'diamond', 'soft'], 'default' => 'square'],
                            'finder_outer_shape' => ['type' => 'string', 'enum' => ['square', 'rounded', 'circle', 'cushion'], 'default' => 'square'],
                            'finder_inner_shape' => ['type' => 'string', 'enum' => ['square', 'rounded', 'circle'], 'default' => 'square'],
                            'logo_data' => ['type' => ['string', 'null'], 'description' => 'Optional data:image/(png|jpeg|webp);base64,... string'],
                            'logo_scale' => ['type' => 'number', 'minimum' => 0.08, 'maximum' => 0.28, 'default' => 0.2],
                        ],
                    ],
                    'QrRequest' => [
                        'type' => 'object',
                        'required' => ['type', 'data'],
                        'properties' => [
                            'type' => ['type' => 'string', 'enum' => $supportedTypes],
                            'data' => ['type' => 'object'],
                            'design' => ['$ref' => '#/components/schemas/DesignOptions'],
                            'format' => ['type' => 'string', 'enum' => ['png', 'svg', 'pdf', 'eps', 'webp', 'jpeg'], 'default' => 'png'],
                            'filename' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
            'paths' => [
                '/api/v1/health' => [
                    'get' => [
                        'summary' => 'Service health and capability check',
                        'responses' => ['200' => ['description' => 'Service is operational']],
                    ],
                ],
                '/api/v1/qr/validate' => [
                    'post' => [
                        'summary' => 'Validate QR payload and design configuration',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/QrRequest']]],
                        ],
                        'responses' => [
                            '200' => ['description' => 'Valid configuration with matrix metadata and readability warnings'],
                            '422' => ['description' => 'Validation error'],
                        ],
                    ],
                ],
                '/api/v1/qr/preview' => [
                    'post' => [
                        'summary' => 'Generate live SVG preview and matrix diagnostics',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/QrRequest']]],
                        ],
                        'responses' => ['200' => ['description' => 'SVG markup and QR metadata']],
                    ],
                ],
                '/api/v1/qr/generate' => [
                    'post' => [
                        'summary' => 'Generate a QR code as JSON with base64/data-URI output',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/QrRequest']]],
                        ],
                        'responses' => ['200' => ['description' => 'Generated QR code data and metadata']],
                    ],
                ],
                '/api/v1/qr/export' => [
                    'post' => [
                        'summary' => 'Export and download binary QR file in PNG, SVG, PDF, EPS, WebP, or JPEG',
                        'requestBody' => [
                            'required' => true,
                            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/QrRequest']]],
                        ],
                        'responses' => ['200' => ['description' => 'Binary QR file stream']],
                    ],
                ],
                '/api/v1/links' => [
                    'post' => [
                        'summary' => 'Create an anonymous dynamic QR short link and ownership secret',
                        'responses' => ['201' => ['description' => 'Dynamic link created with one-time ownership token']],
                    ],
                ],
                '/api/v1/links/{id}' => [
                    'patch' => [
                        'summary' => 'Update destination URL, active status, or rotate ownership token',
                        'security' => [['OwnershipToken' => []]],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Updated dynamic link']],
                    ],
                    'delete' => [
                        'summary' => 'Delete dynamic QR link permanently',
                        'security' => [['OwnershipToken' => []]],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Deleted']],
                    ],
                ],
                '/api/v1/links/{id}/stats' => [
                    'get' => [
                        'summary' => 'Retrieve privacy-preserving aggregated scan statistics',
                        'security' => [['OwnershipToken' => []]],
                        'parameters' => [['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'string']]],
                        'responses' => ['200' => ['description' => 'Aggregated scan analytics']],
                    ],
                ],
            ],
        ];
    }
}
