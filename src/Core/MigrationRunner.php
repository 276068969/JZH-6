<?php

declare(strict_types=1);

namespace App\Core;

use PDO;

final class MigrationRunner
{
    private PDO $db;
    private string $migrationsDir;

    public function __construct(PDO $db, string $migrationsDir)
    {
        $this->db = $db;
        $this->migrationsDir = rtrim($migrationsDir, '/\\');
    }

    public function runAllPendingMigrations(): void
    {
        $this->ensureMigrationsTableExists();

        $executedMigrations = $this->getExecutedMigrations();
        $pendingMigrations = $this->findPendingMigrations($executedMigrations);

        foreach ($pendingMigrations as $migration) {
            $this->executeMigration($migration);
        }
    }

    private function ensureMigrationsTableExists(): void
    {
        $this->db->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    private function getExecutedMigrations(): array
    {
        $stmt = $this->db->query('SELECT migration FROM migrations ORDER BY migration ASC');
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function findPendingMigrations(array $executedMigrations): array
    {
        if (!is_dir($this->migrationsDir)) {
            return [];
        }

        $allFiles = glob($this->migrationsDir . '/*.sql');
        if ($allFiles === false) {
            return [];
        }

        $pending = [];
        foreach ($allFiles as $filePath) {
            $fileName = basename($filePath);
            if (!in_array($fileName, $executedMigrations, true)) {
                $pending[] = [
                    'name' => $fileName,
                    'path' => $filePath,
                ];
            }
        }

        usort($pending, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $pending;
    }

    private function executeMigration(array $migration): void
    {
        $sql = file_get_contents($migration['path']);
        if ($sql === false) {
            throw new \RuntimeException("无法读取迁移文件: {$migration['name']}");
        }

        $this->db->beginTransaction();
        try {
            $this->executeMultiStatementSql($sql);

            $stmt = $this->db->prepare('INSERT INTO migrations (migration) VALUES (:migration)');
            $stmt->execute(['migration' => $migration['name']]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw new \RuntimeException("迁移失败 [{$migration['name']}]: " . $e->getMessage(), 0, $e);
        }
    }

    private function executeMultiStatementSql(string $sql): void
    {
        $sql = trim($sql);
        if ($sql === '') {
            return;
        }

        $statements = $this->splitStatements($sql);

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement !== '') {
                $this->db->exec($statement);
            }
        }
    }

    private function splitStatements(string $sql): array
    {
        $statements = [];
        $currentStatement = '';
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $inBacktick = false;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            $nextChar = $i + 1 < $length ? $sql[$i + 1] : '';

            if ($char === '\\' && ($nextChar === "'" || $nextChar === '"' || $nextChar === '`')) {
                $currentStatement .= $char . $nextChar;
                $i++;
                continue;
            }

            if ($char === "'" && !$inDoubleQuote && !$inBacktick) {
                $inSingleQuote = !$inSingleQuote;
            } elseif ($char === '"' && !$inSingleQuote && !$inBacktick) {
                $inDoubleQuote = !$inDoubleQuote;
            } elseif ($char === '`' && !$inSingleQuote && !$inDoubleQuote) {
                $inBacktick = !$inBacktick;
            }

            if ($char === ';' && !$inSingleQuote && !$inDoubleQuote && !$inBacktick) {
                $statements[] = $currentStatement;
                $currentStatement = '';
            } else {
                $currentStatement .= $char;
            }
        }

        if (trim($currentStatement) !== '') {
            $statements[] = $currentStatement;
        }

        return $statements;
    }
}
