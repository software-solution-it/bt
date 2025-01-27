<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\IncidentsModel;
use App\Model\ApiKeysModel;

class IncidentsService extends Service
{
    private $client;
    private $baseUrl;
    private $incidentsModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->incidentsModel = new IncidentsModel();
        
        Logger::debug('Initializing IncidentsService', [
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

    public function addToBlocklist($params)
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

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'addToBlocklist',
                'params' => [
                    'hashType' => $params['hashType'],
                    'hashList' => $params['hashList'],
                    'sourceInfo' => $params['sourceInfo']
                ],
                'id' => uniqid()
            ];

            Logger::debug('Making addToBlocklist request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/incidents', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('AddToBlocklist response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Sync with database if API call was successful
            if ($statusCode === 200) {
                $this->incidentsModel->addToBlocklist(
                    $params['hashType'],
                    $params['hashList'],
                    $params['sourceInfo']
                );
            }

            return $result['result'] ?? true;

        } catch (\Exception $e) {
            Logger::error('AddToBlocklist API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getBlocklistItems($params)
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

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getBlocklistItems',
                'params' => [
                    'page' => (int)($params['page'] ?? 1),
                    'perPage' => (int)($params['perPage'] ?? 30)
                ],
                'id' => uniqid()
            ];

            Logger::debug('Making getBlocklistItems request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/incidents', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GetBlocklistItems response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Sync with database if API call was successful
            if ($statusCode === 200 && isset($result['result'])) {
                $this->incidentsModel->syncBlocklistWithAPI($result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;
 
        } catch (\Exception $e) {
            Logger::error('GetBlocklistItems API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function removeFromBlocklist($params)
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

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'removeFromBlocklist',
                'params' => [
                    'hashItemId' => $params['hashItemId']
                ],
                'id' => uniqid()
            ];

            $response = $this->client->post('/api/v1.0/jsonrpc/incidents', [
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

            // Sync with database if API call was successful
            if ($statusCode === 200) {
                $this->incidentsModel->removeFromBlocklist($params['hashItemId']);
            }

            return $result['result'] ?? true;

        } catch (\Exception $e) {
            Logger::error('RemoveFromBlocklist API request failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createIsolateEndpointTask($params)
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

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'createIsolateEndpointTask',
                'params' => [
                    'endpointId' => $params['endpointId']
                ],
                'id' => uniqid()
            ];

            Logger::debug('Making createIsolateEndpointTask request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/incidents', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('CreateIsolateEndpointTask response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            return $result['result'] ?? true;

        } catch (\Exception $e) {
            Logger::error('CreateIsolateEndpointTask API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function createRestoreEndpointFromIsolationTask($params)
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

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'createRestoreEndpointFromIsolationTask',
                'params' => [
                    'endpointId' => $params['endpointId']
                ],
                'id' => uniqid()
            ];

            Logger::debug('Making createRestoreEndpointFromIsolationTask request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/incidents', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('CreateRestoreEndpointFromIsolationTask response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            return $result['result'] ?? true;

        } catch (\Exception $e) {
            Logger::error('CreateRestoreEndpointFromIsolationTask API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredIncidents($filters)
    {
        try {
            Logger::debug('IncidentsService::getFilteredIncidents called', [
                'filters' => $filters
            ]);

            return $this->incidentsModel->getFilteredIncidents($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered incidents', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getIncidentsFromDb($apiKeyId)
    {
        try {
            Logger::debug('IncidentsService::getIncidentsFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $incidents = $this->incidentsModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $incidents,
                'total' => count($incidents),
                'page' => 1,
                'perPage' => count($incidents)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get incidents from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
