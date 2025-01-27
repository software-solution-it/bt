<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\PackagesService;
use App\Core\Logger;

class PackagesController extends Controller
{
    private $packagesService;
    
    public function __construct()
    {
        $this->packagesService = new PackagesService();
    }
    
    public function getInstallationLinks($params)
    {
        try {
            Logger::debug('PackagesController::getInstallationLinks called', [
                'params' => $params
            ]);

            $result = $this->packagesService->getInstallationLinks($params);
            
            // Ensure proper JSON-RPC 2.0 response format
            return json_encode([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getInstallationLinks', [
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
    
    public function createPackage($params)
    {
        try {
            Logger::debug('PackagesController::createPackage called', [
                'params' => $params
            ]);

            $result = $this->packagesService->createPackage($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createPackage', [
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
    
    public function getPackagesList($params)
    {
        try {
            Logger::debug('PackagesController::getPackagesList called', [
                'params' => $params
            ]);

            $result = $this->packagesService->getPackagesList($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getPackagesList', [
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
    
    public function deletePackage($params)
    {
        try {
            Logger::debug('PackagesController::deletePackage called', [
                'params' => $params
            ]);

            $result = $this->packagesService->deletePackage($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in deletePackage', [
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
    
    public function getPackageDetails($params)
    {
        try {
            Logger::debug('PackagesController::getPackageDetails called', [
                'params' => $params
            ]);

            $result = $this->packagesService->getPackageDetails($params);
            
            // Ensure proper JSON-RPC 2.0 response format
            return json_encode([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getPackageDetails', [
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
}
