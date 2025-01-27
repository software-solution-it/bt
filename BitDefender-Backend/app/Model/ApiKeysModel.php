<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class ApiKeysModel extends Model
{
    protected $table = 'api_keys';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            api_key VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            Logger::error('Failed to create api_keys table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAllKeys()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            Logger::error('Failed to get API keys', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createKey($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO {$this->table} 
                (name, api_key, is_active) 
                VALUES (:name, :api_key, :is_active)
            ");

            $stmt->execute([
                'name' => $data['name'],
                'api_key' => $data['api_key'],
                'is_active' => $data['is_active'] ?? true
            ]);

            return $this->find($this->db->lastInsertId());
        } catch (\PDOException $e) {
            Logger::error('Failed to create API key', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateKey($id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE {$this->table} 
                SET name = :name, 
                    api_key = :api_key, 
                    is_active = :is_active 
                WHERE id = :id
            ");

            return $stmt->execute([
                'id' => $id,
                'name' => $data['name'],
                'api_key' => $data['api_key'],
                'is_active' => $data['is_active']
            ]);
        } catch (\PDOException $e) {
            Logger::error('Failed to update API key', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function deleteKey($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE id = :id");
            return $stmt->execute(['id' => $id]);
        } catch (\PDOException $e) {
            Logger::error('Failed to delete API key', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
} 