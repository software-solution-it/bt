<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class IntegrationsModel extends Model
{
    protected $table = 'aws_integrations';
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
            api_key_id INT NOT NULL,
            cross_account_role_arn VARCHAR(255) NULL,
            external_id VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            last_usage_sync TIMESTAMP NULL,
            usage_data JSON NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
        )";

        try {
            $this->db->exec($sql);

            // Tabela para armazenar o uso horário
            $this->db->exec("CREATE TABLE IF NOT EXISTS aws_hourly_usage (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                instance_id VARCHAR(50) NOT NULL,
                target_month VARCHAR(7) NOT NULL,
                usage_hours INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_month_instance (target_month, instance_id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

        } catch (\PDOException $e) {
            Logger::error('Failed to create integrations tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncHourlyUsage($targetMonth, $usageData, $apiKeyId)
    {
        try {
            $this->db->beginTransaction();

            // Limpa os dados existentes para o mês alvo e api_key_id
            $stmt = $this->db->prepare("DELETE FROM aws_hourly_usage WHERE target_month = ? AND api_key_id = ?");
            $stmt->execute([$targetMonth, $apiKeyId]);

            // Insere os novos dados
            $stmt = $this->db->prepare(
                "INSERT INTO aws_hourly_usage 
                (instance_id, target_month, usage_hours, api_key_id) 
                VALUES (?, ?, ?, ?)"
            );

            foreach ($usageData as $instance) {
                $stmt->execute([
                    $instance['instanceId'],
                    $targetMonth,
                    $instance['usageHours'],
                    $apiKeyId
                ]);
            }

            // Atualiza a última sincronização
            $this->db->prepare(
                "UPDATE {$this->table} 
                SET last_usage_sync = NOW(), 
                    usage_data = ? 
                WHERE api_key_id = ?"
            )->execute([json_encode($usageData), $apiKeyId]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync hourly usage', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateIntegrationConfig($crossAccountRoleArn, $apiKeyId)
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO {$this->table} (cross_account_role_arn, api_key_id) 
                 VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE cross_account_role_arn = ?"
            );
            return $stmt->execute([$crossAccountRoleArn, $apiKeyId, $crossAccountRoleArn]);
        } catch (\Exception $e) {
            Logger::error('Failed to update integration config', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateExternalId($externalId, $apiKeyId)
    {
        try {
            $stmt = $this->db->prepare( 
                "INSERT INTO {$this->table} (external_id, api_key_id) 
                 VALUES (?, ?) 
                 ON DUPLICATE KEY UPDATE external_id = ?"
            );
            return $stmt->execute([$externalId, $apiKeyId, $externalId]);
        } catch (\Exception $e) {
            Logger::error('Failed to update external ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getExternalId()
    {
        try {
            $stmt = $this->db->query("SELECT external_id FROM {$this->table} WHERE status = 'active' LIMIT 1");
            $result = $stmt->fetch();
            return $result ? $result['external_id'] : null;
        } catch (\Exception $e) {
            Logger::error('Failed to get external ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function disableIntegration()
    {
        try {
            return $this->db->exec("UPDATE {$this->table} SET status = 'disabled' WHERE status = 'active'");
        } catch (\Exception $e) {
            Logger::error('Failed to disable integration', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getHourlyUsage($targetMonth)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT * FROM aws_hourly_usage 
                WHERE target_month = ? 
                ORDER BY instance_id"
            );
            $stmt->execute([$targetMonth]);
            return $stmt->fetchAll();
        } catch (\Exception $e) {
            Logger::error('Failed to get hourly usage', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredIntegrations($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['created_after'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['created_after'];
            }

            if (!empty($filters['cross_account_role_arn'])) {
                $query .= " AND cross_account_role_arn LIKE ?";
                $params[] = '%' . $filters['cross_account_role_arn'] . '%';
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered integrations', [
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
            Logger::error('Failed to find integrations by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
