<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class LicensingModel extends Model
{
    protected $table = 'licenses';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists()
    {
        try {
            // Tabela principal de licenças
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                license_key VARCHAR(255) NOT NULL,
                is_addon BOOLEAN DEFAULT false,
                expiry_date DATETIME NULL,
                used_slots INT NULL,
                total_slots INT NULL,
                subscription_type INT NULL,
                own_use JSON NULL,
                resell JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )";
            $this->db->exec($sql);

            // Tabela para uso mensal
            $sql = "CREATE TABLE IF NOT EXISTS license_monthly_usage (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                target_month VARCHAR(7) NOT NULL,
                used_slots INT NOT NULL,
                total_slots INT NOT NULL,
                usage_details JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY idx_month_api (target_month, api_key_id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )";
            $this->db->exec($sql);

        } catch (\PDOException $e) {
            Logger::error('Failed to create licensing tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
 
    public function syncLicenseInfo($licenseInfo, $apiKeyId)
    {
        try {
            $this->db->beginTransaction();

            // Garante tipos de dados corretos e valores padrão
            $licenseData = [
                'api_key_id' => $apiKeyId,
                'license_key' => (string)($licenseInfo['licenseKey'] ?? ''),
                'is_addon' => isset($licenseInfo['isAddon']) ? (int)$licenseInfo['isAddon'] : 0,
                'expiry_date' => !empty($licenseInfo['expiryDate']) ? 
                    date('Y-m-d H:i:s', strtotime($licenseInfo['expiryDate'])) : null,
                'used_slots' => isset($licenseInfo['usedSlots']) ? (int)$licenseInfo['usedSlots'] : 0,
                'total_slots' => isset($licenseInfo['totalSlots']) ? (int)$licenseInfo['totalSlots'] : 0,
                'subscription_type' => isset($licenseInfo['subscriptionType']) ? 
                    (int)$licenseInfo['subscriptionType'] : null,
                'own_use' => isset($licenseInfo['ownUse']) ? 
                    json_encode($licenseInfo['ownUse']) : null,
                'resell' => isset($licenseInfo['resell']) ? 
                    json_encode($licenseInfo['resell']) : null
            ];

            // Log dos dados formatados para debug
            Logger::debug('Formatted license data', [
                'original' => $licenseInfo,
                'formatted' => $licenseData
            ]);

            // Verifica se já existe uma licença para este api_key_id
            $stmt = $this->db->prepare("SELECT id FROM {$this->table} WHERE api_key_id = ? LIMIT 1");
            $stmt->execute([$apiKeyId]);
            $existingLicense = $stmt->fetch();

            if ($existingLicense) {
                $this->update($existingLicense['id'], $licenseData);
            } else {
                $this->create($licenseData);
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync license info', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $licenseData ?? null
            ]);
            throw $e;
        }
    }

    // Sobrescreve o método update da classe pai para garantir tipos corretos
    public function update($id, array $data)
    {
        try {
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                if ($value === null) {
                    $fields[] = "$key = NULL";
                } else {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                }
            }
            $values[] = $id;

            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            
            Logger::debug('Executing update query', [
                'sql' => $sql,
                'values' => $values
            ]);

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($values);
        } catch (\Exception $e) {
            Logger::error('Update failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    // Sobrescreve o método create da classe pai para garantir tipos corretos
    public function create(array $data)
    {
        try {
            $fields = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO {$this->table} ($fields) VALUES ($placeholders)";
            
            Logger::debug('Executing insert query', [
                'sql' => $sql,
                'values' => array_values($data)
            ]);

            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array_values($data));
        } catch (\Exception $e) {
            Logger::error('Insert failed', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function getLicenseInfo($apiKeyId)
    { 
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE api_key_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$apiKeyId]);
            $result = $stmt->fetch();

            if ($result) {
                // Decodifica os campos JSON
                $result['own_use'] = json_decode($result['own_use'], true);
                $result['resell'] = json_decode($result['resell'], true);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to get license info', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncMonthlyUsage($targetMonth, $usageData, $apiKeyId)
    {
        try {
            $this->db->beginTransaction();
            $formattedMonth = date('Y-m', strtotime(str_replace('/', '-', $targetMonth) . '-01'));
            
            // Verifica se já existe registro para o mês e api_key_id
            $stmt = $this->db->prepare( 
                "SELECT id FROM license_monthly_usage WHERE target_month = ? AND api_key_id = ?"
            );
            $stmt->execute([$formattedMonth, $apiKeyId]);
            $existing = $stmt->fetch();

            if ($existing) {
                $stmt = $this->db->prepare(
                    "UPDATE license_monthly_usage 
                    SET total_endpoints = ?, active_endpoints = ?, usage_details = ?
                    WHERE target_month = ? AND api_key_id = ?"
                );
                $stmt->execute([
                    $usageData['totalEndpoints'],
                    $usageData['activeEndpoints'],
                    json_encode($usageData['details'] ?? []),
                    $formattedMonth,
                    $apiKeyId
                ]);
            } else {
                $stmt = $this->db->prepare(
                    "INSERT INTO license_monthly_usage 
                    (target_month, total_endpoints, active_endpoints, usage_details, api_key_id) 
                    VALUES (?, ?, ?, ?, ?)"
                );
                $stmt->execute([
                    $formattedMonth,
                    $usageData['totalEndpoints'],
                    $usageData['activeEndpoints'],
                    json_encode($usageData['details'] ?? []),
                    $apiKeyId
                ]);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function getMonthlyUsage($targetMonth)
    {
        try {
            $formattedMonth = date('Y-m', strtotime(str_replace('/', '-', $targetMonth) . '-01'));
            
            $stmt = $this->db->prepare(
                "SELECT * FROM license_monthly_usage WHERE target_month = ?"
            );
            $stmt->execute([$formattedMonth]);
            
            $result = $stmt->fetch();
            if ($result) {
                $result['usage_details'] = json_decode($result['usage_details'], true);
            }
            
            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to get monthly usage', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredLicenses($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['license_key'])) {
                $query .= " AND license_key LIKE ?";
                $params[] = '%' . $filters['license_key'] . '%';
            }

            if (!empty($filters['is_addon'])) {
                $query .= " AND is_addon = ?";
                $params[] = (bool)$filters['is_addon'];
            }

            if (!empty($filters['expiry_date_before'])) {
                $query .= " AND expiry_date <= ?";
                $params[] = $filters['expiry_date_before'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered licenses', [
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
            $results = $stmt->fetchAll();

            // Decodifica os campos JSON para cada resultado
            foreach ($results as &$result) {
                $result['own_use'] = json_decode($result['own_use'], true);
                $result['resell'] = json_decode($result['resell'], true);
            }

            return $results;
        } catch (\PDOException $e) {
            Logger::error('Failed to find licenses by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
