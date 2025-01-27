<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\NetworkService;
use App\Core\Logger;

class NetworkController extends Controller
{
    private $networkService;
    
    public function __construct()
    {
        $this->networkService = new NetworkService();
    }
    
    public function getEndpointsList($params)
    {
        try {
            Logger::debug('NetworkController::getEndpointsList called', [
                'params' => $params
            ]);

            if (isset($params['api_key_id']) && !is_numeric($params['api_key_id'])) {
                throw new \Exception('Invalid api_key_id format');
            }

            $result = $this->networkService->getEndpointsList($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getEndpointsList', [
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
    
   

    public function createCustomGroup($params)
    {
        try {
            Logger::debug('NetworkController::createCustomGroup called', [
                'params' => $params
            ]);

            if (empty($params['groupName'])) {
                throw new \Exception('Group name is required');
            }

            $result = $this->networkService->createCustomGroup($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createCustomGroup', [
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
    
    public function deleteCustomGroup($params)
    {
        try {
            Logger::debug('NetworkController::deleteCustomGroup called', [
                'params' => $params
            ]);

            if (empty($params['groupId'])) {
                throw new \Exception('Group ID is required');
            }

            $result = $this->networkService->deleteCustomGroup($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in deleteCustomGroup', [
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
    
    public function getCustomGroupsList($params)
    {
        try {
            Logger::debug('NetworkController::getCustomGroupsList called', [
                'params' => $params
            ]);

            $result = $this->networkService->getCustomGroupsList($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getCustomGroupsList', [
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
    
    public function moveEndpoints($params)
    {
        try {
            Logger::debug('NetworkController::moveEndpoints called', [
                'params' => $params
            ]);

            if (empty($params['endpointIds']) || empty($params['groupId'])) {
                throw new \Exception('Endpoint IDs and group ID are required');
            }

            $result = $this->networkService->moveEndpoints($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in moveEndpoints', [
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
    
    public function deleteEndpoint($params)
    {
        try {
            Logger::debug('NetworkController::deleteEndpoint called', [
                'params' => $params
            ]);

            if (empty($params['endpointId'])) {
                throw new \Exception('Endpoint ID is required');
            }

            $result = $this->networkService->deleteEndpoint($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in deleteEndpoint', [
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
    
    public function moveCustomGroup($params)
    {
        try {
            Logger::debug('NetworkController::moveCustomGroup called', [
                'params' => $params
            ]);

            if (empty($params['groupId']) || empty($params['parentId'])) {
                throw new \Exception('Group ID and parent ID are required');
            }

            $result = $this->networkService->moveCustomGroup($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in moveCustomGroup', [
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
    
    public function getNetworkInventoryItems($params)
    {
        try {
            Logger::debug('NetworkController::getNetworkInventoryItems called', [
                'params' => $params
            ]);

            $result = $this->networkService->getNetworkInventoryItems($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getNetworkInventoryItems', [
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
    
    public function createScanTask($params)
    {
        try {
            Logger::info('NetworkController::createScanTask called', [
                'params' => $params
            ]);

            if (empty($params['targetIds']) || empty($params['type'])) {
                throw new \Exception('Target IDs and type are required');
            }

            if (!in_array($params['type'], [1, 2, 3])) {
                throw new \Exception('Invalid scan type. Must be 1 (quick), 2 (full), or 3 (memory)');
            }

            $result = $this->networkService->createScanTask($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::info('Error in createScanTask', [
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
    
    public function getScanTasksList($params)
    {
        try {
            Logger::debug('NetworkController::getScanTasksList called', [
                'params' => $params
            ]);

            $result = $this->networkService->getScanTasksList($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getScanTasksList', [
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
    
    public function setEndpointLabel($params)
    {
        try {
            Logger::debug('NetworkController::setEndpointLabel called', [
                'params' => $params
            ]);

            if (empty($params['endpointId']) || !isset($params['label'])) {
                throw new \Exception('Endpoint ID and label are required');
            }

            if (strlen($params['label']) > 64) {
                throw new \Exception('Label must not exceed 64 characters');
            }

            $result = $this->networkService->setEndpointLabel($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in setEndpointLabel', [
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
