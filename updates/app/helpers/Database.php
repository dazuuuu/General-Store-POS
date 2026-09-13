<?php
// app/helpers/Database.php
// Single source of the PDO connection. Replaces the database.php / db_connect.php
// duplication so there is exactly one place where the connection (and, through the
// base Model, the tenant scope) is established.

class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $cfg = require ROOT_PATH . '/app/config/database.php';

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $cfg['host'],
                $cfg['dbname'],
                $cfg['charset'] ?? 'utf8mb4'
            );

            self::$pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // fail loudly
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                 // real prepared stmts
            ]);
            // Match app.php's date_default_timezone_set('Africa/Nairobi') — a
            // fixed +03:00 offset (Kenya has no DST) so NOW()/CURDATE() in
            // MySQL always agree with PHP's time()/date(), regardless of the
            // server OS's own timezone. Without this, MySQL defaults to
            // "SYSTEM" (whatever the host happens to be set to), which is
            // exactly the mismatch that made offers look inactive.
            self::$pdo->exec("SET time_zone = '+03:00'");
        }
        return self::$pdo;
    }

    /** For tests: inject a pre-built PDO (e.g. a throwaway test database). */
    public static function setPdo(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }
}