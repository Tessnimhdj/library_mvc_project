<?php

use Core\Log;

include_once __DIR__ . '/Config.php';

class Database
{
    private static ?\PDO $connection = null;

    public static function connect(): \PDO
    {
        if (self::$connection instanceof \PDO) {
            return self::$connection;
        }

        $dsn = 'mysql:host=' . server_name . ';dbname=' . database_name . ';charset=utf8mb4';

        try {
            self::$connection = new \PDO($dsn, user_name, password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            Log::error('Database connection failed: ' . $e->getMessage());
            if (class_exists(\Core\View::class)) {
                \Core\View::renderError(500, 'Could not connect to the database.');
                exit;
            }

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=UTF-8');
            }
            echo '<p>Could not connect to the database.</p>';
            exit;
        }

        return self::$connection;
    }
}
