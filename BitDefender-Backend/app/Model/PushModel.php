<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class PushModel extends Model
{
    protected $table = 'push_settings';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    private function createTables()
    {
        try {
            // Tabela de configurações
            $this->db->exec("CREATE TABLE IF NOT EXISTS push_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                status TINYINT NOT NULL DEFAULT 1,
                service_type VARCHAR(20) NOT NULL,
                url VARCHAR(255) NOT NULL,
                require_valid_ssl BOOLEAN DEFAULT true,
                authorization VARCHAR(255) NULL,
                subscribe_to_events JSON NOT NULL,
                synced_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Tabela de estatísticas
            $this->db->exec("CREATE TABLE IF NOT EXISTS push_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                events_count INT DEFAULT 0,
                test_events_count INT DEFAULT 0,
                sent_messages_count INT DEFAULT 0,
                error_messages_count INT DEFAULT 0,
                connection_errors INT DEFAULT 0,
                status_300_errors INT DEFAULT 0,
                status_400_errors INT DEFAULT 0,
                status_500_errors INT DEFAULT 0,
                timeout_errors INT DEFAULT 0,
                synced_at TIMESTAMP NULL,
                last_update_time TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Nova tabela para eventos
            $this->db->exec("CREATE TABLE IF NOT EXISTS push_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                module VARCHAR(50) NOT NULL,
                company_id VARCHAR(50) NOT NULL,
                computer_id VARCHAR(50) NULL,
                computer_name VARCHAR(255) NULL,
                computer_ip VARCHAR(50) NULL,
                computer_fqdn VARCHAR(255) NULL,
                product_installed VARCHAR(50) NULL,
                event_type VARCHAR(50) NOT NULL,
                event_data JSON NOT NULL,
                detection_time TIMESTAMP NULL,
                synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                INDEX idx_module (module),
                INDEX idx_event_type (event_type),
                INDEX idx_computer_id (computer_id),
                INDEX idx_detection_time (detection_time),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

        } catch (\PDOException $e) {
            Logger::error('Failed to create push tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function setPushEventSettings($settings)
    {
        try {
            $data = [
                'status' => $settings['status'],
                'service_type' => $settings['serviceType'],
                'url' => $settings['serviceSettings']['url'],
                'require_valid_ssl' => $settings['serviceSettings']['requireValidSslCertificate'] ?? true,
                'authorization' => $settings['serviceSettings']['authorization'] ?? null,
                'subscribe_to_events' => json_encode($settings['subscribeToEventTypes']),
                'synced_at' => date('Y-m-d H:i:s')
            ];

            $existing = $this->db->query("SELECT id FROM {$this->table} LIMIT 1")->fetch();

            if ($existing) {
                return $this->update($existing['id'], $data);
            } else {
                return $this->create($data);
            }
        } catch (\Exception $e) { 
            Logger::error('Failed to set push event settings', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPushEventSettings()
    {
        try {
            $stmt = $this->db->query("SELECT * FROM {$this->table} LIMIT 1");
            $settings = $stmt->fetch();

            if (!$settings) {
                return null;
            }

            return [
                'status' => (int)$settings['status'],
                'serviceType' => $settings['service_type'],
                'serviceSettings' => [
                    'url' => $settings['url'],
                    'requireValidSslCertificate' => (bool)$settings['require_valid_ssl'],
                    'authorization' => $settings['authorization']
                ],
                'subscribeToEventTypes' => json_decode($settings['subscribe_to_events'], true)
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get push event settings', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updatePushStats($stats)
    {
        try {
            $data = [
                'events_count' => $stats['count']['events'] ?? 0,
                'test_events_count' => $stats['count']['testEvents'] ?? 0,
                'sent_messages_count' => $stats['count']['sentMessages'] ?? 0,
                'error_messages_count' => $stats['count']['errorMessages'] ?? 0,
                'connection_errors' => $stats['error']['connectionError'] ?? 0,
                'status_300_errors' => $stats['error']['statusCode300'] ?? 0,
                'status_400_errors' => $stats['error']['statusCode400'] ?? 0,
                'status_500_errors' => $stats['error']['statusCode500'] ?? 0,
                'timeout_errors' => $stats['error']['timeout'] ?? 0,
                'last_update_time' => date('Y-m-d H:i:s'),
                'synced_at' => date('Y-m-d H:i:s')
            ];

            return $this->db->prepare("
                INSERT INTO push_stats SET
                events_count = :events_count,
                test_events_count = :test_events_count,
                sent_messages_count = :sent_messages_count,
                error_messages_count = :error_messages_count,
                connection_errors = :connection_errors,
                status_300_errors = :status_300_errors,
                status_400_errors = :status_400_errors,
                status_500_errors = :status_500_errors,
                timeout_errors = :timeout_errors,
                last_update_time = :last_update_time,
                synced_at = :synced_at
            ")->execute($data);
        } catch (\Exception $e) {
            Logger::error('Failed to update push stats', [
                'error' => $e->getMessage(),
                'stats' => $stats
            ]);
            throw $e;
        }
    }

    public function getPushEventStats()
    {
        try {
            $stmt = $this->db->query("SELECT * FROM push_stats ORDER BY id DESC LIMIT 1");
            $stats = $stmt->fetch();

            if (!$stats) {
                return null;
            }

            return [
                'count' => [
                    'events' => (int)$stats['events_count'],
                    'testEvents' => (int)$stats['test_events_count'],
                    'sentMessages' => (int)$stats['sent_messages_count'],
                    'errorMessages' => (int)$stats['error_messages_count']
                ],
                'error' => [
                    'connectionError' => (int)$stats['connection_errors'],
                    'statusCode300' => (int)$stats['status_300_errors'],
                    'statusCode400' => (int)$stats['status_400_errors'],
                    'statusCode500' => (int)$stats['status_500_errors'],
                    'timeout' => (int)$stats['timeout_errors']
                ],
                'lastUpdateTime' => $stats['last_update_time']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get push event stats', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function resetPushEventStats()
    {
        try {
            return $this->db->exec("TRUNCATE TABLE push_stats");
        } catch (\Exception $e) {
            Logger::error('Failed to reset push event stats', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncWithApi($apiSettings)
    {
        try {
            if (!$apiSettings) {
                Logger::debug('Empty API settings received, using defaults');
                return false;
            }

            $data = [
                'status' => (int)($apiSettings['status'] ?? 0),
                'service_type' => $apiSettings['serviceType'] ?? 'webhook',
                'url' => $apiSettings['serviceSettings']['url'] ?? '',
                'require_valid_ssl' => isset($apiSettings['serviceSettings']['requireValidSslCertificate']) 
                    ? (int)$apiSettings['serviceSettings']['requireValidSslCertificate'] 
                    : 1,
                'authorization' => $apiSettings['serviceSettings']['authorization'] ?? null,
                'subscribe_to_events' => json_encode($apiSettings['subscribeToEventTypes'] ?? []),
                'synced_at' => date('Y-m-d H:i:s')
            ];

            // Validação dos campos obrigatórios
            if (empty($data['url'])) {
                throw new \Exception('Service URL is required');
            }

            if (empty($data['service_type'])) {
                throw new \Exception('Service type is required');
            }

            $existing = $this->db->query("SELECT id FROM {$this->table} LIMIT 1")->fetch();

            if ($existing) {
                return $this->update($existing['id'], $data);
            } else {
                return $this->create($data);
            }
        } catch (\Exception $e) {
            Logger::error('Failed to sync push settings with API', [
                'error' => $e->getMessage(),
                'apiSettings' => $apiSettings
            ]);
            throw $e;
        }
    }

    public function syncStatsWithApi($apiStats)
    {
        try {
            if (!$apiStats) {
                Logger::debug('Empty API stats received, using defaults');
                return false;
            }

            $data = [
                'events_count' => $apiStats['count']['events'] ?? 0,
                'test_events_count' => $apiStats['count']['testEvents'] ?? 0,
                'sent_messages_count' => $apiStats['count']['sentMessages'] ?? 0,
                'error_messages_count' => $apiStats['count']['errorMessages'] ?? 0,
                'connection_errors' => $apiStats['error']['connectionError'] ?? 0,
                'status_300_errors' => $apiStats['error']['statusCode300'] ?? 0,
                'status_400_errors' => $apiStats['error']['statusCode400'] ?? 0,
                'status_500_errors' => $apiStats['error']['statusCode500'] ?? 0,
                'timeout_errors' => $apiStats['error']['timeout'] ?? 0,
                'last_update_time' => $apiStats['lastUpdateTime'] ?? date('Y-m-d H:i:s'),
                'synced_at' => date('Y-m-d H:i:s')
            ];

            return $this->db->prepare("
                INSERT INTO push_stats SET
                events_count = :events_count,
                test_events_count = :test_events_count,
                sent_messages_count = :sent_messages_count,
                error_messages_count = :error_messages_count,
                connection_errors = :connection_errors,
                status_300_errors = :status_300_errors,
                status_400_errors = :status_400_errors,
                status_500_errors = :status_500_errors,
                timeout_errors = :timeout_errors,
                last_update_time = :last_update_time,
                synced_at = :synced_at
            ")->execute($data);
        } catch (\Exception $e) {
            Logger::error('Failed to sync push stats with API', [
                'error' => $e->getMessage(),
                'apiStats' => $apiStats
            ]);
            throw $e;
        }
    }

    public function saveEvent($event)
    {
        try {
            $data = [
                'module' => $event['module'],
                'company_id' => $event['companyId'],
                'computer_id' => $event['computer_id'] ?? $event['computerId'] ?? null,
                'computer_name' => $event['computer_name'] ?? $event['computerName'] ?? null,
                'computer_ip' => $event['computer_ip'] ?? $event['computerIp'] ?? null,
                'computer_fqdn' => $event['computer_fqdn'] ?? null,
                'product_installed' => $event['product_installed'] ?? null,
                'event_type' => $this->getEventType($event),
                'event_data' => json_encode($event),
                'detection_time' => $this->getDetectionTime($event),
                'synced_at' => date('Y-m-d H:i:s')
            ];

            $stmt = $this->db->prepare("
                INSERT INTO push_events
                (module, company_id, computer_id, computer_name, computer_ip, computer_fqdn, 
                product_installed, event_type, event_data, detection_time, synced_at)
                VALUES
                (:module, :company_id, :computer_id, :computer_name, :computer_ip, :computer_fqdn,
                :product_installed, :event_type, :event_data, :detection_time, :synced_at)
            ");

            return $stmt->execute($data);
        } catch (\Exception $e) {
            Logger::error('Failed to save push event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
            throw $e;
        }
    }

    private function getEventType($event)
    {
        $moduleTypes = [
            'av' => 'antimalware',
            'aph' => 'antiphishing',
            'fw' => 'firewall',
            'avc' => 'atc_ids',
            'dp' => 'data_protection',
            'hd' => 'hyper_detect',
            'uc' => 'user_control',
            'modules' => 'product_modules',
            'registration' => 'product_registration',
            'sva' => 'security_server_status',
            'sva-load' => 'security_server_load',
            'exchange-malware' => 'exchange_malware',
            'network-sandboxing' => 'sandbox_analyzer',
            'task-status' => 'task_status',
            'storage-antimalware' => 'storage_antimalware',
            'adcloud' => 'active_directory',
            'exchange-user-credentials' => 'exchange_credentials'
        ];

        return $moduleTypes[$event['module']] ?? $event['module'];
    }

    private function getDetectionTime($event)
    {
        $timeFields = [
            'detection_time',
            'detectionTime',
            'timestamp',
            'date',
            'last_blocked',
            'lastAdReportDate'
        ];
        
        foreach ($timeFields as $field) {
            if (isset($event[$field])) {
                if (is_numeric($event[$field])) {
                    return date('Y-m-d H:i:s', $event[$field]);
                }
                
                // Converte formato ISO 8601 para formato MySQL
                try {
                    $datetime = new \DateTime($event[$field]);
                    return $datetime->format('Y-m-d H:i:s');
                } catch (\Exception $e) {
                    Logger::error('Failed to parse datetime', [
                        'value' => $event[$field],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        return date('Y-m-d H:i:s');
    }

    public function getFilteredPushSettings($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['service_type'])) {
                $query .= " AND service_type = ?";
                $params[] = $filters['service_type'];
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
            Logger::error('Failed to get filtered push settings', [
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
                $result['subscribe_to_events'] = json_decode($result['subscribe_to_events'], true);
            }

            return $results;
        } catch (\PDOException $e) {
            Logger::error('Failed to find push settings by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
