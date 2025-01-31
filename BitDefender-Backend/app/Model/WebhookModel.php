<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class WebhookModel extends Model
{
    protected $table = 'webhook_events';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                endpoint_id VARCHAR(24) NULL,
                event_type VARCHAR(50) NOT NULL,
                event_data JSON NOT NULL,
                severity ENUM('low', 'medium', 'high', 'info') DEFAULT 'info',
                computer_name VARCHAR(255) NULL,
                computer_ip VARCHAR(45) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                processed_at TIMESTAMP NULL,
                error_message TEXT NULL,
                INDEX idx_endpoint (endpoint_id),
                INDEX idx_event_type (event_type),
                INDEX idx_severity (severity),
                INDEX idx_created_at (created_at),
                FOREIGN KEY (endpoint_id) REFERENCES endpoints(endpoint_id),
                INDEX idx_machine (computer_name),
                INDEX idx_composite_1 (computer_name, event_type),
                INDEX idx_composite_2 (event_type, severity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->db->exec($sql);

        } catch (\PDOException $e) {
            Logger::error('Failed to create webhook_events table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function saveEvent($eventData)
    {
        try {
            Logger::info('Saving webhook event', ['data' => $eventData]);

            // Extrair taskId do event_data se existir
            $eventDataDecoded = json_decode($eventData['event_data'], true);
            $taskId = $eventDataDecoded['taskId'] ?? null;

            // Preparar dados base
            $data = [
                'endpoint_id' => $eventData['endpoint_id'] ?? null,
                'event_type' => $eventData['event_type'],
                'event_data' => $eventData['event_data'],
                'severity' => $eventData['severity'],
                'computer_name' => $eventData['computer_name'],
                'computer_ip' => $eventData['computer_ip'],
                'created_at' => $eventData['created_at']
            ];

            if ($taskId && $eventData['event_type'] === 'task-status') {
                // Tentar atualizar evento existente
                $sql = "UPDATE {$this->table} 
                       SET event_data = :event_data,
                           severity = :severity,
                           computer_ip = :computer_ip,
                           created_at = :created_at
                       WHERE event_type = :event_type 
                       AND computer_name = :computer_name
                       AND endpoint_id = :endpoint_id
                       AND JSON_EXTRACT(event_data, '$.taskId') = :taskId";

                $stmt = $this->db->prepare($sql);
                $stmt->execute(array_merge($data, ['taskId' => $taskId]));

                if ($stmt->rowCount() > 0) {
                    return $this->db->lastInsertId();
                }
            }

            // Se não houver taskId ou não encontrou registro para atualizar, insere novo
            $sql = "INSERT INTO {$this->table} 
                (endpoint_id, event_type, event_data, severity, computer_name, computer_ip, created_at)
                VALUES 
                (:endpoint_id, :event_type, :event_data, :severity, :computer_name, :computer_ip, :created_at)";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($data);
            return $this->db->lastInsertId();

        } catch (\PDOException $e) {
            Logger::error('Failed to save webhook event', [
                'error' => $e->getMessage(),
                'event_data' => $eventData
            ]);
            throw $e;
        }
    }

    public function getEventsByEndpoint($endpointId, $filters = [])
    {
        try {
            $sql = "SELECT * FROM {$this->table} WHERE endpoint_id = :endpoint_id";
            $params = ['endpoint_id' => $endpointId];

            if (!empty($filters['event_type'])) {
                $sql .= " AND event_type = :event_type";
                $params['event_type'] = $filters['event_type'];
            }

            if (!empty($filters['severity'])) {
                $sql .= " AND severity = :severity";
                $params['severity'] = $filters['severity'];
            }

            if (!empty($filters['status'])) {
                $sql .= " AND status = :status";
                $params['status'] = $filters['status'];
            }

            $sql .= " ORDER BY created_at DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            Logger::error('Failed to get events by endpoint', [
                'error' => $e->getMessage(),
                'endpoint_id' => $endpointId,
                'filters' => $filters
            ]);
            throw $e;
        }
    }
} 