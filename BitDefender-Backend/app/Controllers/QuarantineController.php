<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\QuarantineService;
use App\Core\Logger;

class QuarantineController extends Controller
{
    private $quarantineService;
    
    public function __construct()
    {
        $this->quarantineService = new QuarantineService();
    }
    
    public function getQuarantineItemsList($service, $params)
    {
        try {
            Logger::debug('QuarantineController::getQuarantineItemsList called', [
                'service' => $service,
                'params' => $params
            ]);

            $result = $this->quarantineService->getQuarantineItemsList($service, $params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getQuarantineItemsList', [
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
    
    public function createRemoveQuarantineItemTask($service, $params)
    {
        try {
            Logger::debug('QuarantineController::createRemoveQuarantineItemTask called', [
                'service' => $service,
                'params' => $params
            ]);

            $result = $this->quarantineService->createRemoveQuarantineItemTask($service, $params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createRemoveQuarantineItemTask', [
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
    
    public function createEmptyQuarantineTask($service)
    {
        try {
            Logger::debug('QuarantineController::createEmptyQuarantineTask called', [
                'service' => $service
            ]);

            $result = $this->quarantineService->createEmptyQuarantineTask($service);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createEmptyQuarantineTask', [
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
                'id' => null
            ], 500);
        }
    }
    
    public function createRestoreQuarantineItemTask($service, $params)
    {
        try {
            Logger::debug('QuarantineController::createRestoreQuarantineItemTask called', [
                'service' => $service,
                'params' => $params
            ]);

            $result = $this->quarantineService->createRestoreQuarantineItemTask($service, $params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createRestoreQuarantineItemTask', [
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
    
    public function createRestoreQuarantineExchangeItemTask($service, $params)
    {
        try {
            Logger::debug('QuarantineController::createRestoreQuarantineExchangeItemTask called', [
                'service' => $service,
                'params' => $params
            ]);

            $result = $this->quarantineService->createRestoreQuarantineExchangeItemTask($service, $params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createRestoreQuarantineExchangeItemTask', [
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
