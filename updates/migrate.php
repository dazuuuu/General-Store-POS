<?php

declare(strict_types=1);

// CLI runner for the current one-shot migration.
// Usage:
//   php migrate.php
//   php migrate.php --file=databases/full_migration_db.sql
//   php migrate.php --database=duaqabe_db
//   php migrate.php --dry-run

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This migration runner must be run from the command line.\n");
}

$root = __DIR__;
$configFile = $root . '/app/config/database.php';

if (!is_file($configFile)) {
    exit("Database config not found: {$configFile}\n");
}

$config = require $configFile;

$options = getopt('', ['file::', 'database::', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Usage: php migrate.php [--file=path/to/file.sql] [--database=name] [--dry-run]\n";
    exit(0);
}

$migrationFile = (string)($options['file'] ?? 'databases/full_migration_db.sql');
$migrationPath = substr($migrationFile, 0, 1) === '/'
    ? $migrationFile
    : $root . '/' . ltrim($migrationFile, '/');

$database = (string)($options['database'] ?? ($config['dbname'] ?? ''));
$dryRun = array_key_exists('dry-run', $options);

if ($database === '') {
    exit("No database name provided. Set app/config/database.php or pass --database=your_db.\n");
}

if (!is_file($migrationPath)) {
    exit("Migration file not found: {$migrationPath}\n");
}

/**
 * Split SQL into executable statements while respecting quoted strings and
 * comments. This is enough for the app's migration files, which do not use
 * DELIMITER-based stored routines.
 */
function splitSqlStatements(string $sql): array
{
    $statements = [];
    $buffer = '';
    $length = strlen($sql);
    $quote = null;
    $lineComment = false;
    $blockComment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = $i + 1 < $length ? $sql[$i + 1] : '';

        if ($lineComment) {
            $buffer .= $char;
            if ($char === "\n") {
                $lineComment = false;
            }
            continue;
        }

        if ($blockComment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $blockComment = false;
            }
            continue;
        }

        if ($quote !== null) {
            $buffer .= $char;
            if ($char === '\\' && $next !== '') {
                $buffer .= $next;
                $i++;
                continue;
            }
            if ($char === $quote) {
                $quote = null;
            }
            continue;
        }

        if ($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) {
            $buffer .= $char . $next;
            $i++;
            $lineComment = true;
            continue;
        }

        if ($char === '#') {
            $buffer .= $char;
            $lineComment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $buffer .= $char . $next;
            $i++;
            $blockComment = true;
            continue;
        }

        if ($char === '\'' || $char === '"' || $char === '`') {
            $buffer .= $char;
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            $trimmed = trim($buffer);
            if ($trimmed !== '' && trim(preg_replace('/^\s*(--|#).*$/m', '', $trimmed)) !== '') {
                $statements[] = $trimmed;
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    $trimmed = trim($buffer);
    if ($trimmed !== '' && trim(preg_replace('/^\s*(--|#).*$/m', '', $trimmed)) !== '') {
        $statements[] = $trimmed;
    }

    return $statements;
}

function quoteIdentifier(string $identifier): string
{
    return '`' . str_replace('`', '``', $identifier) . '`';
}

function isCreateDatabaseStatement(string $statement): bool
{
    return preg_match('/^\s*(?:--[^\n]*\n\s*)*CREATE\s+DATABASE\b/i', $statement) === 1;
}

function isUseStatement(string $statement): bool
{
    return preg_match('/^\s*(?:--[^\n]*\n\s*)*USE\s+/i', $statement) === 1;
}

$sql = file_get_contents($migrationPath);
if ($sql === false) {
    exit("Could not read migration file: {$migrationPath}\n");
}

if (stripos($sql, 'DELIMITER') !== false) {
    exit("This runner does not support DELIMITER blocks. Use the mysql CLI for this file.\n");
}

$statements = splitSqlStatements($sql);

echo "Migration file: {$migrationPath}\n";
echo "Target database: {$database}\n";
echo "Statements found: " . count($statements) . "\n";

if ($dryRun) {
    echo "Dry run complete. No SQL was executed.\n";
    exit(0);
}

$charset = (string)($config['charset'] ?? 'utf8mb4');
$host = (string)($config['host'] ?? 'localhost');
$username = (string)($config['username'] ?? 'root');
$password = (string)($config['password'] ?? '');

try {
    $pdo = new PDO(
        "mysql:host={$host};charset={$charset}",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $quotedDatabase = quoteIdentifier($database);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS {$quotedDatabase} CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    $pdo->exec("USE {$quotedDatabase}");

    $ran = 0;
    $skipped = 0;
    $rewritten = 0;
    $tolerableErrorCodes = [
        1050, // table already exists
        1060, // duplicate column
        1061, // duplicate key name
        1062, // duplicate entry
        1091, // cannot drop/check missing column or key
        1826, // duplicate foreign key constraint name
    ];

    foreach ($statements as $index => $statement) {
        if (isCreateDatabaseStatement($statement) || isUseStatement($statement)) {
            $rewritten++;
            continue;
        }

        try {
            $pdo->exec($statement);
            $ran++;
        } catch (PDOException $e) {
            $mysqlCode = (int)($e->errorInfo[1] ?? 0);
            if (in_array($mysqlCode, $tolerableErrorCodes, true)) {
                $skipped++;
                continue;
            }
            if ($mysqlCode === 1054 && preg_match('/^\s*ALTER\s+TABLE\b/i', $statement) === 1) {
                $skipped++;
                continue;
            }

            $statementNumber = $index + 1;
            $preview = trim(preg_replace('/\s+/', ' ', $statement));
            $preview = substr($preview, 0, 240);

            fwrite(STDERR, "\nMigration failed at statement {$statementNumber}:\n");
            fwrite(STDERR, "{$preview}\n\n");
            fwrite(STDERR, $e->getMessage() . "\n");
            exit(1);
        }
    }

    echo "Done. {$ran} statement(s) executed.";
    if ($skipped > 0) {
        echo " {$skipped} already-applied statement(s) skipped.";
    }
    if ($rewritten > 0) {
        echo " {$rewritten} database directive(s) skipped so the configured database was used.";
    }
    echo "\n";
} catch (PDOException $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}
