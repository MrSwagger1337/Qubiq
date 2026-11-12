<?php

declare(strict_types=1);

namespace App\Payloads;

use App\Exceptions\ValidationException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class EventPayload implements PayloadEncoderInterface
{
    public function type(): string
    {
        return 'event';
    }

    public function label(): string
    {
        return 'Event';
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
        return 'Generates an RFC 5545 iCalendar (VCALENDAR / VEVENT) payload with timezone-normalized UTC timestamps.';
    }

    public function sampleData(): array
    {
        return [
            'title' => 'Qubiq Open-Source Community Call',
            'description' => 'Monthly architecture & release sync.',
            'location' => 'Online / Main Auditorium',
            'start' => '2027-03-15T15:00',
            'end' => '2027-03-15T16:00',
            'timezone' => 'UTC',
        ];
    }

    public function encode(array $input): string
    {
        $title = isset($input['title']) && is_scalar($input['title']) ? trim((string) $input['title']) : '';
        $description = isset($input['description']) && is_scalar($input['description']) ? trim((string) $input['description']) : '';
        $location = isset($input['location']) && is_scalar($input['location']) ? trim((string) $input['location']) : '';
        $startRaw = isset($input['start']) && is_scalar($input['start']) ? trim((string) $input['start']) : '';
        $endRaw = isset($input['end']) && is_scalar($input['end']) ? trim((string) $input['end']) : '';
        $tzRaw = isset($input['timezone']) && is_scalar($input['timezone']) && trim((string) $input['timezone']) !== ''
            ? trim((string) $input['timezone'])
            : 'UTC';

        if ($title === '') {
            throw new ValidationException('Calendar event title is required.', [
                'title' => 'Please enter an event title.',
            ]);
        }
        if ($startRaw === '' || $endRaw === '') {
            throw new ValidationException('Event start and end date/time are required.', [
                'start' => 'Provide both start and end date/time.',
            ]);
        }

        try {
            $tz = new DateTimeZone($tzRaw);
        } catch (Throwable) {
            throw new ValidationException('Invalid IANA time zone identifier.', [
                'timezone' => 'Use a valid IANA time zone (e.g. UTC, America/New_York, Europe/London).',
            ]);
        }

        try {
            $startDt = new DateTimeImmutable($startRaw, $tz);
            $endDt = new DateTimeImmutable($endRaw, $tz);
        } catch (Throwable) {
            throw new ValidationException('Invalid start or end date/time format.', [
                'start' => 'Use ISO 8601 date/time format (e.g. 2027-03-15T15:00).',
            ]);
        }

        if ($endDt < $startDt) {
            throw new ValidationException('Event end time must be after or equal to start time.', [
                'end' => 'End date/time cannot be earlier than start date/time.',
            ]);
        }

        $utc = new DateTimeZone('UTC');
        $dtStartUtc = $startDt->setTimezone($utc)->format('Ymd\THis\Z');
        $dtEndUtc = $endDt->setTimezone($utc)->format('Ymd\THis\Z');
        $uid = substr(hash('sha256', $title . '|' . $dtStartUtc . '|' . $dtEndUtc), 0, 16) . '@qubiq.local';

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Qubiq//QR Generator//EN',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtStartUtc,
            'DTSTART:' . $dtStartUtc,
            'DTEND:' . $dtEndUtc,
            'SUMMARY:' . self::escape($title),
        ];

        if ($location !== '') {
            $lines[] = 'LOCATION:' . self::escape($location);
        }
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:' . self::escape($description);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

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
}
