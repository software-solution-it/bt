<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class SyncModel extends Model
{
    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    private function createTables()
    {
        try {
            // Tabela para controle geral de sincronização
            $this->db->query("
                CREATE TABLE IF NOT EXISTS sync_control (
                    api_key_id INT NOT NULL,
                    last_sync TIMESTAMP NOT NULL,
                    PRIMARY KEY (api_key_id),
                    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
                )
            ");

            // Tabela para controle por operação
            $this->db->query("
                CREATE TABLE IF NOT EXISTS operation_sync_control (
                    api_key_id INT NOT NULL,
                    operation_name VARCHAR(50) NOT NULL,
                    last_sync TIMESTAMP NOT NULL,
                    PRIMARY KEY (api_key_id, operation_name),
                    FOREIGN KEY (api_key_id) REFERENCES api_keys(id) ON DELETE CASCADE
                )
            ");
        } catch (\Exception $e) {
            Logger::error('Failed to create sync tables', ['error' => $e->getMessage()]);
        }
    }

    public function getLastSyncTime($apiKeyId)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT last_sync FROM sync_control WHERE api_key_id = ?"
            );
            $stmt->execute([$apiKeyId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? strtotime($result['last_sync']) : null; 
        } catch (\Exception $e) {
            Logger::error('Failed to get last sync time', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function updateLastSyncTime($apiKeyId, $timestamp)
    {
        try {
            Logger::info('Attempting to update last sync time', [
                'api_key_id' => $apiKeyId,
                'timestamp' => $timestamp
            ]);

            $stmt = $this->db->prepare(
                "INSERT INTO sync_control (api_key_id, last_sync) 
                 VALUES (?, FROM_UNIXTIME(?))
                 ON DUPLICATE KEY UPDATE last_sync = FROM_UNIXTIME(?)"
            );
            $result = $stmt->execute([$apiKeyId, $timestamp, $timestamp]);
            
            Logger::info('Update last sync time result', [
                'success' => $result
            ]);
            
            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to update last sync time', [
                'error' => $e->getMessage(),
                'api_key_id' => $apiKeyId
            ]);
            return false;
        }
    }

    public function getLastOperationSync($apiKeyId, $operation)
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT last_sync FROM operation_sync_control 
                 WHERE api_key_id = ? AND operation_name = ?"
            );
            $stmt->execute([$apiKeyId, $operation]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return $result ? strtotime($result['last_sync']) : null;
        } catch (\Exception $e) {
            Logger::error('Failed to get last operation sync', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function updateLastOperationSync($apiKeyId, $operation)
    {
        try {
            Logger::info('Attempting to update operation sync', [
                'api_key_id' => $apiKeyId,
                'operation' => $operation
            ]);

            $stmt = $this->db->prepare(
                "INSERT INTO operation_sync_control (api_key_id, operation_name, last_sync)
                 VALUES (?, ?, NOW())
                 ON DUPLICATE KEY UPDATE last_sync = NOW()"
            );
            $result = $stmt->execute([$apiKeyId, $operation]);

            Logger::info('Update operation sync result', [
                'success' => $result
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Failed to update operation sync', [
                'error' => $e->getMessage(),
                'api_key_id' => $apiKeyId,
                'operation' => $operation
            ]);
            return false;
        }
    }

    public function clearSyncHistory($apiKeyId) 
    {
        try {
            $this->db->beginTransaction();

            $stmt1 = $this->db->prepare("DELETE FROM sync_control WHERE api_key_id = ?");
            $stmt1->execute([$apiKeyId]);
            
            $stmt2 = $this->db->prepare("DELETE FROM operation_sync_control WHERE api_key_id = ?");
            $stmt2->execute([$apiKeyId]);

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollback();
            Logger::error('Failed to clear sync history', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function getApiKeyWithService($apiKeyId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT ak.*, std.type as service_type 
                FROM api_keys ak
                LEFT JOIN service_type_domain std ON ak.service_type_id = std.id 
                WHERE ak.id = ?
            ");
            $stmt->execute([$apiKeyId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            Logger::error('Failed to get API key with service', ['error' => $e->getMessage()]);
            return null;
        }
    }
}  