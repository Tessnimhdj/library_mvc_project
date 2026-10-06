<?php

include_once __DIR__ . '/Config.php';

class Database {
    private static $dbInstance = null;

    public static function connect() {
        $dsn = "mysql:host=" . server_name . ";dbname=" . database_name;
        if (self::$dbInstance === null) {
            try {
                self::$dbInstance = new PDO($dsn, user_name, password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]);
            } catch (PDOException $e) {
                self::logError('Database connection failed: ' . $e->getMessage());
                if (!headers_sent()) {
                    http_response_code(500);
                }
                exit('Could not connect to the database.');
            }
        }
        return self::$dbInstance;
    }

    private static function logError(string $message): void
    {
        error_log($message);

        $logFile = __DIR__ . '/../Core/logs/errors.log';
        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $logFile,
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
