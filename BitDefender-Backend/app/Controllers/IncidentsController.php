<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\IncidentsService;
use App\Core\Logger;

class IncidentsController extends Controller
{
    private $incidentsService;
    
    public function __construct()
    {
        $this->incidentsService = new IncidentsService();
    }
    
    private function validateHash($hash, $hashType) {
        if ($hashType === 1) {
            // SHA256 - 64 caracteres hexadecimais
            return preg_match('/^[a-f0-9]{64}$/i', $hash);
        } else if ($hashType === 2) {
            // MD5 - 32 caracteres hexadecimais
            return preg_match('/^[a-f0-9]{32}$/i', $hash);
        }
        return false;
    }

    public function addToBlocklist($params)
    {
        try {
            Logger::debug('IncidentsController::addToBlocklist called', [
                'params' => $params
            ]);

            // Validações
            if (!isset($params['hashType']) || !in_array($params['hashType'], [1, 2])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Invalid hash type. Must be 1 (SHA256) or 2 (MD5)'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            if (empty($params['hashList']) || !is_array($params['hashList'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Hash list must be a non-empty array'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação do formato dos hashes
            foreach ($params['hashList'] as $hash) {
                if (!$this->validateHash($hash, $params['hashType'])) {
                    $type = $params['hashType'] === 1 ? 'SHA256' : 'MD5';
                    return $this->jsonResponse([
                        'jsonrpc' => '2.0',
                        'error' => [
                            'code' => -32602,
                            'message' => 'Invalid params',
                            'data' => [
                                'details' => "Invalid {$type} hash format: {$hash}"
                            ]
                        ],
                        'id' => $params['id'] ?? null
                    ], 400);
                }
            }

            if (empty($params['sourceInfo'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Source info is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->incidentsService->addToBlocklist($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in addToBlocklist', [
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
    
    public function getBlocklistItems($params)
    {
        try {
            Logger::debug('IncidentsController::getBlocklistItems called', [
                'params' => $params
            ]);

            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 30;

            if ($page < 1) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Page must be greater than 0'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            if ($perPage < 1 || $perPage > 100) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'PerPage must be between 1 and 100'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->incidentsService->getBlocklistItems($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getBlocklistItems', [
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
    
    public function removeFromBlocklist($params)
    {
        try {
            Logger::debug('IncidentsController::removeFromBlocklist called', [
                'params' => $params
            ]);

            if (empty($params['hashItemId'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Hash item ID is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->incidentsService->removeFromBlocklist($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in removeFromBlocklist', [
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
    
    public function createIsolateEndpointTask($params)
    {
        try {
            Logger::debug('IncidentsController::createIsolateEndpointTask called', [
                'params' => $params
            ]);

            if (empty($params['endpointId'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Endpoint ID is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->incidentsService->createIsolateEndpointTask($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createIsolateEndpointTask', [
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
    
    public function createRestoreEndpointFromIsolationTask($params)
    {
        try {
            Logger::debug('IncidentsController::createRestoreEndpointFromIsolationTask called', [
                'params' => $params
            ]);

            if (empty($params['endpointId'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Endpoint ID is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->incidentsService->createRestoreEndpointFromIsolationTask($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in createRestoreEndpointFromIsolationTask', [
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
