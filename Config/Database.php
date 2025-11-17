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
                die("Connection failed: " . $e->getMessage());
            }
        }
        return self::$dbInstance;
    }
}
