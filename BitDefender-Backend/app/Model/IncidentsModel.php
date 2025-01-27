<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class IncidentsModel extends Model
{
    protected $table = 'incidents_blocklist';
    protected $primaryKey = 'hash_item_id';

    public function __construct()
    {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            hash_item_id VARCHAR(24) PRIMARY KEY,
            api_key_id INT NOT NULL,
            hash_type VARCHAR(50) NOT NULL,
            hash_value VARCHAR(255) NOT NULL,
            source_info TEXT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, 
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
        )";

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            Logger::error('Failed to create incidents_blocklist table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncBlocklistWithAPI($apiData, $apiKeyId)
    {
        try {
            $this->db->beginTransaction();

            if (isset($apiData['items']) && is_array($apiData['items'])) {
                foreach ($apiData['items'] as $item) {
                    $this->syncBlocklistItem($item, $apiKeyId);
                }
            } else if (is_array($apiData)) {
                $this->syncBlocklistItem($apiData, $apiKeyId);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync blocklist with API', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function syncBlocklistItem($apiItem, $apiKeyId)
    {
        $existingItem = $this->find($apiItem['hashItemId']);

        $itemData = [
            'api_key_id' => $apiKeyId,
            'hash_type' => $apiItem['hashType'],
            'hash_value' => $apiItem['hashValue'],
            'source_info' => $apiItem['sourceInfo'] ?? null,
            'status' => $apiItem['status'] ?? 'active'
        ];

        if ($existingItem) {
            $this->update($apiItem['hashItemId'], $itemData);
        } else {
            $itemData['hash_item_id'] = $apiItem['hashItemId'];
            $this->create($itemData);
        }
    }

    public function addToBlocklist($hashType, $hashList, $sourceInfo)
    {
        try {
            $this->db->beginTransaction();

            foreach ($hashList as $hashValue) {
                $itemData = [
                    'hash_type' => $hashType,
                    'hash_value' => $hashValue,
                    'source_info' => $sourceInfo,
                    'status' => 'active'
                ];

                // Verifica se já existe
                $stmt = $this->db->prepare("SELECT hash_item_id FROM {$this->table} WHERE hash_type = ? AND hash_value = ?");
                $stmt->execute([$hashType, $hashValue]);
                $existing = $stmt->fetch();

                if (!$existing) {
                    $this->create($itemData);
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function removeFromBlocklist($hashItemId)
    {
        try {
            return $this->update($hashItemId, ['status' => 'removed']);
        } catch (\Exception $e) {
            Logger::error('Failed to remove from blocklist', [
                'error' => $e->getMessage(),
                'hashItemId' => $hashItemId
            ]);
            throw $e;
        }
    }

    public function getBlocklistItems($page = 1, $perPage = 30)
    {
        try {
            $offset = ($page - 1) * $perPage;
            
            $stmt = $this->db->prepare(
                "SELECT * FROM {$this->table} 
                WHERE status = 'active' 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?"
            );
            
            $stmt->execute([$perPage, $offset]);
            $items = $stmt->fetchAll();

            // Get total count
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM {$this->table} WHERE status = 'active'");
            $countStmt->execute();
            $total = $countStmt->fetchColumn();

            return [
                'items' => $items,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get blocklist items', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredIncidents($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['hash_type'])) {
                $query .= " AND hash_type = ?";
                $params[] = $filters['hash_type'];
            }

            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['created_after'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['created_after'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered incidents', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function findByApiKeyId($apiKeyId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE api_key_id = ?");
            $stmt->execute([$apiKeyId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            Logger::error('Failed to find incidents by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
