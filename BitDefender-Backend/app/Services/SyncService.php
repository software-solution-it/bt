<?php

namespace App\Services;

use App\Core\Service;
use App\Core\Logger;
use App\Model\PoliciesModel;
use App\Model\SyncModel;

class SyncService extends Service
{
    private $accountService;
    private $companiesService;
    private $incidentsService;
    private $integrationsService;
    private $licensingService;
    private $networkService;
    private $packagesService;
    private $policiesService;
    private $pushService;
    private $quarantineService;
    private $reportsService;
    private $machineService; 
    private $policiesModel;
    private $syncModel;

    public function __construct()
    {
        $this->accountService = new AccountService();
        $this->companiesService = new CompaniesService();
        $this->incidentsService = new IncidentsService();
        $this->integrationsService = new IntegrationsService();
        $this->licensingService = new LicensingService();
        $this->networkService = new NetworkService();
        $this->packagesService = new PackagesService();
        $this->policiesService = new PoliciesService(); 
        $this->pushService = new PushService();
        $this->quarantineService = new QuarantineService();
        $this->reportsService = new ReportsService();
        $this->machineService = new MachineService();
        $this->policiesModel = new PoliciesModel();
        $this->syncModel = new SyncModel();
    }

    private function formatQuarantineData($rawData)
    {
        if (!isset($rawData['items']) || !is_array($rawData['items'])) {
            return [];
        }

        return array_map(function($item) {
            return [
                'fileName' => $item['fileName'] ?? $item['file_name'] ?? null,
                'itemId' => $item['itemId'] ?? $item['id'] ?? null,
                'filePath' => $item['filePath'] ?? $item['file_path'] ?? '',
                'detectionName' => $item['detectionName'] ?? $item['detection_name'] ?? '',
                'detectionTime' => $item['detectionTime'] ?? $item['detection_time'] ?? null,
                'endpointId' => $item['endpointId'] ?? $item['endpoint_id'] ?? '',
                'endpointName' => $item['endpointName'] ?? $item['endpoint_name'] ?? '',
                'status' => $item['status'] ?? 'pending'
            ];
        }, $rawData['items']);
    }

