<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class AccountModel extends Model
{
    protected $table = 'accounts';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id VARCHAR(24) PRIMARY KEY,
            api_key_id INT NOT NULL,
            email VARCHAR(255) NOT NULL,
            full_name VARCHAR(255) NOT NULL,
            role INT NOT NULL,
            rights JSON NULL,
            language VARCHAR(10) NULL,
            timezone VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
        )";

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            Logger::error('Failed to create accounts table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncWithAPI($apiData, $apiKeyId)
    {
        try {
            $this->db->beginTransaction();

            if (isset($apiData['items']) && is_array($apiData['items'])) {
                foreach ($apiData['items'] as $account) {
                    $this->syncAccount($account, $apiKeyId);
                }
            } else if (is_array($apiData)) {
                $this->syncAccount($apiData, $apiKeyId);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync accounts with API', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function syncAccount($apiAccount, $apiKeyId)
    {
        $existingAccount = $this->find($apiAccount['id']);

        $accountData = [
            'api_key_id' => $apiKeyId,
            'email' => $apiAccount['email'],
            'full_name' => $apiAccount['profile']['fullName'],
            'role' => $apiAccount['role'],
            'rights' => isset($apiAccount['rights']) ? json_encode($apiAccount['rights']) : null,
            'language' => $apiAccount['profile']['language'] ?? null,
            'timezone' => $apiAccount['profile']['timezone'] ?? null
        ];

        if ($existingAccount) {
            $this->update($apiAccount['id'], $accountData);
        } else {
            $accountData['id'] = $apiAccount['id'];
            $this->create($accountData);
        }
    }

    public function deleteByApiId($apiId)
    {
        return $this->delete($apiId);
    }

    public function findByEmail($email)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }

    public function getFilteredAccounts($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['email'])) {
                $query .= " AND email LIKE ?";
                $params[] = '%' . $filters['email'] . '%';
            }

            if (!empty($filters['role'])) {
                $query .= " AND role = ?";
                $params[] = $filters['role'];
            }

            if (!empty($filters['created_after'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['created_after'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered accounts', [
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
            Logger::info('Failed to find accounts by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
