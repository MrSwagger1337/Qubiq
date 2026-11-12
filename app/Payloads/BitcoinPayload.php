<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;

final class BitcoinPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'bitcoin';
    }

    public function label(): string
    {
        return 'Bitcoin';
    }

    public function category(): string
    {
        return 'more';
    }

    public function isUrlWrapper(): bool
    {
        return false;
    }

    public function description(): string
    {
        return 'Generates a BIP-21 compliant bitcoin: payment URI with optional amount, label, and message.';
    }

    public function sampleData(): array
    {
        return [
            'address' => 'bc1qar0srrr7xfkvy5l643lydnw9re59gtzzwf5mdq',
            'amount' => '0.005',
            'label' => 'Qubiq Coffee Jar',
            'message' => 'Open-source appreciation',
        ];
    }

    public function encode(array $input): string
    {
        $address = isset($input['address']) && is_scalar($input['address']) ? trim((string) $input['address']) : '';
        $amountRaw = isset($input['amount']) && is_scalar($input['amount']) ? trim((string) $input['amount']) : '';
        $label = isset($input['label']) && is_scalar($input['label']) ? trim((string) $input['label']) : '';
        $message = isset($input['message']) && is_scalar($input['message']) ? trim((string) $input['message']) : '';

        if (!self::isValidBitcoinAddress($address)) {
            throw new ValidationException('Invalid Bitcoin wallet address.', [
                'address' => 'Enter a valid Legacy (1...), P2SH (3...), or SegWit/Taproot (bc1...) Bitcoin address.',
            ]);
        }

        $query = [];
        if ($amountRaw !== '') {
            if (preg_match('/^\d+(?:\.\d{1,8})?$/', $amountRaw) !== 1 || (float) $amountRaw <= 0.0) {
                throw new ValidationException('Invalid BTC amount.', [
                    'amount' => 'Amount must be a positive number with at most 8 decimal places.',
                ]);
            }
            $normalizedAmount = rtrim(rtrim($amountRaw, '0'), '.');
            $query[] = 'amount=' . $normalizedAmount;
        }
        if ($label !== '') {
            $query[] = 'label=' . rawurlencode($label);
        }
        if ($message !== '') {
            $query[] = 'message=' . rawurlencode($message);
        }

        $uri = 'bitcoin:' . $address;
        if ($query !== []) {
            $uri .= '?' . implode('&', $query);
        }

        return $uri;
    }

    public static function isValidBitcoinAddress(string $address): bool
    {
        if ($address === '') {
            return false;
        }

        // Base58Check P2PKH / P2SH format check
        if (preg_match('/^[13][a-km-zA-HJ-NP-Z1-9]{25,34}$/', $address) === 1) {
            return true;
        }

        // Bech32 / Bech32m mainnet address (bc1q... / bc1p...)
        if (preg_match('/^(bc1|BC1)[ac-hj-np-zAC-HJ-NP-Z02-9]{11,71}$/', $address) === 1) {
            // Mixed case is disallowed in Bech32
            return $address === strtolower($address) || $address === strtoupper($address);
        }

        return false;
    }
}
