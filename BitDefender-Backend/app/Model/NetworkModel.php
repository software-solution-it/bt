<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class NetworkModel extends Model
{
    protected $table = 'endpoints';
    protected $primaryKey = 'endpoint_id';

     public function __construct()
    {
        parent::__construct(); 
        $this->createTables(); 
        $this->updateEndpointsStructure();
        $this->updateNetworkInventoryStructure();
    }

    private function createTables()
    {
        try {
            // Tabela de endpoints atualizada com todos os campos
            $this->db->exec("CREATE TABLE IF NOT EXISTS endpoints (
                endpoint_id VARCHAR(24) PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                group_id VARCHAR(24) NULL,
                api_key_id INT NULL,
                is_managed BOOLEAN DEFAULT true,
                is_deleted BOOLEAN DEFAULT false,
                status VARCHAR(50) NOT NULL,
                ip_address VARCHAR(45) NULL,
                mac_address VARCHAR(17) NULL,
                operating_system VARCHAR(100) NULL,
                operating_system_version VARCHAR(100) NULL,
                label VARCHAR(255) NULL,
                last_seen TIMESTAMP NULL,
                machine_type INT NULL,
                company_id VARCHAR(24) NULL,
                group_name VARCHAR(255) NULL,
                policy_id VARCHAR(24) NULL,
                policy_name VARCHAR(255) NULL,
                policy_applied BOOLEAN DEFAULT false,
                malware_status JSON NULL,
                agent_info JSON NULL,
                state INT DEFAULT 0,
                modules JSON NULL,
                move_state INT DEFAULT 0,
                managed_with_best BOOLEAN DEFAULT false,
                risk_score JSON NULL,
                fqdn VARCHAR(255) NULL,
                macs JSON NULL,
                ssid VARCHAR(255) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_api_key (api_key_id),
                INDEX idx_last_seen (last_seen),
                INDEX idx_state (state),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Tabela de grupos customizados
            $this->db->exec("CREATE TABLE IF NOT EXISTS custom_groups (
                group_id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                parent_id VARCHAR(24) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_parent (parent_id),
                INDEX idx_api_key (api_key_id),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Tabela de tarefas de scan atualizada
            $this->db->exec("CREATE TABLE IF NOT EXISTS scan_tasks (
                id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                start_date TIMESTAMP NULL,
                status INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_api_key (api_key_id),
                INDEX idx_start_date (start_date),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Tabela de inventário de rede atualizada
            $this->db->exec("CREATE TABLE IF NOT EXISTS network_inventory (
                item_id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                parent_id VARCHAR(24) NULL,
                type VARCHAR(50) NOT NULL,
                details JSON NOT NULL,
                company_id VARCHAR(24) NULL,
                lastSeen TIMESTAMP NULL,
                is_deleted BOOLEAN DEFAULT false,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_parent_type (parent_id, type),
                INDEX idx_company (company_id),
                INDEX idx_api_key (api_key_id),
                INDEX idx_is_deleted (is_deleted),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

        } catch (\PDOException $e) {
            Logger::error('Failed to create network tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncEndpoints($endpoints)
    {
        try {
            Logger::info('Starting syncEndpoints', [
                'total_endpoints' => count($endpoints['items'] ?? []),
                'api_key_id' => $endpoints['api_key_id'] ?? null
            ]);

            $startedTransaction = false;
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
                $startedTransaction = true;
            }

            $count = 0;
            foreach ($endpoints['items'] as $item) {
                try {
                    $endpoint = $item['endpoint'] ?? $item;

                    Logger::debug('Processing endpoint data', [
                        'endpoint_id' => $endpoint['endpoint_id'] ?? null,
                        'data' => $endpoint
                    ]);

                    if (empty($endpoint['endpoint_id'])) {
                        Logger::error('Missing endpoint_id field', [
                            'endpoint' => $endpoint
                        ]);
                        continue;
                    }

                    // Preparar os dados para o banco garantindo valores padrão adequados
                    $endpointData = [
                        'endpoint_id' => $endpoint['endpoint_id'],
                        'name' => $endpoint['name'] ?? '',
                        'group_id' => $endpoint['group_id'] ?? null,
                        'api_key_id' => $endpoints['api_key_id'],
                        'is_managed' => $endpoint['is_managed'] ?? true,
                        'status' => $endpoint['status'] ?? 'unknown',
                        'ip_address' => $endpoint['ip_address'] ?? null,
                        'mac_address' => $endpoint['mac_address'] ?? null,
                        'operating_system' => $endpoint['operating_system'] ?? null,
                        'operating_system_version' => $endpoint['operating_system_version'] ?? null,
                        'label' => $endpoint['label'] ?? '',
                        'last_seen' => $endpoint['last_seen'] ?? null,
                        'machine_type' => $endpoint['machine_type'] ?? 0,
                        'company_id' => $endpoint['company_id'] ?? null,
                        'group_name' => $endpoint['group_name'] ?? null,
                        'policy_id' => $endpoint['policy_id'] ?? null,
                        'policy_name' => $endpoint['policy_name'] ?? null,
                        'policy_applied' => $endpoint['policy_applied'] ?? 0,
                        'malware_status' => $endpoint['malware_status'] ?? null,
                        'agent_info' => $endpoint['agent_info'] ?? null,
                        'state' => $endpoint['state'] ?? 0,
                        'modules' => $endpoint['modules'] ?? null,
                        'managed_with_best' => $endpoint['managed_with_best'] ?? 0,
                        'fqdn' => $endpoint['fqdn'] ?? null,
                        'macs' => $endpoint['macs'] ?? null
                    ];

                    Logger::debug('Prepared endpoint data for database', [
                        'endpoint_id' => $endpointData['endpoint_id'],
                        'prepared_data' => $endpointData
                    ]);

                    $existingEndpoint = $this->find($endpointData['endpoint_id']);
                    
                    if ($existingEndpoint) {
                        $this->updateEndpoint($endpointData);
                    } else {
                        $this->createEndpoint($endpointData);
                    }

                    $count++;

                } catch (\Exception $e) {
                    Logger::error('Failed to process endpoint', [
                        'endpoint_id' => $endpoint['endpoint_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if ($startedTransaction) {
                $this->db->commit();
            }

            Logger::info('Endpoints synced successfully', ['count' => $count]);
            return true;

        } catch (\Exception $e) {
            if ($startedTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error('Failed to sync endpoints', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function determineStatus($endpoint)
    {
        if (isset($endpoint['state'])) {
            switch ($endpoint['state']) {
                case 1:
                    return 'online';
                case 2:
                    return 'offline';
                case 3:
                    return 'suspended';
                default:
                    return 'unknown';
            }
        }
        return 'unknown';
    }

    private function createEndpoint($data)
    {
        $sql = "INSERT INTO endpoints (" . implode(", ", array_keys($data)) . ") 
                VALUES (" . implode(", ", array_fill(0, count($data), "?")) . ")";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        
        Logger::info('Endpoint created successfully', [
            'endpoint_id' => $data['endpoint_id'],
            'last_insert_id' => $this->db->lastInsertId(),
            'rows_affected' => $stmt->rowCount()
        ]);
    }

    private function updateEndpoint($data)
    {
        $endpoint_id = $data['endpoint_id'];
        unset($data['endpoint_id']);
        
        $sql = "UPDATE endpoints SET " . 
               implode(", ", array_map(fn($key) => "$key = ?", array_keys($data))) . 
               " WHERE endpoint_id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([...array_values($data), $endpoint_id]);
        
        Logger::info('Endpoint updated successfully', [
            'endpoint_id' => $endpoint_id,
            'rows_affected' => $stmt->rowCount()
        ]);
    }

    private function mapEndpointState($state)
    {
        // Se for string, mapeia para inteiro
        if (is_string($state)) {
            $stateMap = [
                'active' => 1,
                'inactive' => 0,
                'pending' => 2,
                'installing' => 3,
                'installing_failed' => 4,
                'uninstalling' => 5,
                'uninstalling_failed' => 6,
                'suspended' => 7
            ];
            return $stateMap[$state] ?? 0;
        }

        // Se já for inteiro, retorna o próprio valor
        return (int)$state;
    }

    public function create(array $data)
    {
        try {
            Logger::debug('Starting endpoint creation', [
                'endpoint_id' => $data['endpoint_id'] ?? null,
                'name' => $data['name'] ?? null,
                'api_key_id' => $data['api_key_id'] ?? null
            ]);

            // Log detalhado de cada campo
            foreach ($data as $key => $value) {
                Logger::debug('Processing field for create', [
                    'field' => $key,
                    'value' => $value,
                    'type' => gettype($value),
                    'is_null' => $value === null ? 'true' : 'false',
                    'value_length' => is_string($value) ? strlen($value) : 'not_string'
                ]);
            }

            $fields = implode(', ', array_keys($data));
            $placeholders = implode(', ', array_fill(0, count($data), '?'));
            
            $sql = "INSERT INTO {$this->table} ($fields) VALUES ($placeholders)";
            
            Logger::debug('Prepared create query', [
                'sql' => $sql,
                'total_fields' => count($data),
                'fields' => array_keys($data)
            ]);

            try {
                $this->db->beginTransaction();
                
                $stmt = $this->db->prepare($sql);
                $values = array_values($data);
                
                Logger::debug('Attempting to execute query', [
                    'values' => $values,
                    'sql' => $sql
                ]);

                $result = $stmt->execute($values);

                if ($result) {
                    $this->db->commit();
                    Logger::info('Endpoint created successfully', [
                        'endpoint_id' => $data['endpoint_id'] ?? null,
                        'last_insert_id' => $this->db->lastInsertId(),
                        'rows_affected' => $stmt->rowCount()
                    ]);
                } else {
                    $this->db->rollBack();
                    Logger::error('Failed to execute endpoint creation query', [
                        'error_info' => $stmt->errorInfo(),
                        'sql' => $sql,
                        'values' => $values
                    ]);
                }

                return $result;

            } catch (\PDOException $e) {
                $this->db->rollBack();
                Logger::error('PDO Exception during endpoint creation', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'sql_state' => $e->errorInfo[0] ?? null,
                    'driver_error' => $e->errorInfo[1] ?? null,
                    'driver_message' => $e->errorInfo[2] ?? null,
                    'trace' => $e->getTraceAsString()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Logger::error('General exception during endpoint creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    public function update($id, array $data)
    {
        try {
            // Se houver estado no data, garante que seja mapeado
            if (isset($data['state'])) {
                $data['state'] = $this->mapEndpointState($data['state']);
            }

            Logger::info('Updating record', [
                'table' => $this->table,
                'id' => $id,
                'data' => $data
            ]);

            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                Logger::debug('Processing field for update', [
                    'field' => $key,
                    'value' => $value,
                    'type' => gettype($value),
                    'is_null' => $value === null ? 'true' : 'false'
                ]);

                if ($value === null) {
                    $fields[] = "$key = NULL";
                    Logger::debug('Field set to NULL', [
                        'field' => $key
                    ]);
                } else {
                    $fields[] = "$key = ?";
                    $values[] = $value;
                    Logger::debug('Field added to update', [
                        'field' => $key,
                        'value' => $value,
                        'value_length' => is_string($value) ? strlen($value) : 'not_string'
                    ]);
                }
            }
            $values[] = $id;

            $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE {$this->primaryKey} = ?";
            
            Logger::debug('Prepared update query', [
                'sql' => $sql,
                'total_fields' => count($fields),
                'total_values' => count($values),
                'fields' => $fields
            ]);

            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);

            Logger::debug('Update result', [
                'success' => $result,
                'rows_affected' => $stmt->rowCount(),
                'last_query' => $stmt->queryString
            ]);

            return $result;
        } catch (\Exception $e) {
            Logger::error('Update failed', [
                'error' => $e->getMessage(),
                'sql' => $sql ?? null,
                'data' => $data,
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function syncCustomGroups($data)
    {
        try {
            // Verificar se temos o api_key_id
            if (!isset($data['api_key_id'])) {
                throw new \Exception('API Key ID is required for syncing custom groups');
            }

            $apiKeyId = $data['api_key_id'];
            $groups = $data['items'] ?? [];

            if (empty($groups)) {
                Logger::debug('No custom groups to sync');
                return true;
            }

            $this->db->beginTransaction();

            Logger::debug('Starting custom groups sync', [
                'groups_count' => count($groups),
                'api_key_id' => $apiKeyId
            ]);

            foreach ($groups as $group) {
                if (!isset($group['id'])) {
                    Logger::error('Invalid group data - missing ID', ['group' => $group]);
                    continue;
                }

                try {
                    // Preparar dados do grupo
                    $groupData = [
                        'group_id' => $group['id'],
                        'name' => $group['name'] ?? '',
                        'parent_id' => $group['parentId'] ?? null,
                        'api_key_id' => $apiKeyId
                    ];

                    // Verificar se o grupo já existe
                    $stmt = $this->db->prepare("SELECT 1 FROM custom_groups WHERE group_id = ?");
                    $stmt->execute([$groupData['group_id']]);

                    if ($stmt->fetch()) {
                        // Update
                        $stmt = $this->db->prepare(
                            "UPDATE custom_groups 
                            SET name = ?, parent_id = ?, api_key_id = ?, updated_at = NOW()
                            WHERE group_id = ?"
                        );
                        $stmt->execute([
                            $groupData['name'],
                            $groupData['parent_id'],
                            $groupData['api_key_id'],
                            $groupData['group_id']
                        ]);
                    } else {
                        // Insert
                        $stmt = $this->db->prepare(
                            "INSERT INTO custom_groups 
                            (group_id, name, parent_id, api_key_id) 
                            VALUES (?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $groupData['group_id'],
                            $groupData['name'],
                            $groupData['parent_id'],
                            $groupData['api_key_id']
                        ]);
                    }

                    Logger::debug('Processed group successfully', [
                        'group_id' => $groupData['group_id'],
                        'api_key_id' => $groupData['api_key_id']
                    ]);

                } catch (\Exception $e) {
                    Logger::error('Failed to process group', [
                        'group_id' => $group['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            Logger::error('Failed to sync custom groups', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncScanTasks($tasks, $apiKeyId)
    {
        try {
            if (!isset($tasks['items']) || empty($tasks['items'])) {
                Logger::debug('No scan tasks to sync');
                return true;
            }

            $this->db->beginTransaction();

            foreach ($tasks['items'] as $task) {
                try {
                    if (!isset($task['id'])) {
                        Logger::error('Missing required field id for scan task', [
                            'task' => $task
                        ]);
                        continue;
                    }

                    $taskData = [
                        'id' => $task['id'],
                        'api_key_id' => $apiKeyId,
                        'name' => $task['name'],
                        'start_date' => $task['startDate'] ?? null,
                        'status' => $task['status'] ?? 0
                    ];

                    // Verifica se a tarefa já existe
                    $stmt = $this->db->prepare("SELECT 1 FROM scan_tasks WHERE id = ?");
                    $stmt->execute([$taskData['id']]);

                    if ($stmt->fetch()) {
                        $stmt = $this->db->prepare(
                            "UPDATE scan_tasks 
                            SET name = ?, start_date = ?, status = ?, api_key_id = ? 
                            WHERE id = ?"
                        );
                        $stmt->execute([
                            $taskData['name'],
                            $taskData['start_date'],
                            $taskData['status'],
                            $taskData['api_key_id'],
                            $taskData['id']
                        ]);
                    } else {
                        $stmt = $this->db->prepare(
                            "INSERT INTO scan_tasks 
                            (id, name, start_date, status, api_key_id) 
                            VALUES (?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([
                            $taskData['id'],
                            $taskData['name'],
                            $taskData['start_date'],
                            $taskData['status'],
                            $taskData['api_key_id']
                        ]);
                    }

                } catch (\Exception $e) {
                    Logger::error('Failed to process scan task', [
                        'task' => $task,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync scan tasks', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncInventoryItems($items, $apiKeyId)
    {
        try {
            if (!isset($items['items']) || empty($items['items'])) {
                Logger::debug('No inventory items to sync');
                return true;
            }

            $this->db->beginTransaction();

            Logger::debug('Starting inventory items sync', [
                'items_count' => count($items['items'])
            ]);

            foreach ($items['items'] as $item) {
                try {
                    // Verifica campos obrigatórios
                    if (!isset($item['id']) || !isset($item['name']) || !isset($item['type'])) {
                        Logger::error('Missing required fields for inventory item', [
                            'item' => $item
                        ]);
                        continue;
                    }

                    $itemData = [
                        'item_id' => $item['id'],
                        'api_key_id' => $apiKeyId,
                        'name' => $item['name'],
                        'type' => $item['type'],
                        'parent_id' => $item['parentId'] ?? null,
                        'company_id' => $item['companyId'] ?? null,
                        'details' => json_encode($item['details'] ?? [])
                    ];

                    Logger::debug('Processing inventory item', [
                        'item_id' => $itemData['item_id'],
                        'name' => $itemData['name']
                    ]);

                    // Verifica se o item já existe
                    $stmt = $this->db->prepare("SELECT 1 FROM network_inventory WHERE item_id = ?");
                    $stmt->execute([$itemData['item_id']]);

                    if ($stmt->fetch()) {
                        // Atualiza item existente
                        $updateStmt = $this->db->prepare(
                            "UPDATE network_inventory 
                            SET name = ?, type = ?, parent_id = ?, company_id = ?, details = ?, api_key_id = ? 
                            WHERE item_id = ?"
                        );
                        $result = $updateStmt->execute([
                            $itemData['name'],
                            $itemData['type'],
                            $itemData['parent_id'],
                            $itemData['company_id'],
                            $itemData['details'],
                            $itemData['api_key_id'],
                            $itemData['item_id']
                        ]);
                        Logger::debug('Updated inventory item', [
                            'item_id' => $itemData['item_id']
                        ]);
                    } else {
                        // Insere novo item
                        $insertStmt = $this->db->prepare(
                            "INSERT INTO network_inventory 
                            (item_id, name, type, parent_id, company_id, details, api_key_id) 
                            VALUES (?, ?, ?, ?, ?, ?, ?)"
                        );
                        $result = $insertStmt->execute([
                            $itemData['item_id'],
                            $itemData['name'],
                            $itemData['type'],
                            $itemData['parent_id'],
                            $itemData['company_id'],
                            $itemData['details'],
                            $itemData['api_key_id']
                        ]);
                        Logger::debug('Created new inventory item', [
                            'item_id' => $itemData['item_id']
                        ]);
                    }

                } catch (\Exception $e) {
                    Logger::error('Failed to process inventory item', [
                        'item_id' => $item['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            $this->db->commit();
            Logger::debug('Inventory sync completed successfully');
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync inventory items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function deleteEndpoint($endpointId)
    {
        return $this->delete($endpointId);
    }

    public function deleteCustomGroup($groupId)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM custom_groups WHERE group_id = ?");
            return $stmt->execute([$groupId]);
        } catch (\Exception $e) {
            Logger::error('Failed to delete custom group', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateEndpointLabel($endpointId, $label)
    {
        try {
            return $this->update($endpointId, ['label' => $label]);
        } catch (\Exception $e) {
            Logger::error('Failed to update endpoint label', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function moveEndpoints($endpointIds, $groupId)
    {
        try {
            $placeholders = str_repeat('?,', count($endpointIds) - 1) . '?';
            $params = array_merge($endpointIds, [$groupId]);
            
            $stmt = $this->db->prepare(
                "UPDATE endpoints 
                SET group_id = ? 
                WHERE endpoint_id IN ($placeholders)"
            );
            
            return $stmt->execute($params);
        } catch (\Exception $e) {
            Logger::error('Failed to move endpoints', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function updateTableStructure()
    {
        try {
            // Lista de colunas necessárias e seus tipos
            $columns = [
                'company_id' => 'VARCHAR(24) NULL',
                'group_name' => 'VARCHAR(255) NULL',
                'policy_id' => 'VARCHAR(24) NULL',
                'policy_name' => 'VARCHAR(255) NULL',
                'policy_applied' => 'BOOLEAN NULL',
                'machine_type' => 'INT NULL',
                'malware_status' => 'JSON NULL',
                'agent_info' => 'JSON NULL',
                'state' => 'INT NULL',
                'modules' => 'JSON NULL',
                'move_state' => 'INT NULL',
                'managed_with_best' => 'BOOLEAN NULL',
                'risk_score' => 'JSON NULL',
                'fqdn' => 'VARCHAR(255) NULL',
                'ssid' => 'VARCHAR(255) NULL',
                'macs' => 'JSON NULL',
                'operating_system_version' => 'VARCHAR(255) NULL'
            ];

            // Verifica cada coluna e adiciona se não existir
            foreach ($columns as $column => $definition) {
                $checkColumn = $this->db->query(
                    "SELECT COUNT(*) 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '{$this->table}' 
                    AND COLUMN_NAME = '{$column}'"
                );

                if ($checkColumn->fetchColumn() == 0) {
                    $sql = "ALTER TABLE {$this->table} ADD COLUMN {$column} {$definition}";
                    $this->db->exec($sql);
                    Logger::debug("Added column {$column} to {$this->table}", [
                        'sql' => $sql
                    ]);
                }
            }

            // Adiciona índices se não existirem
            $indexes = [
                'idx_company' => ['company_id'],
                'idx_policy' => ['policy_id'],
                'idx_fqdn' => ['fqdn'],
                'idx_machine_type' => ['machine_type']
            ];

            foreach ($indexes as $indexName => $columns) {
                $checkIndex = $this->db->query(
                    "SELECT COUNT(*) 
                    FROM information_schema.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '{$this->table}' 
                    AND INDEX_NAME = '{$indexName}'"
                );

                if ($checkIndex->fetchColumn() == 0) {
                    $columnList = implode(',', $columns);
                    $this->db->exec("CREATE INDEX {$indexName} ON {$this->table} ({$columnList})");
                    Logger::debug("Added index {$indexName} to {$this->table}");
                }
            }

            Logger::debug('Table structure update completed', [
                'table' => $this->table
            ]);

        } catch (\PDOException $e) {
            Logger::error('Failed to update table structure', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function syncEndpointDetails($endpoint)
    {
        try {
            $this->db->beginTransaction();

            $endpointData = [
                'endpoint_id' => $endpoint['id'],
                'name' => $endpoint['name'],
                'status' => 'active',
                'operating_system' => $endpoint['operatingSystem'] ?? null,
                'ip_address' => $endpoint['ip'] ?? null,
                'machine_type' => $endpoint['machineType'] ?? null,
                'last_seen' => $endpoint['lastSeen'] ?? null,
                'state' => $this->mapEndpointState($endpoint['state'] ?? 'inactive'),
                'label' => $endpoint['label'] ?? '',
                'is_managed' => 1,
                'managed_with_best' => isset($endpoint['managedWithBest']) ? (int)$endpoint['managedWithBest'] : 1,
                'company_id' => $endpoint['companyId'] ?? null,
                'malware_status' => isset($endpoint['malwareStatus']) ? json_encode($endpoint['malwareStatus']) : null,
                'agent_info' => isset($endpoint['agent']) ? json_encode($endpoint['agent']) : null,
                'modules' => isset($endpoint['modules']) ? json_encode($endpoint['modules']) : null,
                'risk_score' => isset($endpoint['riskScore']) ? json_encode($endpoint['riskScore']) : null
            ];

            // Adiciona informações de política se existirem
            if (isset($endpoint['policy'])) {
                $endpointData['policy_id'] = $endpoint['policy']['id'] ?? null;
                $endpointData['policy_name'] = $endpoint['policy']['name'] ?? null;
                $endpointData['policy_applied'] = isset($endpoint['policy']['applied']) ? (int)$endpoint['policy']['applied'] : 0;
            }

            // Adiciona informações de grupo se existirem
            if (isset($endpoint['group'])) {
                $endpointData['group_id'] = $endpoint['group']['id'] ?? null;
                $endpointData['group_name'] = $endpoint['group']['name'] ?? null;
            }

            $existing = $this->find($endpoint['id']);
            if ($existing) {
                unset($endpointData['endpoint_id']);
                $this->update($endpoint['id'], $endpointData);
            } else {
                $this->create($endpointData);
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync endpoint details', [
                'error' => $e->getMessage(),
                'endpoint' => $endpoint['id']
            ]);
            throw $e;
        }
    }

    public function getInventory($page = 1, $perPage = 100, $filters = [])
    {
        try {
            $offset = ($page - 1) * $perPage;
            $params = [];
            $whereConditions = ['is_deleted = 0'];
    
            if (isset($filters['api_key_id'])) {
                $whereConditions[] = 'api_key_id = ?';
                $params[] = $filters['api_key_id'];
            }
    
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
    
            // 1. Primeiro busca os dados básicos do network_inventory
            $sql = "SELECT 
                    item_id,
                    api_key_id,
                    name,
                    parent_id,
                    type,
                    details,
                    company_id,
                    lastSeen,
                    is_deleted,
                    created_at,
                    updated_at
                FROM network_inventory
                {$whereClause}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?";
    
            $stmt = $this->db->prepare($sql);
            $params[] = $perPage;
            $params[] = $offset;
            $stmt->execute($params);
            $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
            if (!empty($items)) {
                // Coleta todos os IDs para buscar dados relacionados
                $itemIds = array_column($items, 'item_id');
                $placeholders = str_repeat('?,', count($itemIds) - 1) . '?';
    
                // 2. Busca dados dos endpoints
                $sqlEndpoints = "SELECT 
                    endpoint_id,
                    group_id,
                    is_managed,
                    status,
                    ip_address,
                    operating_system,
                    operating_system_version,
                    label,
                    last_seen,
                    machine_type,
                    policy_id,
                    policy_applied,
                    malware_status,
                    agent_info,
                    state,
                    modules,
                    managed_with_best,
                    risk_score,
                    fqdn,
                    macs,
                    ssid
                FROM endpoints 
                WHERE endpoint_id IN ($placeholders)";
    
                $stmtEndpoints = $this->db->prepare($sqlEndpoints);
                $stmtEndpoints->execute($itemIds);
                $endpoints = $stmtEndpoints->fetchAll(\PDO::FETCH_ASSOC);
                $endpointsMap = array_column($endpoints, null, 'endpoint_id');
    
                // 3. Busca políticas relacionadas
                $policyIds = array_unique(array_column($endpoints, 'policy_id'));
                if (!empty($policyIds)) {
                    $policyPlaceholders = str_repeat('?,', count($policyIds) - 1) . '?';
                    $sqlPolicies = "SELECT id, name, settings FROM policies WHERE id IN ($policyPlaceholders)";
                    $stmtPolicies = $this->db->prepare($sqlPolicies);
                    $stmtPolicies->execute($policyIds);
                    $policies = $stmtPolicies->fetchAll(\PDO::FETCH_ASSOC);
                    $policiesMap = array_column($policies, null, 'id');
                }
    
                // Mescla os dados
                foreach ($items as &$item) {
                    $endpoint = $endpointsMap[$item['item_id']] ?? null;
                    
                    // Processa os campos JSON
                    $networkDetails = is_string($item['details']) ? 
                        json_decode($item['details'], true) : 
                        ($item['details'] ?? []);
    
                    $details = [
                        'label' => $endpoint['label'] ?? '',
                        'fqdn' => $endpoint['fqdn'] ?? '',
                        'groupId' => $endpoint['group_id'] ?? '',
                        'isManaged' => (bool)($endpoint['is_managed'] ?? false),
                        'machineType' => (int)($endpoint['machine_type'] ?? 0),
                        'operatingSystemVersion' => $endpoint['operating_system_version'] ?? '',
                        'ip' => $endpoint['ip_address'] ?? '',
                        'macs' => json_decode($endpoint['macs'] ?? '[]', true),
                        'ssid' => $endpoint['ssid'] ?? '',
                        'managedWithBest' => (bool)($endpoint['managed_with_best'] ?? false),
                        'modules' => json_decode($endpoint['modules'] ?? '[]', true),
                        'status' => $endpoint['status'] ?? null,
                        'malware_status' => json_decode($endpoint['malware_status'] ?? 'null', true),
                        'agent_info' => json_decode($endpoint['agent_info'] ?? 'null', true),
                        'state' => (int)($endpoint['state'] ?? 0),
                        'risk_score' => json_decode($endpoint['risk_score'] ?? 'null', true)
                    ];
    
                    if ($endpoint && isset($endpoint['policy_id']) && isset($policiesMap[$endpoint['policy_id']])) {
                        $policy = $policiesMap[$endpoint['policy_id']];
                        $details['policy'] = [
                            'id' => $endpoint['policy_id'],
                            'name' => $policy['name'],
                            'applied' => (bool)$endpoint['policy_applied'],
                            'settings' => json_decode($policy['settings'] ?? '{}', true)
                        ];
                    }
    
                    // Mescla com network_details
                    if (!empty($networkDetails)) {
                        $details = array_merge($networkDetails, $details);
                    }
    
                    $item['details'] = $details;
                    $item['lastSeen'] = $item['lastSeen'] ? date('c', strtotime($item['lastSeen'])) : null;
                    $item['api_key_id'] = (int)$item['api_key_id'];
                    $item['is_deleted'] = (int)$item['is_deleted'];
                }
            }
    
            // Conta total de registros
            $sqlCount = "SELECT COUNT(*) as total FROM network_inventory {$whereClause}";
            $stmtCount = $this->db->prepare($sqlCount);
            $stmtCount->execute(array_slice($params, 0, -2));
            $total = $stmtCount->fetch(\PDO::FETCH_ASSOC)['total'];
    
            return [
                'items' => $items,
                'total' => (int)$total,
                'page' => (int)$page,
                'perPage' => (int)$perPage,
                'pagesCount' => ceil($total / $perPage)
            ];
    
        } catch (\Exception $e) {
            Logger::error('Failed to get inventory', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function syncNetworkInventory($items, $apiKeyId)
    {
        try {
            Logger::info('Starting network inventory sync', [
                'total_items' => count($items)
            ]);

            $this->db->beginTransaction();

            // Marca todos os itens como deletados inicialmente
            $stmt = $this->db->prepare("UPDATE network_inventory SET is_deleted = 1 WHERE api_key_id = :api_key_id");
            $stmt->execute(['api_key_id' => $apiKeyId]);

            foreach ($items as $item) {
                Logger::info('Raw item data', [
                    'item_id' => $item['id'],
                    'details' => $item['details'] ?? 'no_details',
                    'raw_item' => $item
                ]);

                // Preparar dados para inserção/atualização
                $data = [
                        'item_id' => $item['id'],
                        'api_key_id' => $apiKeyId,
                        'name' => $item['name'],
                        'type' => $item['type'],
                        'parent_id' => $item['parentId'] ?? null,
                        'company_id' => $item['companyId'] ?? null,
                    'details' => json_encode($item['details'] ?? []),
                    'lastSeen' => isset($item['lastSeen']) ? date('Y-m-d H:i:s', strtotime($item['lastSeen'])) : null,
                    'is_deleted' => 0,
                    'updated_at' => date('Y-m-d H:i:s')
                ];

                // Verificar se o item já existe
                $stmt = $this->db->prepare("SELECT 1 FROM network_inventory WHERE item_id = :item_id");
                $stmt->execute(['item_id' => $data['item_id']]);
                $exists = $stmt->fetchColumn();

                if ($exists) {
                    $sql = "UPDATE network_inventory SET 
                        name = :name,
                        api_key_id = :api_key_id,
                        type = :type,
                        parent_id = :parent_id,
                        company_id = :company_id,
                        details = :details,
                        lastSeen = :lastSeen,
                        is_deleted = :is_deleted,
                        updated_at = :updated_at
                        WHERE item_id = :item_id";
                    } else {
                    $sql = "INSERT INTO network_inventory 
                        (item_id, api_key_id, name, type, parent_id, company_id, details, lastSeen, is_deleted, updated_at)
                        VALUES 
                        (:item_id, :api_key_id, :name, :type, :parent_id, :company_id, :details, :lastSeen, :is_deleted, :updated_at)";
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($data);
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            if ($this->db->inTransaction()) {
            $this->db->rollBack();
            }
            Logger::error('Failed to sync network inventory', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredNetworks($filters)
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

            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered networks', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }


    private function updateNetworkInventoryStructure()
    {
        try {
            // Verifica se a coluna is_deleted existe
                $checkColumn = $this->db->query(
                    "SELECT COUNT(*) 
                    FROM information_schema.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'network_inventory' 
                AND COLUMN_NAME = 'is_deleted'"
                );

                if ($checkColumn->fetchColumn() == 0) {
                $this->db->exec("ALTER TABLE network_inventory ADD COLUMN is_deleted BOOLEAN DEFAULT false");
                $this->db->exec("ALTER TABLE network_inventory ADD COLUMN lastSeen TIMESTAMP NULL");
                $this->db->exec("CREATE INDEX idx_is_deleted ON network_inventory (is_deleted)");
            }
        } catch (\PDOException $e) {
            Logger::error('Failed to update network_inventory structure', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function updateEndpointsStructure()
    {
        try {
            // Verifica se a coluna last_seen existe
            $checkColumn = $this->db->query(
                "SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'endpoints' 
                AND COLUMN_NAME = 'last_seen'"
            );

            if ($checkColumn->fetchColumn() == 0) {
                $this->db->exec("ALTER TABLE endpoints ADD COLUMN last_seen TIMESTAMP NULL");
                $this->db->exec("CREATE INDEX idx_last_seen ON endpoints (last_seen)");
                Logger::info('Added last_seen column to endpoints table');
            }
        } catch (\PDOException $e) {
            Logger::error('Failed to update endpoints structure', [
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
            $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decodifica os campos JSON para cada resultado
            foreach ($results as &$result) {
                if (isset($result['malware_status']) && is_string($result['malware_status'])) {
                    $result['malware_status'] = json_decode($result['malware_status'], true);
                }
                if (isset($result['agent_info']) && is_string($result['agent_info'])) {
                    $result['agent_info'] = json_decode($result['agent_info'], true);
                }
                if (isset($result['modules']) && is_string($result['modules'])) {
                    $result['modules'] = json_decode($result['modules'], true);
                }
                if (isset($result['risk_score']) && is_string($result['risk_score'])) {
                    $result['risk_score'] = json_decode($result['risk_score'], true);
                }
                if (isset($result['macs']) && is_string($result['macs'])) {
                    $result['macs'] = json_decode($result['macs'], true);
                }
            }

            return $results;
        } catch (\PDOException $e) {
            Logger::error('Failed to find networks by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getCustomGroups($filters = [])
    {
        try {
            $sql = "SELECT * FROM custom_groups WHERE 1=1";
            $params = [];

            if (isset($filters['api_key_id'])) {
                $sql .= " AND api_key_id = ?";
                $params[] = $filters['api_key_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);

        } catch (\PDOException $e) {
            Logger::error('Failed to get custom groups', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
