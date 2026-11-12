<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class WifiPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'wifi';
    }

    public function label(): string
    {
        return 'Wi-Fi';
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
        return 'Generates a WIFI: network credential payload. Note: WPA2/WPA3-Personal networks both use T:WPA per ZXing/Android/iOS Wi-Fi QR conventions; WPA2-Enterprise (802.1X) is not universally supported via QR.';
    }

    public function sampleData(): array
    {
        return [
            'ssid' => 'Qubiq-Guest-5G',
            'encryption' => 'WPA',
            'password' => 'CorrectHorseBatteryStaple!',
            'hidden' => false,
        ];
    }

    public function encode(array $input): string
    {
        $ssid = isset($input['ssid']) && is_scalar($input['ssid']) ? trim((string) $input['ssid']) : '';
        $encryptionRaw = isset($input['encryption']) && is_scalar($input['encryption'])
            ? strtoupper(trim((string) $input['encryption']))
            : 'WPA';
        $password = isset($input['password']) && is_scalar($input['password']) ? (string) $input['password'] : '';
        $hidden = filter_var($input['hidden'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($ssid === '' || strlen($ssid) > 64) {
            throw new ValidationException('Wi-Fi network SSID is required (1–64 bytes).', [
                'ssid' => 'Enter a valid Wi-Fi network name (SSID).',
            ]);
        }

        // Map WPA2 / WPA3-SAE personal representations to standard T:WPA token recognized by iOS & Android
        $authType = match ($encryptionRaw) {
            'WPA', 'WPA2', 'WPA3', 'WPA/WPA2', 'WPA2/WPA3' => 'WPA',
            'WEP' => 'WEP',
            'NONE', 'OPEN', 'NOPASS', '' => 'nopass',
            default => throw new ValidationException('Unsupported Wi-Fi authentication type.', [
                'encryption' => 'Choose WPA/WPA2/WPA3, WEP, or Open (nopass).',
            ]),
        };

        if ($authType !== 'nopass' && $password === '') {
            throw new ValidationException('Wi-Fi password is required for secured networks.', [
                'password' => 'Enter the network password or choose Open network.',
            ]);
        }

        if ($authType === 'WPA' && (strlen($password) < 8 || strlen($password) > 64)) {
            throw new ValidationException('WPA/WPA2/WPA3 passwords must be 8 to 63 ASCII characters (or 64 hex digits).', [
                'password' => 'WPA password must be between 8 and 64 characters.',
            ]);
        }

        $payload = 'WIFI:T:' . $authType . ';S:' . self::escape($ssid) . ';';
        if ($authType !== 'nopass') {
            $payload .= 'P:' . self::escape($password) . ';';
        }
        if ($hidden) {
            $payload .= 'H:true;';
        }

        return $payload . ';';
    }

    public static function escape(string $value): string
    {
        return strtr($value, [
            '\\' => '\\\\',
            ';' => '\;',
            ',' => '\,',
            ':' => '\:',
            '"' => '\"',
        ]);
    }
}
