<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ReportsService;
use App\Core\Logger;

class ReportsController extends Controller
{
    private $reportsService;
    
    public function __construct()
    {
        $this->reportsService = new ReportsService();
    }
    
    public function createReport($params)
    {
        try {
            Logger::debug('ReportsController::createReport called', [
                'params' => $params
            ]);

            $result = $this->reportsService->createReport($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in createReport', [
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

    public function getReportsList($params)
    {
        try {
            Logger::debug('ReportsController::getReportsList called', [
                'params' => $params
            ]);

            $result = $this->reportsService->getReportsList($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getReportsList', [
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

    public function getDownloadLinks($params)
    {
        try {
            Logger::debug('ReportsController::getDownloadLinks called', [
                'params' => $params
            ]);

            $result = $this->reportsService->getDownloadLinks($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getDownloadLinks', [
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

    public function deleteReport($params)
    {
        try {
            Logger::debug('ReportsController::deleteReport called', [
                'params' => $params
            ]);

            $result = $this->reportsService->deleteReport($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in deleteReport', [
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
