<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\IntegrationsService;
use App\Core\Logger;

class IntegrationsController extends Controller
{
    private $integrationsService;
    
    public function __construct()
    {
        $this->integrationsService = new IntegrationsService();
    }
    
    public function getHourlyUsageForAmazonEC2Instances($params)
    {
        try {
            Logger::debug('IntegrationsController::getHourlyUsageForAmazonEC2Instances called', [
                'params' => $params
            ]);

            $result = $this->integrationsService->getHourlyUsageForAmazonEC2Instances($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getHourlyUsageForAmazonEC2Instances', [
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
    
    public function configureAmazonEC2IntegrationUsingCrossAccountRole($params)
    {
        try {
            Logger::debug('IntegrationsController::configureAmazonEC2IntegrationUsingCrossAccountRole called', [
                'params' => $params
            ]);

            $result = $this->integrationsService->configureAmazonEC2IntegrationUsingCrossAccountRole($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in configureAmazonEC2IntegrationUsingCrossAccountRole', [
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
    
    public function generateAmazonEC2ExternalIdForCrossAccountRole($params = [])
    {
        try {
            Logger::debug('IntegrationsController::generateAmazonEC2ExternalIdForCrossAccountRole called', [
                'params' => $params
            ]);

            $result = $this->integrationsService->generateAmazonEC2ExternalIdForCrossAccountRole($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in generateAmazonEC2ExternalIdForCrossAccountRole', [
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
    
    public function getAmazonEC2ExternalIdForCrossAccountRole($params = [])
    {
        try {
            Logger::debug('IntegrationsController::getAmazonEC2ExternalIdForCrossAccountRole called', [
                'params' => $params
            ]);

            $result = $this->integrationsService->getAmazonEC2ExternalIdForCrossAccountRole($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getAmazonEC2ExternalIdForCrossAccountRole', [
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
    
    public function disableAmazonEC2Integration($params = [])
    {
        try {
            Logger::debug('IntegrationsController::disableAmazonEC2Integration called', [
                'params' => $params
            ]);

            $result = $this->integrationsService->disableAmazonEC2Integration($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in disableAmazonEC2Integration', [
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
