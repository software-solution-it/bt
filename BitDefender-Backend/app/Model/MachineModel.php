<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;
use GuzzleHttp\Client;

class MachineModel extends Model
{
    protected $table = 'machines';
    protected $primaryKey = 'machine_id';

    private $cache = [];
    private $cacheExpiry = 300; // 5 minutos de cache

    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    private function createTables()
    {
        try {
            $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
                id INT AUTO_INCREMENT PRIMARY KEY,
                machine_id VARCHAR(255) NOT NULL,
                api_key_id INT NOT NULL,
                name VARCHAR(255),
                fqdn VARCHAR(255),
                type INT,
                group_id VARCHAR(255),
                is_managed TINYINT(1) DEFAULT 1,
                operating_system VARCHAR(255),
                operating_system_version VARCHAR(255),
                ip VARCHAR(45),
                macs TEXT,
                ssid VARCHAR(255),
                managed_with_best TINYINT(1) DEFAULT 1,
                policy_id VARCHAR(255),
                policy_name VARCHAR(255),
                company_id VARCHAR(255),
                state INT,
                modules TEXT,
                last_seen DATETIME,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_machine (machine_id, api_key_id),
                INDEX idx_api_key (api_key_id),
                INDEX idx_machine_id (machine_id),
                INDEX idx_company (company_id),
                INDEX idx_name (name),
                INDEX idx_ip (ip),
                INDEX idx_last_seen (last_seen),
                INDEX idx_state (state),
                INDEX idx_policy (policy_id),
                INDEX idx_type (type),
                INDEX idx_created (created_at),
                INDEX idx_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->db->exec($sql);
            
            // Tabela de quarentena
            $this->db->exec("CREATE TABLE IF NOT EXISTS quarantine (
                id INT AUTO_INCREMENT PRIMARY KEY,
                api_key_id INT NOT NULL,
                endpoint_id VARCHAR(24) NULL,
                threat_type VARCHAR(50) NOT NULL,
                threat_name VARCHAR(255) NOT NULL,
                file_path TEXT NULL,
                severity VARCHAR(20) NOT NULL,
                status VARCHAR(20) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                INDEX idx_endpoint (endpoint_id),
                INDEX idx_severity (severity),
                INDEX idx_status (status),
                INDEX idx_created (created_at),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id),
                FOREIGN KEY (endpoint_id) REFERENCES endpoints(endpoint_id)
            )");

            // Tabela de scan_tasks se não existir
            $this->db->exec("CREATE TABLE IF NOT EXISTS scan_tasks (
                id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                endpoint_id VARCHAR(24) NULL,
                name VARCHAR(255) NOT NULL,
                status INT NOT NULL DEFAULT 1,
                scan_type VARCHAR(50) NULL,
                start_date TIMESTAMP NULL,
                end_date TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                INDEX idx_endpoint (endpoint_id),
                INDEX idx_status (status),
                INDEX idx_created (created_at),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id),
                FOREIGN KEY (endpoint_id) REFERENCES endpoints(endpoint_id)
            )");

