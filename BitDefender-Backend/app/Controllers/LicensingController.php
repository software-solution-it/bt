<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\LicensingService;
use App\Core\Logger;

class LicensingController extends Controller
{
    private $licensingService;
    
    public function __construct()
    {
        $this->licensingService = new LicensingService();
    }
    
    public function getLicenseInfo($params)
    {
        try {
            Logger::debug('LicensingController::getLicenseInfo called', [
                'params' => $params
            ]);

            $result = $this->licensingService->getLicenseInfo($params['id'] ?? null);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getLicenseInfo', [
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
    
    public function setLicenseKey($params)
    {
        try {
            Logger::debug('LicensingController::setLicenseKey called', [
                'params' => $params
            ]);

            if (empty($params['licenseKey'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'License key is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->licensingService->setLicenseKey($params['licenseKey'], $params['id'] ?? null);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in setLicenseKey', [
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
    
    public function getMonthlyUsage($params)
    {
        try {
            Logger::debug('LicensingController::getMonthlyUsage called', [
                'params' => $params
            ]);

            if (isset($params['targetMonth']) && !preg_match('/^(0[1-9]|1[0-2])\/\d{4}$/', $params['targetMonth'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Invalid date format. Use mm/yyyy'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->licensingService->getMonthlyUsage(
                $params['targetMonth'] ?? null,
                $params['id'] ?? null
            );
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getMonthlyUsage', [
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
