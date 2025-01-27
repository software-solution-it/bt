<?php

namespace App\Services;

use App\Core\Service;
use App\Core\Logger;
use App\Model\PoliciesModel;

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
            Logger::info('Starting full sync process', [
                'params' => $params
            ]);
            
            $results = [];

            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Sync endpoints
            Logger::info('Starting endpoints sync');
            $endpoints = $this->networkService->getEndpointsList([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 10000
            ]);
            Logger::info('Endpoints data received', [
                'data_type' => gettype($endpoints),
                'has_items' => isset($endpoints['items'])
            ]);
            $results['endpoints'] = $endpoints;

            // Sync accounts
            Logger::info('Starting accounts sync');
            $results['accounts'] = $this->accountService->getAccounts([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Accounts sync completed', [
                'data_type' => gettype($results['accounts'])
            ]);

            // Sync blocklist
            Logger::info('Starting blocklist sync');
            $results['blocklist'] = $this->incidentsService->getBlocklistItems([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Blocklist sync completed', [
                'data_type' => gettype($results['blocklist'])
            ]);

            // Sync license
            Logger::info('Starting license sync');
            $results['license'] = $this->licensingService->getLicenseInfo([
                'api_key_id' => $params['api_key_id']
            ]);
            Logger::info('License sync completed', [
                'data_type' => gettype($results['license'])
            ]);

            // Sync scan tasks
            Logger::info('Starting scan tasks sync');
            $results['scanTasks'] = $this->networkService->getScanTasksList([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Scan tasks sync completed', [
                'data_type' => gettype($results['scanTasks'])
            ]);

            // Sync custom groups
            Logger::info('Starting custom groups sync');
            $customGroups = $this->networkService->getCustomGroupsList([
                'api_key_id' => $params['api_key_id']
            ]) ?? [];
            $results['customGroups'] = $customGroups;
            Logger::info('Custom groups sync completed', [
                'data_type' => gettype($results['customGroups'])
            ]);

            // Sync network inventory
            Logger::info('Starting network inventory sync');
            $networkInventory = $this->networkService->getNetworkInventoryItems([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100,
                'filters' => [
                    'type' => [
                        'computers' => true
                    ],
                    'depth' => [
                        'allItemsRecursively' => true
                    ]
                ]
            ]);
            Logger::info('Network inventory initial data received', [
                'data_type' => gettype($networkInventory),
                'total_items' => $networkInventory['total'] ?? 0
            ]);
            $results['networkInventory'] = $networkInventory;

            // Sync installation links
            Logger::info('Starting installation links sync');
            $results['installationLinks'] = $this->packagesService->getInstallationLinks([
                'api_key_id' => $params['api_key_id']
            ]);
            Logger::info('Installation links sync completed', [
                'data_type' => gettype($results['installationLinks'])
            ]);

            // Sync packages
            Logger::info('Starting packages sync');
            $results['packages'] = $this->packagesService->getPackagesList([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Packages sync completed', [
                'data_type' => gettype($results['packages']),
                'packages_data' => $results['packages']
            ]);

            // Sync policies
            Logger::info('Starting policies sync');
            $policiesResult = $this->policiesService->getPoliciesList([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Policies data received', [
                'data_type' => gettype($policiesResult),
                'data' => $policiesResult
            ]);

            if (is_array($policiesResult)) {
                $results['policies'] = $policiesResult;
                if (isset($policiesResult['items'])) {
                    Logger::info('Starting policies details sync');
                    $this->syncPolicies($policiesResult['items'], $params['api_key_id']);
                    Logger::info('Policies details sync completed');
                }
            }

            // Sync quarantine
            Logger::info('Starting quarantine sync');
            $quarantineResult = $this->quarantineService->getQuarantineItemsList('computers', [
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Quarantine data received', [
                'data_type' => gettype($quarantineResult),
                'data' => $quarantineResult
            ]);

            if ($quarantineResult) {
                $results['quarantine'] = $this->formatQuarantineData($quarantineResult);
            }

            // Sync reports
            Logger::info('Starting reports sync');
            $results['reports'] = $this->reportsService->getReportsList([
                'api_key_id' => $params['api_key_id'],
                'page' => 1,
                'perPage' => 100
            ]);
            Logger::info('Reports sync completed', [
                'data_type' => gettype($results['reports'])
            ]);

            Logger::info('Full sync process completed successfully', [
                'total_results' => count($results)
            ]);
            return $results;

        } catch (\Exception $e) {
            Logger::error('Full sync process failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
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
}
