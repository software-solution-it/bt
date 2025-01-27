<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class MachineModel extends Model
{
    protected $table = 'machines';
    protected $primaryKey = 'machine_id';

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
                UNIQUE KEY unique_machine (machine_id, api_key_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

            $this->db->exec($sql);
            
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

    public function getAllInventoryData($params)
    {
        try {
            $results = [];
            $apiKeyId = is_array($params) ? $params['api_key_id'] : $params;
            $tables = is_array($params) && isset($params['tables']) ? $params['tables'] : [
                'network_inventory',
                'machines',
                'accounts',
                'custom_groups',
                'endpoints',
                'licenses',
                'packages',
                'policies',
                'quarantine',
                'installation_links',
                'scan_tasks'
            ];
            $filters = is_array($params) && isset($params['filters']) ? $params['filters'] : [];
            
            Logger::info('getAllInventoryData called with params', [
                'api_key_id' => $apiKeyId,
                'tables' => $tables,
                'filters' => $filters
            ]);

            $tableQueries = [
                'accounts' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM accounts WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['account_type'])) {
                        $query .= " AND type = ?";
                        $params[] = $filters['account_type'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    return $this->decodeJsonFields($result);
                },
                
                'custom_groups' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM custom_groups WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['group_name'])) {
                        $query .= " AND name LIKE ?";
                        $params[] = "%{$filters['group_name']}%";
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'endpoints' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM endpoints WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['endpoint_status'])) {
                        $query .= " AND status = ?";
                        $params[] = $filters['endpoint_status'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'licenses' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM licenses WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['license_type'])) {
                        $query .= " AND type = ?";
                        $params[] = $filters['license_type'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'network_inventory' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM network_inventory WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['status'])) {
                        $query .= " AND status = ?";
                        $params[] = $filters['status'];
                    }
                    
                    if (isset($filters['group_id'])) {
                        $query .= " AND group_id = ?";
                        $params[] = $filters['group_id'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                    return $this->decodeJsonFields($result);
                },
                
                'machines' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM machines WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['machine_status'])) {
                        $query .= " AND status = ?";
                        $params[] = $filters['machine_status'];
                    }
                    
                    if (isset($filters['machine_name'])) {
                        $query .= " AND name LIKE ?";
                        $params[] = "%{$filters['machine_name']}%";
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'packages' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM packages WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['package_type'])) {
                        $query .= " AND type = ?";
                        $params[] = $filters['package_type'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'policies' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM policies WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['policy_status'])) {
                        $query .= " AND status = ?";
                        $params[] = $filters['policy_status'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'quarantine' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM quarantine_items WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['threat_type'])) {
                        $query .= " AND threat_type = ?";
                        $params[] = $filters['threat_type'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'installation_links' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM installation_links WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['link_status'])) {
                        $query .= " AND status = ?";
                        $params[] = $filters['link_status'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                },
                
                'scan_tasks' => function() use ($apiKeyId, $filters) {
                    $query = "SELECT * FROM scan_tasks WHERE api_key_id = ?";
                    $params = [$apiKeyId];
                    
                    if (isset($filters['task_status'])) {
                        $query .= " AND status = ?";
                        $params[] = $filters['task_status'];
                    }
                    
                    $stmt = $this->db->prepare($query);
                    $stmt->execute($params);
                    return $stmt->fetchAll();
                }
            ];
            
            // Execute queries for all requested tables
            foreach ($tables as $table) {
                if (isset($tableQueries[$table])) {
                    Logger::debug('Executing query for table', ['table' => $table]);
                    $results[$table] = $tableQueries[$table]();
                }
            }

            Logger::info('Queries completed', [
                'tables_processed' => array_keys($results),
                'total_results' => count($results)
            ]);
            
            return $results;

        } catch (\PDOException $e) {
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
}
