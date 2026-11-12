<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Support\DatabaseManager;
use DateTimeImmutable;
use DateTimeZone;

final class DynamicLinkRepository
{
    public function __construct(
        private readonly DatabaseManager $db
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByShortCode(string $shortCode): ?array
    {
        $stmt = $this->db->connection()->prepare(
            'SELECT id, short_code, destination_url, token_hash, is_active, scan_count, created_at, updated_at, last_scanned_at
             FROM dynamic_links
             WHERE short_code = :code
             LIMIT 1'
        );
        $stmt->execute(['code' => $shortCode]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function create(string $shortCode, string $destinationUrl, string $tokenHash): int
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $stmt = $this->db->connection()->prepare(
            'INSERT INTO dynamic_links (short_code, destination_url, token_hash, is_active, scan_count, created_at, updated_at)
             VALUES (:code, :url, :hash, 1, 0, :created, :updated)'
        );
        $stmt->execute([
            'code' => $shortCode,
            'url' => $destinationUrl,
            'hash' => $tokenHash,
            'created' => $now,
            'updated' => $now,
        ]);

        return (int) $this->db->connection()->lastInsertId();
    }

    public function updateDestinationAndStatus(int $id, string $destinationUrl, bool $isActive): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $stmt = $this->db->connection()->prepare(
            'UPDATE dynamic_links
             SET destination_url = :url, is_active = :active, updated_at = :updated
             WHERE id = :id'
        );
        $stmt->execute([
            'url' => $destinationUrl,
            'active' => $isActive ? 1 : 0,
            'updated' => $now,
            'id' => $id,
        ]);
    }

    public function updateTokenHash(int $id, string $newTokenHash): void
    {
        $now = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM);
        $stmt = $this->db->connection()->prepare(
            'UPDATE dynamic_links SET token_hash = :hash, updated_at = :updated WHERE id = :id'
        );
        $stmt->execute([
            'hash' => $newTokenHash,
            'updated' => $now,
            'id' => $id,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->connection()->prepare('DELETE FROM dynamic_links WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public function recordScan(
        int $linkId,
        bool $analyticsEnabled,
        string $deviceCategory,
        string $countryCode,
        int $retentionDays
    ): void {
        $pdo = $this->db->connection();
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);
        $nowIso = $now->format(DATE_ATOM);
        $today = $now->format('Y-m-d');

        $update = $pdo->prepare(
            'UPDATE dynamic_links
             SET scan_count = scan_count + 1, last_scanned_at = :scanned_at
             WHERE id = :id'
        );
        $update->execute([
            'scanned_at' => $nowIso,
            'id' => $linkId,
        ]);

        if (!$analyticsEnabled) {
            return;
        }

        $rollup = $pdo->prepare(
            'INSERT INTO dynamic_scan_rollups (link_id, scan_date, device_category, country_code, scans)
             VALUES (:link_id, :scan_date, :device, :country, 1)
             ON CONFLICT(link_id, scan_date, device_category, country_code)
             DO UPDATE SET scans = scans + 1'
        );
        $rollup->execute([
            'link_id' => $linkId,
            'scan_date' => $today,
            'device' => $deviceCategory,
            'country' => $countryCode,
        ]);

        if ($retentionDays > 0 && random_int(1, 50) === 1) {
            $cutoff = $now->modify('-' . $retentionDays . ' days')->format('Y-m-d');
            $prune = $pdo->prepare('DELETE FROM dynamic_scan_rollups WHERE scan_date < :cutoff');
            $prune->execute(['cutoff' => $cutoff]);
        }
    }

    /**
     * @return array{
     *   today_scans: int,
     *   weekly_scans: int,
     *   monthly_scans: int,
     *   daily_series: list<array{date: string, scans: int}>,
     *   devices: list<array{device: string, scans: int}>,
     *   countries: list<array{country: string, scans: int}>
     * }
     */
    public function getAggregatedAnalytics(int $linkId): array
    {
        $pdo = $this->db->connection();
        $utc = new DateTimeZone('UTC');
        $now = new DateTimeImmutable('now', $utc);

        $today = $now->format('Y-m-d');
        $sevenDaysAgo = $now->modify('-6 days')->format('Y-m-d');
        $thirtyDaysAgo = $now->modify('-29 days')->format('Y-m-d');

        $windowStmt = $pdo->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN scan_date = :today THEN scans ELSE 0 END), 0) AS today_scans,
                COALESCE(SUM(CASE WHEN scan_date >= :week THEN scans ELSE 0 END), 0) AS weekly_scans,
                COALESCE(SUM(CASE WHEN scan_date >= :month THEN scans ELSE 0 END), 0) AS monthly_scans
             FROM dynamic_scan_rollups
             WHERE link_id = :id'
        );
        $windowStmt->execute([
            'today' => $today,
            'week' => $sevenDaysAgo,
            'month' => $thirtyDaysAgo,
            'id' => $linkId,
        ]);
        /** @var array<string, mixed> $summary */
        $summary = $windowStmt->fetch() ?: [];

        $dailyStmt = $pdo->prepare(
            'SELECT scan_date AS date, SUM(scans) AS scans
             FROM dynamic_scan_rollups
             WHERE link_id = :id AND scan_date >= :month
             GROUP BY scan_date
             ORDER BY scan_date ASC'
        );
        $dailyStmt->execute([
            'id' => $linkId,
            'month' => $thirtyDaysAgo,
        ]);
        $dailyRows = $dailyStmt->fetchAll();
        $dailySeries = [];
        foreach ($dailyRows as $r) {
            if (is_array($r)) {
                $dailySeries[] = [
                    'date' => (string) ($r['date'] ?? ''),
                    'scans' => (int) ($r['scans'] ?? 0),
                ];
            }
        }

        $deviceStmt = $pdo->prepare(
            'SELECT device_category AS device, SUM(scans) AS scans
             FROM dynamic_scan_rollups
             WHERE link_id = :id
             GROUP BY device_category
             ORDER BY scans DESC'
        );
        $deviceStmt->execute(['id' => $linkId]);
        $deviceRows = $deviceStmt->fetchAll();
        $devices = [];
        foreach ($deviceRows as $r) {
            if (is_array($r)) {
                $devices[] = [
                    'device' => (string) ($r['device'] ?? 'other'),
                    'scans' => (int) ($r['scans'] ?? 0),
                ];
            }
        }

        $countryStmt = $pdo->prepare(
            "SELECT country_code AS country, SUM(scans) AS scans
             FROM dynamic_scan_rollups
             WHERE link_id = :id AND country_code != 'XX'
             GROUP BY country_code
             ORDER BY scans DESC
             LIMIT 15"
        );
        $countryStmt->execute(['id' => $linkId]);
        $countryRows = $countryStmt->fetchAll();
        $countries = [];
        foreach ($countryRows as $r) {
            if (is_array($r)) {
                $countries[] = [
                    'country' => (string) ($r['country'] ?? ''),
                    'scans' => (int) ($r['scans'] ?? 0),
                ];
            }
        }

        return [
            'today_scans' => (int) ($summary['today_scans'] ?? 0),
            'weekly_scans' => (int) ($summary['weekly_scans'] ?? 0),
            'monthly_scans' => (int) ($summary['monthly_scans'] ?? 0),
            'daily_series' => $dailySeries,
            'devices' => $devices,
            'countries' => $countries,
        ];
    }
}
