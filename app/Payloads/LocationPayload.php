<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class LocationPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'location';
    }

    public function label(): string
    {
        return 'Location';
    }

    public function category(): string
    {
        return 'primary';
    }

    public function isUrlWrapper(): bool
    {
        return false;
    }

    public function description(): string
    {
        return 'Generates an RFC 5870 geo: coordinates URI with optional location query label.';
    }

    public function sampleData(): array
    {
        return [
            'latitude' => '37.7749',
            'longitude' => '-122.4194',
            'label' => 'San Francisco Civic Center',
        ];
    }

    public function encode(array $input): string
    {
        $latRaw = isset($input['latitude']) && is_scalar($input['latitude']) ? trim((string) $input['latitude']) : '';
        $lngRaw = isset($input['longitude']) && is_scalar($input['longitude']) ? trim((string) $input['longitude']) : '';
        $label = isset($input['label']) && is_scalar($input['label']) ? trim((string) $input['label']) : '';

        if ($latRaw === '' || !is_numeric($latRaw)) {
            throw new ValidationException('Valid latitude is required.', [
                'latitude' => 'Latitude must be a number between -90 and 90.',
            ]);
        }
        if ($lngRaw === '' || !is_numeric($lngRaw)) {
            throw new ValidationException('Valid longitude is required.', [
                'longitude' => 'Longitude must be a number between -180 and 180.',
            ]);
        }

        $lat = (float) $latRaw;
        $lng = (float) $lngRaw;

        if ($lat < -90.0 || $lat > 90.0) {
            throw new ValidationException('Latitude out of range.', [
                'latitude' => 'Latitude must be between -90 and 90.',
            ]);
        }
        if ($lng < -180.0 || $lng > 180.0) {
            throw new ValidationException('Longitude out of range.', [
                'longitude' => 'Longitude must be between -180 and 180.',
            ]);
        }

        $latFormatted = rtrim(rtrim(sprintf('%.6F', $lat), '0'), '.');
        $lngFormatted = rtrim(rtrim(sprintf('%.6F', $lng), '0'), '.');

        $uri = 'geo:' . $latFormatted . ',' . $lngFormatted;
        if ($label !== '') {
            $uri .= '?q=' . $latFormatted . ',' . $lngFormatted . '(' . rawurlencode($label) . ')';
        }

        return $uri;
    }
}
