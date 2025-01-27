<?php

namespace App\Services;

use App\Core\Service;
use App\Model\NetworkModel;
use App\Core\Logger;
use App\Model\ApiKeysModel;
use App\Model\MachineModel;

class MachineService extends Service
{
    private $networkModel;
    private $machineModel;

    public function __construct()
    {
        $this->networkModel = new NetworkModel();
        $this->machineModel = new MachineModel();
    }

    public function getMachineInventory($params)
    {
        try {
            if (!isset($params['api_key_id'])) { 
                throw new \Exception('API Key ID is required');
            }

            Logger::debug('MachineService::getMachineInventory called', [
                'params' => $params,
                'api_key_id' => $params['api_key_id']
            ]);

            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($params['api_key_id']);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            // Se tiver ID específico, ajusta os parâmetros
            if (isset($params['filters']['machineId'])) {
                Logger::debug('Filtering by specific machine ID', [
                    'machineId' => $params['filters']['machineId']
                ]);
                
                // Força página 1 e 1 item por página
                $params['page'] = 1;
                $params['perPage'] = 1;
            }

            // Busca os dados básicos do inventário
            Logger::debug('Calling networkModel->getInventory', [
                'page' => $params['page'] ?? 1,
                'perPage' => $params['perPage'] ?? 100
            ]);

            $inventory = $this->networkModel->getInventory(
                $params['page'] ?? 1,
                $params['perPage'] ?? 100,
                array_merge($params['filters'] ?? [], ['api_key_id' => $params['api_key_id']])
            );

            Logger::debug('Network inventory response', [
                'type' => gettype($inventory),
                'is_array' => is_array($inventory),
                'has_items' => isset($inventory['items']),
                'raw_data' => $inventory
            ]);

            // Ensure inventory has the expected structure
            if (!is_array($inventory)) {
                $inventory = [
                    'items' => [],
                    'total' => 0,
                    'page' => $params['page'] ?? 1,
                    'perPage' => $params['perPage'] ?? 100,
                    'pagesCount' => 0
                ];
            }

            // Ensure items exists and is an array
            if (!isset($inventory['items']) || !is_array($inventory['items'])) {
                $inventory['items'] = [];
            }

            // Se está buscando por ID e não encontrou, retorna vazio
            if (isset($params['filters']['machineId']) && empty($inventory['items'])) {
                return [
                    'items' => [],
                    'total' => 0,
                    'page' => 1,
                    'perPage' => 1,
                    'pagesCount' => 0
                ];
            }

            // Enriquece os dados com informações adicionais
            foreach ($inventory['items'] as &$item) {
                // Ensure details is an array
                $details = is_array($item['details']) ? $item['details'] : [];
                
                // Adiciona informações de módulos da política
                if (isset($details['policy']['settings']) && is_array($details['policy']['settings'])) {
                    $policySettings = $details['policy']['settings'];
                    $details['modules'] = $this->extractModulesFromPolicy($policySettings);
                }

                // Formata o tipo da máquina
                $details['machineType'] = isset($item['type']) && $item['type'] === 5 ? 1 : 2;

                // Garante campos padrão com valores existentes ou defaults
                $details = array_merge([
                    'isManaged' => true,
                    'managedWithBest' => true,
                    'productOutdated' => false,
                    'macs' => [],
                    'ssid' => '',
                    'label' => '',
                    'state' => isset($details['state']) ? (int)$details['state'] : 0,
                    'fqdn' => '',
                    'groupId' => '',
                    'operatingSystemVersion' => '',
                    'ip' => '',
                    'policy' => []
                ], $details);

                // Garante que state é sempre um número
                $details['state'] = (int)$details['state'];

                // Substitui o campo details original pelos dados organizados
                $item['details'] = $details;

                // Garante que lastSuccessfulScan está no formato correto
                if (isset($item['lastSuccessfulScan']) && is_array($item['lastSuccessfulScan']) && isset($item['lastSuccessfulScan']['date'])) {
                    $item['lastSuccessfulScan']['date'] = date('c', strtotime($item['lastSuccessfulScan']['date']));
                }

                Logger::debug('Processed machine item', [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? 'unknown',
                    'state' => $item['details']['state'],
                    'details' => $item['details']
                ]);
            }

            // After processing the inventory items, save them to database
            if (!empty($inventory['items'])) {
                $this->saveMachineInventory($inventory['items'], $params['api_key_id']);
                Logger::debug('Saved machine inventory to database', [
                    'count' => count($inventory['items'])
                ]);
            }

            return $inventory;

        } catch (\Exception $e) {
            Logger::error('Failed to get machine inventory', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function extractModulesFromPolicy($settings)
    {
        $modules = [
            'antimalware' => false,
            'firewall' => false,
            'contentControl' => false,
            'powerUser' => false,
            'deviceControl' => false,
            'advancedThreatControl' => false,
            'applicationControl' => false,
            'encryption' => false,
            'networkAttackDefense' => false,
            'antiTampering' => false,
            'advancedAntiExploit' => false,
            'userControl' => false,
            'antiphishing' => false,
            'trafficScan' => false,
            'edrSensor' => false,
            'hyperDetect' => false,
            'remoteEnginesScanning' => false,
            'sandboxAnalyzer' => false,
            'riskManagement' => false
        ];

        if (isset($settings['general']['modules'])) {
            foreach ($settings['general']['modules'] as $module => $enabled) {
                if (array_key_exists($module, $modules)) {
                    $modules[$module] = (bool)$enabled;
                }
            }
        }

        return $modules;
    }

    public function getFilteredMachines($filters)
    {
        try {
            Logger::debug('MachineService::getFilteredMachines called', [
                'filters' => $filters
            ]);

            return $this->machineModel->getFilteredMachines($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered machines', [
                'error' => $e->getMessage() 
            ]);
            throw $e;
        }
    }

    public function saveMachineInventory($machines, $apiKeyId)
    {
        try {
            Logger::debug('Starting to save machine inventory', [
                'total_machines' => count($machines),
                'api_key_id' => $apiKeyId
            ]);

            foreach ($machines as $machine) {
                try {
                    // Format machine data for database
                    $machineData = [
                        'api_key_id' => $apiKeyId,
                        'machine_id' => $machine['details']['id'] ?? $machine['id'] ?? null,
                        'name' => $machine['name'] ?? null,
                        'type' => $machine['details']['machineType'] ?? null,
                        'state' => $machine['details']['state'] ?? 0,
                        'group_id' => $machine['details']['groupId'] ?? null,
                        'policy_id' => $machine['details']['policy']['id'] ?? null,
                        'is_managed' => $machine['details']['isManaged'] ? 1 : 0,
                        'details' => json_encode($machine['details']),
                        'last_seen' => $machine['details']['lastSeen'] ?? null,
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];

                    $this->machineModel->saveMachine($machineData);
                    
                    Logger::debug('Saved machine successfully', [
                        'machine_id' => $machineData['machine_id'],
                        'name' => $machineData['name']
                    ]);
                } catch (\Exception $e) {
                    Logger::error('Failed to save individual machine', [
                        'machine_id' => $machine['id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            return true;
        } catch (\Exception $e) {
            Logger::error('Failed to save machine inventory', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAllInventoryData($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            Logger::debug('MachineService::getAllInventoryData called', [
                'api_key_id' => $params['api_key_id']
            ]);

            $result = $this->machineModel->getAllInventoryData($params['api_key_id']);

            return $result;

        } catch (\Exception $e) {
            Logger::error('Failed to get all inventory data', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
