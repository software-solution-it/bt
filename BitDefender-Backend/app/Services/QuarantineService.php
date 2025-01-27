<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\QuarantineModel;
use App\Model\ApiKeysModel;

class QuarantineService extends Service
{
    private $client;
    private $baseUrl;
    private $quarantineModel;
    
    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->quarantineModel = new QuarantineModel();
        
        Logger::debug('Initializing QuarantineService', [
            'baseUrl' => $this->baseUrl
        ]);
    }
 
    private function initializeClient($apiToken)
    {
        $authString = base64_encode($apiToken . ':');
        
        Logger::debug('Auth string generated', [
            'authString' => 'Basic ' . $authString
        ]);

        $this->client = new Client([
            'base_uri' => $this->baseUrl,
            'headers' => [
                'Authorization' => 'Basic ' . $authString,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ],
            RequestOptions::VERIFY => false,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::TIMEOUT => 30,
            RequestOptions::CONNECT_TIMEOUT => 30
        ]);
    }
    
    private function makeRequest($service, $method, $params = [], $requestId = null)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($params['api_key_id']);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            $this->initializeClient($apiKey['api_key']);

            // Remove api_key_id dos parâmetros antes de enviar para a API
            $apiParams = $params;
            unset($apiParams['api_key_id']);

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $apiParams,
                'id' => $requestId ?? uniqid()
            ];

            Logger::debug("Making {$method} request", [
                'service' => $service,
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post("/api/v1.0/jsonrpc/quarantine/{$service}", [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error("{$method} request failed", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function getQuarantineItemsList($service, $params)
    {
        if (!in_array($service, ['computers', 'exchange'])) {
            throw new \Exception('Invalid service type. Must be computers or exchange');
        }

        if (!isset($params['api_key_id'])) {
            throw new \Exception('API Key ID is required');
        }

        $result = $this->makeRequest($service, 'getQuarantineItemsList', [
            'api_key_id' => $params['api_key_id'],
            'endpointId' => $params['endpointId'] ?? null,
            'page' => $params['page'] ?? 1,
            'perPage' => min($params['perPage'] ?? 30, 100)
        ]);

        if ($result && isset($result['items'])) {
            // Adiciona api_key_id aos itens antes de sincronizar
            foreach ($result['items'] as &$item) {
                $item['api_key_id'] = $params['api_key_id'];
            }
            $this->quarantineModel->syncQuarantineItems($service, $result['items']);
        }

        return $result;
    }
    
    public function createRemoveQuarantineItemTask($service, $params)
    {
        if (!in_array($service, ['computers', 'exchange'])) {
            throw new \Exception('Invalid service type. Must be computers or exchange');
        }

        if (empty($params['quarantineItemsIds']) || !is_array($params['quarantineItemsIds'])) {
            throw new \Exception('quarantineItemsIds must be a non-empty array');
        }

        if (count($params['quarantineItemsIds']) > 100) {
            throw new \Exception('Maximum of 100 items can be removed at once');
        }

        $result = $this->makeRequest($service, 'createRemoveQuarantineItemTask', [
            'quarantineItemsIds' => $params['quarantineItemsIds']
        ]);

        if ($result) {
            // Update local status and log task
            $this->quarantineModel->updateItemsStatus($params['quarantineItemsIds'], 'pending_removal');
            $this->quarantineModel->logTaskHistory(
                $result['taskId'],
                $service,
                'remove',
                $params['quarantineItemsIds'],
                'initiated'
            );
        }

        return $result;
    }
    
    public function createEmptyQuarantineTask($service)
    {
        if (!in_array($service, ['computers', 'exchange'])) {
            throw new \Exception('Invalid service type. Must be computers or exchange');
        }

        $result = $this->makeRequest($service, 'createEmptyQuarantineTask');

        if ($result) {
            $this->quarantineModel->logTaskHistory(
                $result['taskId'],
                $service,
                'empty',
                null,
                'initiated'
            );
        }

        return $result;
    }
    
    public function createRestoreQuarantineItemTask($service, $params)
    {
        if ($service !== 'computers') {
            throw new \Exception('This method is only available for computers service');
        }

        if (empty($params['quarantineItemsIds']) || !is_array($params['quarantineItemsIds'])) {
            throw new \Exception('quarantineItemsIds must be a non-empty array');
        }

        if (count($params['quarantineItemsIds']) > 100) {
            throw new \Exception('Maximum of 100 items can be restored at once');
        }

        $result = $this->makeRequest($service, 'createRestoreQuarantineItemTask', [
            'quarantineItemsIds' => $params['quarantineItemsIds'],
            'locationToRestore' => $params['locationToRestore'] ?? null,
            'addExclusionInPolicy' => $params['addExclusionInPolicy'] ?? false
        ]);

        if ($result) {
            $this->quarantineModel->updateItemsStatus($params['quarantineItemsIds'], 'pending_restore');
            $this->quarantineModel->logTaskHistory(
                $result['taskId'],
                $service,
                'restore',
                $params['quarantineItemsIds'],
                'initiated'
            );
        }

        return $result;
    }
    
    public function createRestoreQuarantineExchangeItemTask($service, $params)
    {
        if ($service !== 'exchange') {
            throw new \Exception('This method is only available for exchange service');
        }

        if (empty($params['quarantineItemsIds']) || !is_array($params['quarantineItemsIds'])) {
            throw new \Exception('quarantineItemsIds must be a non-empty array');
        }

        if (count($params['quarantineItemsIds']) > 100) {
            throw new \Exception('Maximum of 100 items can be restored at once');
        }

        if (empty($params['username']) || empty($params['password'])) {
            throw new \Exception('Username and password are required');
        }

        $result = $this->makeRequest($service, 'createRestoreQuarantineExchangeItemTask', [
            'quarantineItemsIds' => $params['quarantineItemsIds'],
            'username' => $params['username'],
            'password' => $params['password'],
            'email' => $params['email'] ?? null,
            'ewsUrl' => $params['ewsUrl'] ?? null
        ]);

        if ($result) {
            $this->quarantineModel->updateItemsStatus($params['quarantineItemsIds'], 'pending_restore');
            $this->quarantineModel->logTaskHistory(
                $result['taskId'],
                $service,
                'restore',
                $params['quarantineItemsIds'],
                'initiated'
            );
        }

        return $result;
    }
    
    public function getFilteredQuarantineItems($filters)
    {
        try {
            if (!isset($filters['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            Logger::debug('QuarantineService::getFilteredQuarantineItems called', [
                'filters' => $filters
            ]);

            return $this->quarantineModel->getFilteredQuarantineItems($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered quarantine items', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getQuarantineFromDb($apiKeyId)
    {
        try {
            Logger::debug('QuarantineService::getQuarantineFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $quarantineItems = $this->quarantineModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $quarantineItems,
                'total' => count($quarantineItems),
                'page' => 1,
                'perPage' => count($quarantineItems)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get quarantine items from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
