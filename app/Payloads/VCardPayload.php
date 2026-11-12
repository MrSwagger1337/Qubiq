<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;
use App\Validators\UrlSafetyValidator;

final class VCardPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'vcard';
    }

    public function label(): string
    {
        return 'vCard';
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
        return 'Generates a native vCard 3.0 digital contact card for instant address-book import.';
    }

    public function sampleData(): array
    {
        return [
            'first_name' => 'Elena',
            'last_name' => 'Rostova',
            'organization' => 'Qubiq Open Source',
            'title' => 'Principal Architect',
            'mobile_phone' => '+14155550199',
            'work_phone' => '+14155550100',
            'email' => 'elena@example.com',
            'website' => 'https://example.com',
            'street' => '548 Market St',
            'city' => 'San Francisco',
            'region' => 'CA',
            'postal_code' => '94104',
            'country' => 'USA',
            'birthday' => '1990-05-14',
            'notes' => 'Open-source privacy tools & developer infrastructure.',
        ];
    }

    public function encode(array $input): string
    {
        $firstName = $this->str($input, 'first_name');
        $lastName = $this->str($input, 'last_name');
        $org = $this->str($input, 'organization');
        $title = $this->str($input, 'title');
        $mobile = $this->str($input, 'mobile_phone');
        $workPhone = $this->str($input, 'work_phone');
        $email = $this->str($input, 'email');
        $website = $this->str($input, 'website');
        $address = $this->str($input, 'address');
        $street = $this->str($input, 'street');
        $city = $this->str($input, 'city');
        $region = $this->str($input, 'region');
        $postal = $this->str($input, 'postal_code');
        $country = $this->str($input, 'country');
        $birthday = $this->str($input, 'birthday');
        $notes = $this->str($input, 'notes');

        if ($firstName === '' && $lastName === '' && $org === '') {
            throw new ValidationException('vCard requires at least a first name, last name, or organization.', [
                'first_name' => 'Provide at least a name or organization.',
            ]);
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new ValidationException('Invalid vCard email address.', [
                'email' => 'Enter a valid email address.',
            ]);
        }

        if ($mobile !== '') {
            $mobile = PhonePayload::normalizePhone($mobile, 'mobile_phone');
        }
        if ($workPhone !== '') {
            $workPhone = PhonePayload::normalizePhone($workPhone, 'work_phone');
        }
        if ($website !== '') {
            $website = UrlSafetyValidator::normalizeWebUrl($website, 'website', true);
        }
        if ($birthday !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday) !== 1) {
            throw new ValidationException('Birthday must be in YYYY-MM-DD format.', [
                'birthday' => 'Use YYYY-MM-DD format (e.g. 1990-05-14).',
            ]);
        }

        $fullName = trim($firstName . ' ' . $lastName);
        if ($fullName === '') {
            $fullName = $org;
        }

        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'N:' . self::escape($lastName) . ';' . self::escape($firstName) . ';;;',
            'FN:' . self::escape($fullName),
        ];

        if ($org !== '') {
            $lines[] = 'ORG:' . self::escape($org);
        }
        if ($title !== '') {
            $lines[] = 'TITLE:' . self::escape($title);
        }
        if ($mobile !== '') {
            $lines[] = 'TEL;TYPE=CELL,VOICE:' . self::escape($mobile);
        }
        if ($workPhone !== '') {
            $lines[] = 'TEL;TYPE=WORK,VOICE:' . self::escape($workPhone);
        }
        if ($email !== '') {
            $lines[] = 'EMAIL;TYPE=PREF,INTERNET:' . self::escape($email);
        }
        if ($website !== '') {
            $lines[] = 'URL:' . self::escape($website);
        }

        if ($street !== '' || $city !== '' || $region !== '' || $postal !== '' || $country !== '') {
            $lines[] = sprintf(
                'ADR;TYPE=WORK:;;%s;%s;%s;%s;%s',
                self::escape($street),
                self::escape($city),
                self::escape($region),
                self::escape($postal),
                self::escape($country)
            );
        } elseif ($address !== '') {
            $lines[] = 'ADR;TYPE=WORK:;;' . self::escape($address) . ';;;;';
        }

        if ($birthday !== '') {
            $lines[] = 'BDAY:' . $birthday;
        }
        if ($notes !== '') {
            $lines[] = 'NOTE:' . self::escape($notes);
        }

        $lines[] = 'END:VCARD';

        return implode("\r\n", $lines);
    }

    public static function escape(string $value): string
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $value);

        return strtr($normalized, [
            '\\' => '\\\\',
            ';' => '\;',
            ',' => '\,',
            "\n" => '\n',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function str(array $input, string $key): string
    {
        return isset($input[$key]) && is_scalar($input[$key]) ? trim((string) $input[$key]) : '';
    }
}
