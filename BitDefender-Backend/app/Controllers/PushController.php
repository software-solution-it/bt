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
            Logger::debug('PushController::setPushEventSettings called', [
                'params' => $params
            ]);

            $result = $this->pushService->setPushEventSettings($params);
            
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
                    'message' => 'Internal error',
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
            Logger::debug('PushController::getPushEventSettings called', [
                'params' => $params
            ]);

            $result = $this->pushService->getPushEventSettings();
            
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
                    'message' => 'Internal error',
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
            Logger::debug('PushController::sendTestPushEvent called', [
                'params' => $params
            ]);

            $result = $this->pushService->sendTestPushEvent($params);
            
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
                    'message' => 'Internal error',
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
