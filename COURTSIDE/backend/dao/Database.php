<?php
/**
 * Creates the single PDO connection used by all DAO classes.
 * Credentials come from backend/config/config.php (not committed to Git).
 */
class Database
{
    private static ?PDO $connection = null;

    public static function connect(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $configFile = __DIR__ . '/../config/config.php';
        if (!is_file($configFile)) {
            throw new RuntimeException('Missing backend/config/config.php. Copy config.example.php and fill in your values.');
        }
        $db = (require $configFile)['db'];

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $db['host'], $db['port'], $db['name'], $db['charset']
        );

        try {
            self::$connection = new PDO($dsn, $db['user'], $db['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errors become exceptions
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // rows as associative arrays
                PDO::ATTR_EMULATE_PREPARES   => false,                    // real prepared statements
                PDO::MYSQL_ATTR_FOUND_ROWS   => true,                     // rowCount() = matched rows, not only changed rows
            ]);
        } catch (PDOException $e) {
            // Do not chain $e: it could expose host/user details to callers.
            throw new RuntimeException('Database connection failed.');
        }

        return self::$connection;
    }
}
