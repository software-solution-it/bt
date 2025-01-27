<?php

namespace App\Config;

use PDO;
use App\Core\Logger;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $host = Environment::get('DB_HOST');
            $port = Environment::get('DB_PORT');
            $database = Environment::get('DB_DATABASE');
            $username = Environment::get('DB_USERNAME');
            $password = Environment::get('DB_PASSWORD');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";

            $this->connection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);

            Logger::debug('Database connection established');

        } catch (\PDOException $e) {
            Logger::error('Database connection failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }
}
