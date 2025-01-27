<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class PoliciesModel extends Model
{
    protected $table = 'policies';
    protected $primaryKey = 'policy_id';

    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    private function createTables()
    {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS policies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                policy_id VARCHAR(24) NOT NULL,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                settings JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_policy_id (policy_id),
                INDEX idx_api_key (api_key_id),
                UNIQUE KEY uk_policy_api (policy_id, api_key_id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            return true;

        } catch (\Exception $e) {
            Logger::error('Failed to create policies table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function savePolicy($policy)
    {
        if (empty($policy['id']) || empty($policy['name'])) {
            Logger::error('Invalid policy data', [
                'policy' => $policy
            ]);
            throw new \Exception('Invalid policy data: missing required fields');
        }

        try {
            $this->db->beginTransaction();

            // Primeiro verifica se a política já existe
            $stmt = $this->db->prepare("SELECT policy_id FROM policies WHERE policy_id = ?");
            $stmt->execute([$policy['id']]);
            $exists = $stmt->fetch();

            $policyData = [
                'policy_id' => $policy['id'],
                'name' => $policy['name'],
                'settings' => json_encode($policy['settings'] ?? [])
            ];

            if ($exists) {
                // Se existe, atualiza
                $sql = "UPDATE policies SET 
                        name = :name,
                        settings = :settings
                        WHERE policy_id = :policy_id";
            } else {
                // Se não existe, insere
                $sql = "INSERT INTO policies (
                    policy_id, name, settings
                ) VALUES (
                    :policy_id, :name, :settings
                )";
            }

            Logger::debug('Saving policy data', [
                'policy_id' => $policy['id'],
                'name' => $policy['name'],
                'operation' => $exists ? 'update' : 'insert'
            ]);

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($policyData);

            if (!$result) {
                throw new \Exception('Failed to execute policy save query');
            }

            $this->db->commit();
            
            Logger::debug('Policy saved successfully', [
                'policy_id' => $policy['id'],
                'name' => $policy['name'],
                'operation' => $exists ? 'update' : 'insert'
            ]);

            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to save policy', [
                'error' => $e->getMessage(),
                'policy_id' => $policy['id'] ?? null, 
                'name' => $policy['name'] ?? null
            ]);
            throw $e;
        }
    }

    public function getPolicyDetails($policyId)
    {
        try {
            // Busca a política principal
            $policy = $this->find($policyId);
            if (!$policy) {
                return null;
            }

            // Busca as configurações específicas
            $stmt = $this->db->prepare(
                "SELECT module, settings 
                FROM policy_settings 
                WHERE policy_id = ?"
            );
            $stmt->execute([$policyId]);
            $settings = $stmt->fetchAll();

            // Formata as configurações
            $formattedSettings = [];
            foreach ($settings as $setting) {
                $formattedSettings[$setting['module']] = json_decode($setting['settings'], true);
            }

            return [
                'policyId' => $policy['policy_id'],
                'name' => $policy['name'],
                'description' => $policy['description'],
                'parentId' => $policy['parent_id'],
                'type' => $policy['type'],
                'settings' => $formattedSettings,
                'inheritanceType' => $policy['inheritance_type']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get policy details', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPoliciesList($page = 1, $perPage = 30)
    {
        try {
            $offset = ($page - 1) * $perPage;
            
            $stmt = $this->db->prepare(
                "SELECT SQL_CALC_FOUND_ROWS * FROM {$this->table} 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?"
            );
            
            $stmt->execute([$perPage, $offset]);
            $items = $stmt->fetchAll();

            // Get total count
            $total = $this->db->query("SELECT FOUND_ROWS()")->fetchColumn();

            // Format items
            $formattedItems = [];
            foreach ($items as $item) {
                $formattedItems[] = [
                    'policyId' => $item['policy_id'],
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'parentId' => $item['parent_id'],
                    'type' => $item['type'],
                    'settings' => json_decode($item['settings'], true),
                    'inheritanceType' => $item['inheritance_type']
                ];
            }

            return [
                'items' => $formattedItems,
                'total' => $total,
                'page' => $page,
                'perPage' => $perPage
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get policies list', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredPolicies($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['api_key_id'])) {
                $query .= " AND api_key_id = ?";
                $params[] = $filters['api_key_id'];
            }

            if (!empty($filters['name'])) {
                $query .= " AND name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['type'])) {
                $query .= " AND type = ?";
                $params[] = $filters['type'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered policies', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function savePolicyDetails($policyId, $details)
    {
        try {
            if (empty($policyId) || empty($details)) {
                throw new \Exception('Invalid policy details data');
            }

            if (!isset($details['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $this->db->beginTransaction();

            $policyData = [
                'policy_id' => $policyId,
                'api_key_id' => $details['api_key_id'],
                'name' => $details['name'] ?? '',
                'settings' => json_encode($details['settings'] ?? [])
            ];

            $sql = "INSERT INTO policies (
                policy_id, api_key_id, name, settings
            ) VALUES (
                :policy_id, :api_key_id, :name, :settings
            ) ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                settings = VALUES(settings),
                api_key_id = VALUES(api_key_id),
                updated_at = CURRENT_TIMESTAMP";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($policyData);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to save policy details', [
                'error' => $e->getMessage(),
                'policy_id' => $policyId
            ]);
            throw $e;
        }
    }

    public function findByApiKeyId($apiKeyId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE api_key_id = ?");
            $stmt->execute([$apiKeyId]);
            $results = $stmt->fetchAll();

            // Decodifica os campos JSON para cada resultado
            foreach ($results as &$result) {
                $result['settings'] = json_decode($result['settings'], true);
            }

            return $results;
        } catch (\PDOException $e) {
            Logger::error('Failed to find policies by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
