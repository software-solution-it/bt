<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\ApiKeysService;
use App\Core\Logger;

class ApiKeysController extends Controller
{
    private $apiKeysService;

    public function __construct()
    {
        $this->apiKeysService = new ApiKeysService();
    }

    public function listKeys($params)
    {
        try {
            Logger::debug('ApiKeysController::listKeys called');
            $result = $this->apiKeysService->getAllKeys();

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);
        } catch (\Exception $e) {
            Logger::error('Error in listKeys', [
                'error' => $e->getMessage()
            ]);
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function createKey($params)
    {
        try {
            Logger::debug('ApiKeysController::createKey called', [
                'params' => $params
            ]);

            $result = $this->apiKeysService->createKey($params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 400);
        } catch (\Exception $e) {
            Logger::error('Error in createKey', [
                'error' => $e->getMessage()
            ]);
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function updateKey($params)
    {
        try {
            Logger::debug('ApiKeysController::updateKey called', [
                'params' => $params
            ]);

            if (empty($params['id'])) {
                throw new \InvalidArgumentException('ID is required');
            }

            $result = $this->apiKeysService->updateKey($params['id'], $params);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 400);
        } catch (\Exception $e) {
            Logger::error('Error in updateKey', [
                'error' => $e->getMessage()
            ]);
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function deleteKey($params)
    {
        try {
            Logger::debug('ApiKeysController::deleteKey called', [
                'params' => $params
            ]);

            if (empty($params['id'])) {
                throw new \InvalidArgumentException('ID is required');
            }

            $result = $this->apiKeysService->deleteKey($params['id']);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32602,
                    'message' => 'Invalid params',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 400);
        } catch (\Exception $e) {
            Logger::error('Error in deleteKey', [
                'error' => $e->getMessage()
            ]);
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => ['details' => $e->getMessage()]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }
} 