<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\SyncService;
use App\Core\Logger;
use App\Services\MachineService;

class SyncController extends Controller
{
    private $syncService;
    
    public function __construct()
    {
        $this->syncService = new SyncService();
    }
    
    public function syncAll($params)
    {
        try {
            Logger::debug('SyncController::syncAll called', [
                'params' => $params
            ]);

            // Garantir que api_key_id está presente
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $result = $this->syncService->syncAll($params);
            
            // Ensure we're returning a properly formatted response
            return json_encode([
                'jsonrpc' => '2.0',
                'result' => $result, 
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in syncAll', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ]);
        }
    }

    public function getFilteredAccounts($params)
    {
        try {
            Logger::debug('SyncController::getFilteredAccounts called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredAccounts($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredAccounts', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredCompanies($params)
    {
        try {
            Logger::debug('SyncController::getFilteredCompanies called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredCompanies($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredCompanies', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredIncidents($params)
    {
        try {
            Logger::debug('SyncController::getFilteredIncidents called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredIncidents($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredIncidents', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredIntegrations($params)
    {
        try {
            Logger::debug('SyncController::getFilteredIntegrations called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredIntegrations($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredIntegrations', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredLicenses($params)
    {
        try {
            Logger::debug('SyncController::getFilteredLicenses called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredLicenses($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredLicenses', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredMachines($params)
    {
        try {
            Logger::debug('SyncController::getFilteredMachines called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredMachines($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredMachines', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredNetworks($params)
    {
        try {
            Logger::debug('SyncController::getFilteredNetworks called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredNetworks($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredNetworks', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredPackages($params)
    {
        try {
            Logger::debug('SyncController::getFilteredPackages called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredPackages($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredPackages', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredPolicies($params)
    {
        try {
            Logger::debug('SyncController::getFilteredPolicies called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredPolicies($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredPolicies', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }


    public function getFilteredQuarantineItems($params)
    {
        try {
            Logger::debug('SyncController::getFilteredQuarantineItems called', [
                'params' => $params
            ]);

            $result = $this->syncService->getFilteredQuarantineItems($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredQuarantineItems', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getFilteredReports($params)
    {
        try {
            Logger::debug('SyncController::getFilteredReports called', [
                'params' => $params
            ]);

            // Garantir que api_key_id está presente
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $result = $this->syncService->getFilteredReports($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getFilteredReports', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getMachineInventory($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $result = $this->syncService->getMachineInventory($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Failed to get machine inventory', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }
}
