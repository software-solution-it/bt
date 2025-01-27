<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\PushService;
use App\Core\Logger;
use App\Core\Environment;

class PushController extends Controller
{
    private $pushService;
    
    public function __construct()
    {
        $this->pushService = new PushService();
    }
    
    public function setPushEventSettings($params)
    {
        try {
            Logger::info('PushController::setPushEventSettings raw input', [
                'raw_params' => $params,
                'has_jsonrpc' => isset($params['jsonrpc']),
                'has_method' => isset($params['method']),
                'has_params' => isset($params['params'])
            ]);

            // Se os parâmetros estiverem no formato JSON-RPC
            $requestParams = [];
            if (isset($params['params'])) {
                $requestParams = $params['params'];
                Logger::info('Extracted params from JSON-RPC', [
                    'extracted' => $requestParams
                ]);
            } elseif (isset($params['api_key_id'])) {
                $requestParams = $params;
            }

            Logger::info('Final processed params', [
                'requestParams' => $requestParams
            ]);

            // Validações
            if (!isset($requestParams['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            if (!isset($requestParams['status'])) {
                throw new \Exception('Status is required');
            }

            if (!isset($requestParams['serviceType'])) {
                throw new \Exception('Service type is required');
            }

            // Validar serviceType
            $allowedServiceTypes = ['jsonRPC', 'splunk', 'cef'];
            if (!in_array($requestParams['serviceType'], $allowedServiceTypes)) {
                throw new \Exception('Invalid service type. Allowed types are: ' . implode(', ', $allowedServiceTypes));
            }

            if (!isset($requestParams['serviceSettings']) || !isset($requestParams['serviceSettings']['url'])) {
                throw new \Exception('Service URL is required');
            }

            if (!isset($requestParams['subscribeToEventTypes']) || !is_array($requestParams['subscribeToEventTypes'])) {
                throw new \Exception('Subscribe to event types is required and must be an array');
            }

            // Converte api_key_id para número
            $apiKeyId = intval($requestParams['api_key_id']);
            $requestParams['api_key_id'] = $apiKeyId;

            $result = $this->pushService->setPushEventSettings($requestParams);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in setPushEventSettings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage(),
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }
    
    public function getPushEventSettings($params = [])
    {
        try {
            Logger::info('PushController::getPushEventSettings received', [
                'raw_params' => $params,
                'has_params' => isset($params['params']),
                'params_type' => gettype($params),
                'full_request' => $params
            ]);

            // Se os parâmetros estiverem no formato JSON-RPC
            $requestParams = [];
            if (isset($params['params'])) {
                $requestParams = $params['params'];
            } elseif (isset($params['api_key_id'])) {
                $requestParams = $params;
            }

            Logger::info('Processed params', [
                'requestParams' => $requestParams,
                'has_api_key' => isset($requestParams['api_key_id'])
            ]);

            if (!isset($requestParams['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Converte api_key_id para número
            $apiKeyId = intval($requestParams['api_key_id']);
            
            $result = $this->pushService->getPushEventSettings([
                'api_key_id' => $apiKeyId
            ]);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getPushEventSettings', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage(),
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }
    
    public function sendTestPushEvent($params)
    {
        try {
            Logger::info('PushController::sendTestPushEvent raw input', [
                'raw_params' => $params,
                'has_jsonrpc' => isset($params['jsonrpc']),
                'has_method' => isset($params['method']),
                'has_params' => isset($params['params'])
            ]);

            // Se os parâmetros estiverem no formato JSON-RPC
            $requestParams = [];
            if (isset($params['params'])) {
                $requestParams = $params['params'];
                Logger::info('Extracted params from JSON-RPC', [
                    'extracted' => $requestParams
                ]);
            } elseif (isset($params['api_key_id'])) {
                $requestParams = $params;
            }

            Logger::info('Final processed params', [
                'requestParams' => $requestParams
            ]);

            if (!isset($requestParams['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            if (!isset($requestParams['eventType'])) {
                throw new \Exception('Event type is required');
            }

            if (!isset($requestParams['data'])) {
                throw new \Exception('Event data is required');
            }

            // Converte api_key_id para número
            $apiKeyId = intval($requestParams['api_key_id']);
            
            $result = $this->pushService->sendTestPushEvent([
                'api_key_id' => $apiKeyId,
                'eventType' => $requestParams['eventType'],
                'data' => $requestParams['data']
            ]);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in sendTestPushEvent', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage(),
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }
    
    public function getPushEventStats($params = [])
    {
        try {
            Logger::debug('PushController::getPushEventStats called', [
                'params' => $params
            ]);

            $result = $this->pushService->getPushEventStats();
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in getPushEventStats', [
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
    
    public function resetPushEventStats($params = [])
    {
        try {
            Logger::debug('PushController::resetPushEventStats called', [
                'params' => $params
            ]);

            $result = $this->pushService->resetPushEventStats();
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Error in resetPushEventStats', [
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
    
    public function receiveEvents()
    {
        try {
            // Obter o payload do webhook
            $rawInput = file_get_contents('php://input');
            $payload = json_decode($rawInput, true);
            
            Logger::debug('Request received', [
                'payload' => $payload
            ]);

            // Se for uma chamada de método específica
            if (isset($payload['method'])) {
                switch ($payload['method']) {
                    case 'setPushEventSettings':
                        return $this->setPushEventSettings($payload['params']);
                    case 'getPushEventSettings':
                        return $this->getPushEventSettings($payload['params'] ?? []);
                    case 'sendTestPushEvent':
                        return $this->sendTestPushEvent($payload['params']);
                    case 'getPushEventStats':
                        return $this->getPushEventStats($payload['params'] ?? []);
                    case 'resetPushEventStats':
                        return $this->resetPushEventStats($payload['params'] ?? []);
                    case 'addEvents':
                        // Processa eventos do webhook
                        foreach ($payload['params']['events'] as $event) {
                            $this->pushService->processEvent($event);
                        }
                        return $this->jsonResponse([
                            'jsonrpc' => '2.0',
                            'result' => true,
                            'id' => $payload['id'] ?? null
                        ]);
                }
            }

            throw new \Exception('Invalid method or payload structure');

        } catch (\Exception $e) {
            Logger::error('Request processing failed', [
                'error' => $e->getMessage()
            ]);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $payload['id'] ?? null
            ], 500);
        }
    }
    
}
