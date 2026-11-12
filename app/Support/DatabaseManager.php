<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\UnsupportedCapabilityException;
use PDO;
use Throwable;

final class DatabaseManager
{
    private ?PDO $pdo = null;

    private bool $migrated = false;

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function connection(): PDO
    {
        if ($this->pdo instanceof PDO) {
            return $this->pdo;
        }

        if (!extension_loaded('pdo_sqlite')) {
            throw new UnsupportedCapabilityException(
                'The PHP pdo_sqlite extension is required for optional Dynamic QR Codes. Static QR codes continue to work without any database.'
            );
        }

        $sqlitePath = $this->config->string('database.sqlite_path', ':memory:');
        if ($sqlitePath !== ':memory:') {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
        }

        try {
            $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec('PRAGMA foreign_keys = ON;');
            if ($sqlitePath !== ':memory:') {
                $pdo->exec('PRAGMA journal_mode = WAL;');
                $pdo->exec('PRAGMA busy_timeout = 5000;');
            }
        } catch (Throwable $e) {
            throw new UnsupportedCapabilityException('Unable to initialize SQLite storage for dynamic QR codes.');
        }

        $this->pdo = $pdo;
        $this->migrate();

        return $pdo;
    }

    public function migrate(): void
    {
        if ($this->migrated) {
            return;
        }

        $pdo = $this->pdo ?? $this->connection();
        $migrationsDir = $this->config->string('paths.migrations');
        $files = glob(rtrim($migrationsDir, '/\\') . '/*.sql');

        if (is_array($files)) {
            sort($files);
            foreach ($files as $file) {
                $sql = file_get_contents($file);
                if (is_string($sql) && trim($sql) !== '') {
                    $pdo->exec($sql);
                }
            }
        }

        $this->migrated = true;
    }
}
