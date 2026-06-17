<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class Database
{
    private static ?PDO $pdo = null;
    private static bool $migrationsRun = false;

    public static function connection(): PDO
    {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $name = getenv('DB_NAME') ?: 'ambulance_platform';
            $user = getenv('DB_USER') ?: 'ambulance_user';
            $pass = getenv('DB_PASS') ?: 'ambulance_pass';

            self::$pdo = new PDO(
                "mysql:host={$host};dbname={$name};charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );

            self::runMigrationsIfNeeded();
        }

        return self::$pdo;
    }

    private static function runMigrationsIfNeeded(): void
    {
        if (self::$migrationsRun) {
            return;
        }

        self::$migrationsRun = true;

        try {
            $migrationsDir = __DIR__ . '/../../database/migrations';
            if (is_dir($migrationsDir)) {
                $runner = new MigrationRunner(self::$pdo, $migrationsDir);
                $runner->runAllPendingMigrations();
            }
        } catch (\Throwable $e) {
            error_log('数据库迁移执行失败: ' . $e->getMessage());
        }
    }
}