            // Tabela de policies se não existir
            $this->db->exec("CREATE TABLE IF NOT EXISTS policies (
                id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                settings JSON NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_api_key (api_key_id),
                INDEX idx_status (status),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

        } catch (\Exception $e) {
            Logger::error('Failed to create machines table', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function saveMachine($machineData)
    {
        try {
            $this->db->beginTransaction();

            $sql = "INSERT INTO {$this->table} (
                machine_id, api_key_id, name, fqdn, type, group_id,
                is_managed, operating_system, operating_system_version,
                ip, macs, ssid, managed_with_best, policy_id,
                policy_name, company_id, state, modules, last_seen,
                created_at, updated_at
            ) VALUES (
                :machine_id, :api_key_id, :name, :fqdn, :type, :group_id,
                :is_managed, :operating_system, :operating_system_version,
                :ip, :macs, :ssid, :managed_with_best, :policy_id,
                :policy_name, :company_id, :state, :modules, :last_seen,
                :created_at, :updated_at
            ) ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                fqdn = VALUES(fqdn),
                type = VALUES(type),
                group_id = VALUES(group_id),
                is_managed = VALUES(is_managed),
                operating_system = VALUES(operating_system),
                operating_system_version = VALUES(operating_system_version),
                ip = VALUES(ip),
                macs = VALUES(macs),
                ssid = VALUES(ssid),
                managed_with_best = VALUES(managed_with_best),
                policy_id = VALUES(policy_id),
                policy_name = VALUES(policy_name),
                company_id = VALUES(company_id),
                state = VALUES(state),
                modules = VALUES(modules),
                last_seen = VALUES(last_seen),
                updated_at = VALUES(updated_at)";

            $stmt = $this->db->prepare($sql);
            
            // Prepara os dados para inserção
            $params = [
                ':machine_id' => $machineData['machine_id'],
                ':api_key_id' => $machineData['api_key_id'],
                ':name' => $machineData['name'],
                ':fqdn' => $machineData['details']['fqdn'] ?? null,
                ':type' => $machineData['type'],
                ':group_id' => $machineData['group_id'],
                ':is_managed' => $machineData['is_managed'],
                ':operating_system' => $machineData['details']['operatingSystem'] ?? null,
                ':operating_system_version' => $machineData['details']['operatingSystemVersion'] ?? null,
                ':ip' => $machineData['details']['ip'] ?? null,
                ':macs' => isset($machineData['details']['macs']) ? json_encode($machineData['details']['macs']) : null,
                ':ssid' => $machineData['details']['ssid'] ?? null,
                ':managed_with_best' => $machineData['details']['managedWithBest'] ?? true,
                ':policy_id' => $machineData['details']['policy']['id'] ?? null,
                ':policy_name' => $machineData['details']['policy']['name'] ?? null,
                ':company_id' => $machineData['details']['companyId'] ?? null,
                ':state' => $machineData['state'],
                ':modules' => isset($machineData['details']['modules']) ? json_encode($machineData['details']['modules']) : null,
                ':last_seen' => $machineData['last_seen'],
                ':created_at' => $machineData['created_at'],
                ':updated_at' => $machineData['updated_at']
            ];

            Logger::debug('Attempting to save machine with params', [
                'machine_id' => $params[':machine_id'],
                'name' => $params[':name']
            ]);

            $stmt->execute($params);
            $this->db->commit();
            
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to save machine', [
                'error' => $e->getMessage(),
                'machine_id' => $machineData['machine_id'] ?? null,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredMachines($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['name'])) {
                $query .= " AND name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['type'])) {
                $query .= " AND type = ?";
                $params[] = $filters['type'];
            }

            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered machines', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getTableQueries()
    {
        return [
            'endpoints' => function($apiKeyId, $filters) {
                $query = "SELECT e.endpoint_id, e.name, e.group_id, e.api_key_id, 
                         e.is_managed, e.status, e.ip_address, e.mac_address, 
                         e.operating_system, e.operating_system_version, e.label, 
                         e.last_seen, e.machine_type, e.company_id, e.group_name, 
                         e.policy_id, e.policy_name, e.policy_applied, e.malware_status, 
                         e.agent_info, e.state, e.modules, e.managed_with_best, 
                         e.risk_score, e.fqdn, e.macs, e.ssid, e.created_at, e.updated_at
                         FROM endpoints e 
                         WHERE e.api_key_id = ?";
                $params = [$apiKeyId];

                if (isset($filters['endpoint_status'])) {
                    $query .= " AND e.status = ?";
                    $params[] = $filters['endpoint_status'];
                }
                if (isset($filters['endpoint_name'])) {
                    $query .= " AND e.name LIKE ?";
                    $params[] = "%{$filters['endpoint_name']}%";
                }
                if (isset($filters['endpoint_group'])) {
                    $query .= " AND e.group_id = ?";
                    $params[] = $filters['endpoint_group'];
                }
                if (isset($filters['endpoint_policy'])) {
                    $query .= " AND e.policy_id = ?";
                    $params[] = $filters['endpoint_policy'];
                }
                if (isset($filters['operating_system'])) {
                    $query .= " AND e.operating_system LIKE ?";
                    $params[] = "%{$filters['operating_system']}%";
                }

                return $this->executeQuery($query, $params);
            },

            'licenses' => function($apiKeyId, $filters) {
                $query = "SELECT l.id, l.api_key_id, l.license_key, l.is_addon, 
                         l.expiry_date, l.used_slots, l.total_slots, 
                         l.subscription_type, l.own_use, l.resell, 
                         l.created_at, l.updated_at,
                         ak.name as company_name
                         FROM licenses l
                         LEFT JOIN api_keys ak ON l.api_key_id = ak.id
                         WHERE l.api_key_id = ?";
                $params = [$apiKeyId];

                if (isset($filters['expiry_date_from'])) {
                    $query .= " AND l.expiry_date >= ?";
                    $params[] = $filters['expiry_date_from'];
                }
                if (isset($filters['expiry_date_to'])) {
                    $query .= " AND l.expiry_date <= ?";
                    $params[] = $filters['expiry_date_to'];
                }
                
                return $this->executeQuery($query, $params);
            },

            'policies' => function($apiKeyId, $filters) {
                $query = "SELECT p.id, p.policy_id, p.api_key_id, p.name, p.settings, 
                         p.created_at, p.updated_at
                         FROM policies p
                         WHERE p.api_key_id = ?";
                $params = [$apiKeyId];

                if (isset($filters['policy_name'])) {
                    $query .= " AND p.name LIKE ?";
                    $params[] = "%{$filters['policy_name']}%";
                }

                return $this->executeQuery($query, $params);
            },

            'webhook_events' => function($apiKeyId, $filters) {
                $query = "SELECT w.id, w.endpoint_id, w.event_type, w.event_data, 
                         w.severity, w.status, w.computer_name, w.computer_ip,
                         w.created_at, w.processed_at, w.error_message
                         FROM webhook_events w
                         JOIN endpoints e ON w.endpoint_id = e.endpoint_id
                         WHERE e.api_key_id = ?
                         ORDER BY w.created_at DESC";
                $params = [$apiKeyId];

                return $this->executeQuery($query, $params);
            },

            'companies' => function($apiKeyId, $filters) {
                $query = "SELECT c.id, c.api_key_id, c.name, c.address, c.phone, 
                         c.country, c.state, c.city, c.postal_code, c.timezone,
                         c.created_at, c.updated_at
                         FROM companies c
                         WHERE c.api_key_id = ?";
                $params = [$apiKeyId];

                return $this->executeQuery($query, $params);
            },

            'accounts' => function($apiKeyId, $filters) {
                $query = "SELECT a.id, a.api_key_id, a.email, a.full_name, a.role, 
                         a.rights, a.language, a.timezone, a.created_at, a.updated_at
                         FROM accounts a
                         WHERE a.api_key_id = ?";
                $params = [$apiKeyId];

                return $this->executeQuery($query, $params);
            }
        ];
    }

    private function executeQuery($query, $params) 
    {
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->decodeJsonFields($results);
    }

    public function getAllInventoryData($params)
    {
        try {
            $apiKeyId = is_array($params) ? $params['api_key_id'] : $params;
            $requestedTable = is_array($params) && isset($params['tables']) ? $params['tables'][0] : null;
            $filters = is_array($params) && isset($params['filters']) ? $params['filters'] : [];

            Logger::info('MachineModel::getAllInventoryData - Início', [
                'api_key_id' => $apiKeyId,
                'requested_table' => $requestedTable,
                'filters' => $filters
            ]);

            $tableQueries = $this->getTableQueries();

            // Lista de tabelas válidas
            $validTables = [
                'endpoints',
                'licenses',
                'policies',
                'webhook_events',
                'companies',
                'accounts'
            ];

            // Se uma tabela específica foi solicitada
            if (!empty($requestedTable)) {
                Logger::info('Buscando tabela específica', [
                    'tabela' => $requestedTable,
                    'é_válida' => in_array($requestedTable, $validTables)
                ]);

                if (in_array($requestedTable, $validTables) && isset($tableQueries[$requestedTable])) {
                    $result = $tableQueries[$requestedTable]($apiKeyId, $filters);
                    Logger::info("Resultado {$requestedTable}", ['count' => count($result)]);
                    return $result;
                }

                Logger::info('Tabela não encontrada ou inválida', ['tabela' => $requestedTable]);
                return [];
            }

            // Se nenhuma tabela foi especificada, retorna apenas as válidas
            Logger::info('Buscando tabelas válidas');
            $results = [];
            foreach ($validTables as $table) {
                if (isset($tableQueries[$table])) {
                    $results[$table] = $tableQueries[$table]($apiKeyId, $filters);
                    Logger::info("Resultado tabela {$table}", ['count' => count($results[$table])]);
                }
            }

            return $results;

        } catch (\Exception $e) {
            Logger::error('Failed to get inventory data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Decodifica campos JSON nos resultados
     */
    private function decodeJsonFields($rows)
    {
        $jsonFields = ['modules', 'macs', 'settings', 'roles', 'scan_mode', 'deployment_options', 'details'];
        
        return array_map(function($row) use ($jsonFields) {
            foreach ($jsonFields as $field) {
                if (isset($row[$field]) && is_string($row[$field])) {
                    $decoded = json_decode($row[$field], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row[$field] = $decoded;
                    }
                }
            }
            return $row;
        }, $rows);
    }

    public function getInventory($apiKey, $type = 'endpoints')
    {
        $cacheKey = "inventory_{$apiKey}_{$type}";
        
        // Verifica se há cache válido
        if (isset($this->cache[$cacheKey]) && 
            (time() - $this->cache[$cacheKey]['time'] < $this->cacheExpiry)) {
            return $this->cache[$cacheKey]['data'];
        }

        try {
            $client = new Client([
                'base_uri' => getenv('BITDEFENDER_API_URL'),
                'timeout' => 10,
                'headers' => [
                    'Authorization' => $apiKey
                ]
            ]);

            // Otimiza a requisição especificando apenas os campos necessários
            $params = [
                'query' => [
                    'fields' => $this->getRequiredFields($type),
                    'pageSize' => 100, // Limita o número de resultados
                    'pageNumber' => 1
                ]
            ];

            $response = $client->get("/api/v1.0/jsonrpc/inventory", $params);
            $result = json_decode($response->getBody(), true);

            // Armazena no cache
            $this->cache[$cacheKey] = [
                'time' => time(),
                'data' => $result
            ];

            return $result;
        } catch (\Exception $e) {
            Logger::error('Error getting inventory', [
                'error' => $e->getMessage(),
                'apiKey' => substr($apiKey, 0, 10) . '...'
            ]);
            throw $e;
        }
    }

    private function getRequiredFields($type)
    {
        switch ($type) {
            case 'endpoints':
                return 'id,name,label,ip,os,webhook_events';
            case 'accounts':
                return 'id,email,full_name,role,language,timezone';
            case 'licenses':
                return 'id,license_key,expiry_date,updated_at';
            default:
                return '*';
        }
    }
}
