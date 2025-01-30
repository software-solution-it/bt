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
            is_active TINYINT(1) DEFAULT 1,
            description TEXT,
            service_type_id INT,
            last_used_at TIMESTAMP NULL,
            expires_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_api_key (api_key),
            INDEX idx_name (name),
            INDEX idx_is_active (is_active),
            INDEX idx_service_type (service_type_id),
            INDEX idx_created (created_at),
            INDEX idx_updated (updated_at),
            FOREIGN KEY (service_type_id) REFERENCES service_type_domain(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci";

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            Logger::error('Failed to create api_keys table', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getAllKeys($type = 'all')
    {
        try {
            $query = "
                SELECT ak.*, c.name as company_name, std.type as service_type 
                FROM {$this->table} ak
                LEFT JOIN companies c ON ak.id = c.api_key_id
                LEFT JOIN service_type_domain std ON ak.service_type_id = std.id
                WHERE ak.is_active = 1
            ";
            
            $params = [];
            
            if ($type !== 'all') {
                $query .= " AND std.type = ?";
                $params[] = $type;
            }
            
            $query .= " ORDER BY ak.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
        } catch (\PDOException $e) {
            Logger::error('Failed to get API keys', [
                'error' => $e->getMessage(),
                'type' => $type
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

    public function find($id)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ak.*, std.name as service_name, std.type as service_type 
                FROM {$this->table} ak
                LEFT JOIN service_type_domain std ON ak.service_type_id = std.id 
                WHERE ak.id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch();
        } catch (\PDOException $e) {
            Logger::error('Failed to find API key', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
} 