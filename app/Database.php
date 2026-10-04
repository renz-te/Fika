<?php

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = config('database.host');
            $name = config('database.name');
            $charset = config('database.charset', 'utf8mb4');
            $user = config('database.user');
            $pass = config('database.pass');

            $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";
            
            try {
                self::$instance = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+08:00'"
                ]);
            } catch (PDOException $e) {
                // In production, we don't leak the real error message
                die("Database connection failed.");
            }
        }
        return self::$instance;
    }
}
