<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\IntegrationsModel;
use App\Model\ApiKeysModel;

class IntegrationsService extends Service
{
    private $client;
    private $baseUrl;
    private $integrationsModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->integrationsModel = new IntegrationsModel();
         
        Logger::debug('Initializing IntegrationsService', [
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

    private function generateRequestId($clientId = null) {
        return $clientId ?? uniqid('bd_', true);
    }

    public function getHourlyUsageForAmazonEC2Instances($params)
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
                'method' => 'getHourlyUsageForAmazonEC2Instances',
                'params' => [
                    'targetMonth' => $params['targetMonth'] ?? null
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making getHourlyUsageForAmazonEC2Instances request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/integrations', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GetHourlyUsageForAmazonEC2Instances response received', [ 
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if ($statusCode === 200 && isset($result['result'])) {
                // Sync with database
                $targetMonth = $params['targetMonth'] ?? date('Y-m');
                $this->integrationsModel->syncHourlyUsage($targetMonth, $result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetHourlyUsageForAmazonEC2Instances API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString() 
            ]);
            throw $e;
        }
    }

    public function configureAmazonEC2IntegrationUsingCrossAccountRole($params)
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

            if (empty($params['crossAccountRoleArn'])) {
                throw new \Exception('Cross Account Role ARN is required');
            }

            if (!preg_match('/^arn:aws:iam::\d{12}:role\/[\w+=,.@-]+$/', $params['crossAccountRoleArn'])) {
                throw new \Exception('Invalid Cross Account Role ARN format');
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'configureAmazonEC2IntegrationUsingCrossAccountRole',
                'params' => [
                    'crossAccountRoleArn' => $params['crossAccountRoleArn']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making configureAmazonEC2IntegrationUsingCrossAccountRole request', [
                'requestBody' => $requestBody
            ]); 

            $response = $this->client->post('/api/v1.0/jsonrpc/integrations', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('ConfigureAmazonEC2IntegrationUsingCrossAccountRole response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if ($statusCode === 200) {
                // Sync with database
                $this->integrationsModel->updateIntegrationConfig($params['crossAccountRoleArn'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('ConfigureAmazonEC2IntegrationUsingCrossAccountRole API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function generateAmazonEC2ExternalIdForCrossAccountRole($params = [])
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'generateAmazonEC2ExternalIdForCrossAccountRole',
                'params' => [],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making generateAmazonEC2ExternalIdForCrossAccountRole request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/integrations', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GenerateAmazonEC2ExternalIdForCrossAccountRole response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if ($statusCode === 200 && isset($result['result'])) {
                // Sync with database
                $this->integrationsModel->updateExternalId($result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GenerateAmazonEC2ExternalIdForCrossAccountRole API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getAmazonEC2ExternalIdForCrossAccountRole($params = [])
    {
        try {
            // Primeiro tenta obter do banco de dados local
            $localExternalId = $this->integrationsModel->getExternalId();
            if ($localExternalId) {
                return $localExternalId;
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getAmazonEC2ExternalIdForCrossAccountRole',
                'params' => [],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making getAmazonEC2ExternalIdForCrossAccountRole request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/integrations', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GetAmazonEC2ExternalIdForCrossAccountRole response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            } 

            if ($statusCode === 200 && isset($result['result'])) {
                // Sync with database
                $this->integrationsModel->updateExternalId($result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetAmazonEC2ExternalIdForCrossAccountRole API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function disableAmazonEC2Integration($params = [])
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'disableAmazonEC2Integration',
                'params' => [],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making disableAmazonEC2Integration request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/integrations', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('DisableAmazonEC2Integration response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if ($statusCode === 200) {
                // Sync with database
                $this->integrationsModel->disableIntegration();
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('DisableAmazonEC2Integration API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredIntegrations($filters)
    {
        try {
            Logger::debug('IntegrationsService::getFilteredIntegrations called', [
                'filters' => $filters
            ]);

            return $this->integrationsModel->getFilteredIntegrations($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered integrations', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getIntegrationsFromDb($apiKeyId)
    {
        try {
            Logger::debug('IntegrationsService::getIntegrationsFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $integrations = $this->integrationsModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $integrations,
                'total' => count($integrations),
                'page' => 1,
                'perPage' => count($integrations)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get integrations from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
