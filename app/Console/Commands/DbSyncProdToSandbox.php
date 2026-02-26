<?php

namespace App\Console\Commands;

use App\Support\DatabaseProfileManager;
use Illuminate\Console\Command;
use InvalidArgumentException;
use PDO;
use PDOException;

class DbSyncProdToSandbox extends Command
{
    protected $signature = 'db:sync-prod-to-sandbox {--force : Overwrite sandbox DB without confirmation}';

    protected $description = 'Clone production data into sandbox (tables, rows and views) using direct MySQL connections.';

    public function handle(DatabaseProfileManager $profileManager): int
    {
        try {
            $prodMissing = $profileManager->validateProfile('production');
            $sandboxMissing = $profileManager->validateProfile('sandbox');
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        if ($prodMissing !== []) {
            $this->error('Production profile incomplete. Missing: ' . implode(', ', $prodMissing));
            return self::FAILURE;
        }

        if ($sandboxMissing !== []) {
            $this->error('Sandbox profile incomplete. Missing: ' . implode(', ', $sandboxMissing));
            return self::FAILURE;
        }

        $prod = $profileManager->getProfileConfig('production');
        $sandbox = $profileManager->getProfileConfig('sandbox');

        if (!$this->option('force')) {
            $question = sprintf(
                'This will DELETE all data from sandbox DB [%s] and replace it with production [%s]. Continue?',
                $sandbox['database'],
                $prod['database']
            );

            if (!$this->confirm($question, false)) {
                $this->warn('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        try {
            $prodPdo = $this->connect($prod, true);
            $sandboxAdminPdo = $this->connect($sandbox, false);

            $this->recreateSandboxDatabase($sandboxAdminPdo, $sandbox['database']);

            $sandboxPdo = $this->connect($sandbox, true);
            $this->cloneTablesAndData($prodPdo, $sandboxPdo);
            $this->cloneViews($prodPdo, $sandboxPdo);
        } catch (PDOException $exception) {
            $this->error('Database sync failed: ' . $exception->getMessage());
            return self::FAILURE;
        }

        $this->info('Production data copied to sandbox successfully.');

        return self::SUCCESS;
    }

    private function connect(array $config, bool $withDatabase): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;%scharset=utf8mb4',
            $config['host'],
            $config['port'],
            $withDatabase ? 'dbname=' . $config['database'] . ';' : ''
        );

        return new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
        ]);
    }

    private function recreateSandboxDatabase(PDO $sandboxAdminPdo, string $database): void
    {
        $databaseName = $this->quoteIdentifier($database);
        $sandboxAdminPdo->exec('DROP DATABASE IF EXISTS ' . $databaseName);
        $sandboxAdminPdo->exec('CREATE DATABASE ' . $databaseName . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    private function cloneTablesAndData(PDO $prodPdo, PDO $sandboxPdo): void
    {
        $sandboxPdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $prodPdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);

        $totalTables = count($tables);
        $this->info("Copying {$totalTables} tables...");
        $bar = $this->output->createProgressBar($totalTables);
        $bar->start();

        try {
            foreach ($tables as $tableRow) {
                $table = (string) $tableRow[0];
                $tableName = $this->quoteIdentifier($table);

                $create = $prodPdo->query('SHOW CREATE TABLE ' . $tableName)->fetch(PDO::FETCH_NUM);
                $createSql = (string) ($create[1] ?? '');
                $sandboxPdo->exec($createSql);

                $select = $prodPdo->query('SELECT * FROM ' . $tableName);
                $columnCount = $select->columnCount();

                if ($columnCount > 0) {
                    $columns = [];

                    for ($i = 0; $i < $columnCount; $i++) {
                        $meta = $select->getColumnMeta($i);
                        $columns[] = $meta['name'];
                    }

                    $quotedColumns = array_map(fn ($column) => $this->quoteIdentifier((string) $column), $columns);
                    $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                    $insertSql = sprintf(
                        'INSERT INTO %s (%s) VALUES (%s)',
                        $tableName,
                        implode(', ', $quotedColumns),
                        $placeholders
                    );

                    $sandboxPdo->beginTransaction();
                    $insert = $sandboxPdo->prepare($insertSql);

                    while ($row = $select->fetch(PDO::FETCH_ASSOC)) {
                        $insert->execute(array_values($row));
                    }

                    $sandboxPdo->commit();
                }

                $bar->advance();
            }
        } finally {
            $sandboxPdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function cloneViews(PDO $prodPdo, PDO $sandboxPdo): void
    {
        $views = $prodPdo->query("SHOW FULL TABLES WHERE Table_type = 'VIEW'")->fetchAll(PDO::FETCH_NUM);

        if ($views === []) {
            return;
        }

        $this->info('Recreating views...');

        foreach ($views as $viewRow) {
            $view = (string) $viewRow[0];
            $viewName = $this->quoteIdentifier($view);

            $sandboxPdo->exec('DROP VIEW IF EXISTS ' . $viewName);

            $create = $prodPdo->query('SHOW CREATE VIEW ' . $viewName)->fetch(PDO::FETCH_ASSOC);
            $createViewSql = (string) ($create['Create View'] ?? '');
            $createViewSql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s+/i', '', $createViewSql) ?? $createViewSql;

            if ($createViewSql !== '') {
                $sandboxPdo->exec($createViewSql);
            }
        }
    }

    private function quoteIdentifier(string $value): string
    {
        return '`' . str_replace('`', '``', $value) . '`';
    }
}