    public function syncAll($params)
    {
        try {
            $apiKey = $this->syncModel->getApiKeyWithService($params['api_key_id']);
            if (!$apiKey) {
                throw new \Exception('API Key not found');
            }

            $lastSync = $this->syncModel->getLastSyncTime($params['api_key_id']);
            $currentTime = time();

            Logger::info('Starting sync process', [
                'last_sync' => $lastSync ? date('Y-m-d H:i:s', $lastSync) : 'never',
                'api_key_id' => $params['api_key_id'],
                'service_type' => $apiKey['service_type']
            ]);

            // Sincroniza políticas primeiro
            $policiesService = new PoliciesService();
            $policies = $policiesService->getPoliciesList([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);

            Logger::info('Policies synced', [
                'total_policies' => count($policies['items'] ?? [])
            ]);

            // Depois sincroniza máquinas com as políticas atualizadas
            $machineService = new MachineService();
            $machines = $machineService->getMachineInventory([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);

            $syncOperations = [];
            
            if ($apiKey['service_type'] === 'Produtos') {
                // Para Produtos, apenas licenças com informações básicas
                $syncOperations['licenses'] = [
                    'function' => function() use ($params, $lastSync, $apiKey) {
                        $licenseInfo = $this->licensingService->getLicenseInfo([
                            'api_key_id' => $params['api_key_id']
                        ]);

                        // Atualiza o nome da licença para o nome da empresa
                        if (isset($licenseInfo['items'])) {
                            foreach ($licenseInfo['items'] as &$license) {
                                $license['name'] = $apiKey['name']; // Nome da empresa/chave
                                // Mantém apenas informações relevantes
                                $license = [
                                    'name' => $license['name'],
                                    'expiry_date' => $license['expiryDate'] ?? null,
                                    'total_slots' => $license['totalSlots'] ?? 0,
                                    'used_slots' => $license['usedSlots'] ?? 0
                                ];
                            }
                        }

                        return $licenseInfo;
                    },
                    'interval' => 3600 // 1 hora
                ];
            } else {
                // Para Serviços, mantém todas as operações
                $syncOperations = [
                    'endpoints' => [
                        'function' => function() use ($params, $lastSync) {
                            return $this->networkService->getEndpointsList([
                                'api_key_id' => $params['api_key_id'],
                                'page' => 1,
                                'perPage' => 100,
                                'filters' => [
                                    'updatedAfter' => $lastSync ? date('Y-m-d\TH:i:s\Z', $lastSync) : null
                                ]
                            ]);
                        },
                        'interval' => 300
                    ],
                    'accounts' => [
                        'function' => function() use ($params, $lastSync) {
                            return $this->accountService->getAccounts([
                                'api_key_id' => $params['api_key_id'],
                                'filters' => ['updatedAfter' => $lastSync ? date('Y-m-d\TH:i:s\Z', $lastSync) : null]
                            ]);
                        },
                        'interval' => 3600
                    ],
                    'policies' => [
                        'function' => function() use ($params, $lastSync) {
                            return $this->policiesService->getPoliciesList([
                                'api_key_id' => $params['api_key_id'],
                                'filters' => ['updatedAfter' => $lastSync ? date('Y-m-d\TH:i:s\Z', $lastSync) : null]
                            ]);
                        },
                        'interval' => 7200
                    ]
                ];
            }

            foreach ($syncOperations as $key => $operation) {
                try {
                    $lastOperationSync = $this->syncModel->getLastOperationSync($params['api_key_id'], $key);
                    $shouldSync = !$lastOperationSync || (time() - $lastOperationSync) > $operation['interval'];

                    if (!$shouldSync) {
                        continue;
                    }

                    Logger::info("Starting {$key} sync");
                    $operation['function']();
                    $this->syncModel->updateLastOperationSync($params['api_key_id'], $key);
                    Logger::info("{$key} sync completed");
                    
                } catch (\Exception $e) {
                    Logger::error("Error syncing {$key}", ['error' => $e->getMessage()]);
                    return ['success' => false, 'message' => "Failed to sync {$key}"];
                }
            }

            $this->syncModel->updateLastSyncTime($params['api_key_id'], $currentTime);
            return [
                'success' => true,
                'message' => 'Sync completed successfully',
                'data' => [
                    'policies_synced' => count($policies['items'] ?? []),
                    'machines_synced' => count($machines['items'] ?? [])
                ]
            ];

        } catch (\Exception $e) {
            Logger::error('Sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function getErrorCode(\Exception $e) {
        // Verifica o código da exceção
        $code = $e->getCode();
        if ($code) return $code;
        
        // Verifica se é uma exceção HTTP
        if ($e instanceof \GuzzleHttp\Exception\ClientException) {
            return $e->getResponse()->getStatusCode();
        }
        
        // Procura por códigos HTTP na mensagem de erro
        $message = $e->getMessage();
        if (preg_match('/HTTP Error: (\d{3})/', $message, $matches)) {
            return (int)$matches[1];
        }
        
        return 500; // Fallback para erro genérico
    }

    private function isPermissionError($errorCode) {
        return in_array($errorCode, [401, 403]);
    }

    private function syncPolicies($policies, $apiKeyId)
    {
        try {
            Logger::debug('Starting policy sync', [
                'total_policies' => count($policies),
                'api_key_id' => $apiKeyId
            ]);

            foreach ($policies as $policy) {
                try {
                    $policyDetails = $this->policiesService->getPolicyDetails([
                        'policyId' => $policy['id'],
                        'api_key_id' => $apiKeyId
                    ]);

                    if ($policyDetails) {
                        Logger::debug('Policy details fetched successfully', [
                            'policy_id' => $policy['id'],
                            'name' => $policy['name']
                        ]);
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to sync individual policy', [
                        'policy_id' => $policy['id'],
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
        } catch (\Exception $e) {
            Logger::error('Policy sync failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredAccounts($filters)
    {
        try {
            Logger::info('Fetching filtered accounts', [
                'filters' => $filters
            ]);

            return $this->accountService->getFilteredAccounts($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered accounts', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredCompanies($filters)
    {
        try {
            Logger::info('Fetching filtered companies', [
                'filters' => $filters
            ]);

            return $this->companiesService->getFilteredCompanies($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered companies', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredIncidents($filters)
    {
        try {
            Logger::info('Fetching filtered incidents', [
                'filters' => $filters
            ]);

            return $this->incidentsService->getFilteredIncidents($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered incidents', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredIntegrations($filters)
    {
        try {
            Logger::info('Fetching filtered integrations', [
                'filters' => $filters
            ]);

            return $this->integrationsService->getFilteredIntegrations($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered integrations', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredLicenses($filters)
    {
        try {
            Logger::info('Fetching filtered licenses', [
                'filters' => $filters
            ]);

            return $this->licensingService->getFilteredLicenses($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered licenses', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredMachines($filters)
    {
        try {
            Logger::info('Fetching filtered machines', [
                'filters' => $filters
            ]);

            return $this->machineService->getFilteredMachines($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered machines', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredNetworks($filters)
    {
        try {
            Logger::info('Fetching filtered networks', [
                'filters' => $filters
            ]);

            return $this->networkService->getFilteredNetworks($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered networks', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredPackages($filters)
    {
        try {
            Logger::info('Fetching filtered packages', [
                'filters' => $filters
            ]);

            return $this->packagesService->getFilteredPackages($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered packages', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredPolicies($filters)
    {
        try {
            Logger::info('Fetching filtered policies', [
                'filters' => $filters
            ]);

            return $this->policiesService->getFilteredPolicies($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered policies', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredPushSettings($filters)
    {
        try {
            Logger::info('Fetching filtered push settings', [
                'filters' => $filters
            ]);

            return $this->pushService->getFilteredPushSettings($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered push settings', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredQuarantineItems($filters)
    {
        try {
            Logger::info('Fetching filtered quarantine items', [
                'filters' => $filters
            ]);

            return $this->quarantineService->getFilteredQuarantineItems($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered quarantine items', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredReports($filters)
    {
        try {
            Logger::info('Fetching filtered reports', [
                'filters' => $filters
            ]);

            return $this->reportsService->getFilteredReports($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch filtered reports', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getInstallationLinks($params)
    {
        try {
            Logger::info('Fetching installation links', [
                'params' => $params
            ]);

            return $this->packagesService->getInstallationLinks($params);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch installation links', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getQuarantineItemsList($type, $params)
    {
        try {
            Logger::info('Fetching quarantine items list', [
                'type' => $type,
                'params' => $params
            ]);

            return $this->quarantineService->getQuarantineItemsList($type, $params);

        } catch (\Exception $e) {
            Logger::error('Failed to fetch quarantine items list', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getMachineInventory($params)
    {
        try {
            Logger::info('Fetching inventory data with filters', [
                'params' => $params
            ]);

            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Get filtered data from database
            $queryParams = [
                'api_key_id' => $params['api_key_id'],
                'filters' => $params['filters'] ?? []
            ];

            // If type is specified, only get that specific table
            if (isset($params['type'])) {
                $queryParams['tables'] = [$params['type']];
            }

            $results = $this->machineService->getAllInventoryData($queryParams);

            // If type was specified, return only that table's results
            if (isset($params['type']) && isset($results[$params['type']])) {
                $results = $results[$params['type']];
            }

            $formattedResult = [
                'items' => $results,
                'total' => count($results)
            ];

            Logger::info('Returning formatted result', [
                'total_items' => count($formattedResult['items'])
            ]);

            return $formattedResult;

        } catch (\Exception $e) {
            Logger::error('Failed to fetch inventory data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function determineState($machine)
    {
        return $machine['details']['isManaged'] ? 'active' : 'inactive';
    }

    public function getEvents()
    {
        try {
            return $this->networkService->getNetworkInventoryItems([
                'page' => 1,
                'perPage' => 100
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to get events', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
