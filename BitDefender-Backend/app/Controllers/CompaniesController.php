<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\CompaniesService;
use App\Core\Logger;

class CompaniesController extends Controller
{
    private $companiesService;
    
    public function __construct()
    {
        $this->companiesService = new CompaniesService();
    }
    
    public function getCompanyDetails($params)
    {
        try {
            Logger::debug('CompaniesController::getCompanyDetails called', [
                'params' => $params
            ]);

            $result = $this->companiesService->getCompanyDetails();
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getCompanyDetails', [
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
    
    public function updateCompanyDetails($params)
    {
        try {
            Logger::debug('CompaniesController::updateCompanyDetails called', [
                'params' => $params
            ]);

            // Validações
            $validFields = ['name', 'address', 'phone'];
            $updateData = array_intersect_key($params, array_flip($validFields));

            if (empty($updateData)) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'At least one of these fields is required: name, address, phone'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->companiesService->updateCompanyDetails($updateData);

            // Busca os dados atualizados
            $updatedCompany = $this->companiesService->getCompanyDetails();
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $updatedCompany,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in updateCompanyDetails', [
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
