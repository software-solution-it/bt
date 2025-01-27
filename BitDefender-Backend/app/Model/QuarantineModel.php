<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class QuarantineModel extends Model
{
    protected $table = 'quarantine_items';
    protected $primaryKey = 'item_id';

    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    private function createTables()
    {
        try {
            // Tabela principal de itens em quarentena
            $this->db->exec("CREATE TABLE IF NOT EXISTS quarantine_items (
                item_id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                service_type ENUM('computers', 'exchange') NOT NULL,
                endpoint_id VARCHAR(24) NULL,
                file_name VARCHAR(255) NOT NULL,
                file_path TEXT NULL,
                threat_name VARCHAR(255) NULL,
                detection_time TIMESTAMP NULL,
                file_size BIGINT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'quarantined',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                INDEX idx_service_endpoint (service_type, endpoint_id),
                INDEX idx_status (status),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Tabela de histórico de tarefas
            $this->db->exec("CREATE TABLE IF NOT EXISTS quarantine_task_history (
                task_id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                service_type ENUM('computers', 'exchange') NOT NULL,
                action_type ENUM('remove', 'restore', 'empty') NOT NULL,
                items JSON NULL,
                status VARCHAR(50) NOT NULL,
                result TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                INDEX idx_api_key (api_key_id),
                INDEX idx_service_action (service_type, action_type),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

        } catch (\PDOException $e) {
            Logger::error('Failed to create quarantine tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function validateQuarantineItem($item)
    {
        // Verifica campos obrigatórios
        if (!isset($item['fileName']) || !isset($item['itemId'])) {
            Logger::info('Invalid quarantine item data', [
                'item' => $item,
                'missing_fields' => [
                    'fileName' => !isset($item['fileName']),
                    'itemId' => !isset($item['itemId'])
                ]
            ]);
            return false;
        }
        return true;
    }

    public function syncQuarantineItems($service, $items)
    {
        try {
            foreach ($items as $item) {
                // Extrair o nome do arquivo do caminho
                $fileName = basename($item['details']['filePath'] ?? '');
                
                // Preparar os dados do item com campos obrigatórios
                $itemData = [
                    'item_id' => $item['id'],
                    'file_name' => $fileName,
                    'api_key_id' => $item['api_key_id'],
                    'service_type' => $service,
                    'endpoint_id' => $item['endpointId'],
                    'threat_name' => $item['threatName'],
                    'file_path' => $item['details']['filePath'] ?? '',
                    'detection_time' => $item['quarantinedOn'],
                    'status' => $this->mapActionStatus($item['actionStatus'])
                ];

                // Verificar campos obrigatórios
                if (empty($itemData['file_name']) || empty($itemData['item_id'])) {
                    Logger::info('Invalid quarantine item data', [
                        'item' => $item,
                        'missing_fields' => [
                            'fileName' => empty($itemData['file_name']),
                            'itemId' => empty($itemData['item_id'])
                        ]
                    ]);
                    continue;
                }

                $this->insertOrUpdate($itemData);
            }
            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to sync quarantine items', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function mapActionStatus($status)
    {
        $statusMap = [
            0 => 'quarantined',
            1 => 'restored',
            2 => 'removed',
            3 => 'pending_restore',
            4 => 'pending_removal'
        ];
        
        return $statusMap[$status] ?? 'unknown';
    }

    public function logTaskHistory($taskId, $service, $actionType, $items, $status, $result = null)
    {
        try {
            if (!$taskId || !$service || !$actionType) {
                Logger::error('Missing required fields for task history', [
                    'taskId' => $taskId,
                    'service' => $service,
                    'actionType' => $actionType
                ]);
                return false;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO quarantine_task_history 
                (task_id, service_type, action_type, items, status, result, completed_at) 
                VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)"
            );

            return $stmt->execute([
                $taskId,
                $service,
                $actionType,
                json_encode($items),
                $status,
                $result
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to log task history', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateItemsStatus($itemIds, $newStatus)
    {
        try {
            if (!is_array($itemIds) || empty($itemIds)) {
                Logger::error('Invalid itemIds for status update', [
                    'itemIds' => $itemIds
                ]);
                return false;
            }

            $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
            $stmt = $this->db->prepare(
                "UPDATE {$this->table} 
                SET status = ? 
                WHERE item_id IN ($placeholders)"
            );

            $params = array_merge([$newStatus], $itemIds);
            return $stmt->execute($params);
        } catch (\Exception $e) {
            Logger::error('Failed to update items status', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        } 
    }

    public function getFilteredQuarantineItems($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['api_key_id'])) {
                $query .= " AND api_key_id = ?";
                $params[] = $filters['api_key_id'];
            }

            if (!empty($filters['service_type'])) {
                $query .= " AND service_type = ?";
                $params[] = $filters['service_type'];
            }

            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['detection_time_after'])) {
                $query .= " AND detection_time >= ?";
                $params[] = $filters['detection_time_after'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered quarantine items', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function insertOrUpdate($data)
    {
        try {
            $sql = "INSERT INTO {$this->table} (
                item_id, api_key_id, service_type, endpoint_id, file_name, 
                file_path, threat_name, detection_time, status
            ) VALUES (
                :item_id, :api_key_id, :service_type, :endpoint_id, :file_name,
                :file_path, :threat_name, :detection_time, :status
            ) ON DUPLICATE KEY UPDATE
                file_name = VALUES(file_name),
                file_path = VALUES(file_path),
                threat_name = VALUES(threat_name),
                status = VALUES(status),
                updated_at = CURRENT_TIMESTAMP";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($data);
        } catch (\PDOException $e) {
            Logger::error('Failed to insert/update quarantine item', [
                'error' => $e->getMessage(),
                'data' => $data
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
            Logger::error('Failed to find quarantine items by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
