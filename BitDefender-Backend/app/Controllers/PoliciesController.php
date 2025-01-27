<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\PoliciesService;
use App\Core\Logger;

class PoliciesController extends Controller
{
    private $policiesService;
    
    public function __construct()
    {
        $this->policiesService = new PoliciesService();
    }
    
    public function getPoliciesList($params)
    {
        try {
            Logger::debug('PoliciesController::getPoliciesList called', [
                'params' => $params
            ]);

            $result = $this->policiesService->getPoliciesList($params);
            
            return json_encode([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getPoliciesList', [
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
    
    public function getPolicyDetails($params)
    {
        try {
            Logger::debug('PoliciesController::getPolicyDetails called', [
                'params' => $params
            ]);

            $result = $this->policiesService->getPolicyDetails($params);
            
            return json_encode([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getPolicyDetails', [
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
