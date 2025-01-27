<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\CompaniesModel;
use App\Model\ApiKeysModel;

class CompaniesService extends Service
{
    private $client;
    private $baseUrl;
    private $companiesModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->companiesModel = new CompaniesModel();
        
        Logger::debug('Initializing CompaniesService', [
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
    
    public function getCompanyDetails($params)
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
                'method' => 'getCompanyDetails',
                'params' => new \stdClass(),
                'id' => uniqid()
            ];

            Logger::debug('Making getCompanyDetails request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/companies', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GetCompanyDetails response received', [
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
                $this->companiesModel->syncWithAPI($result['result'], $params['api_key_id']);
            }
 
            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetCompanyDetails API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    public function updateCompanyDetails(array $data)
    {
        try {
            if (!isset($data['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($data['api_key_id']);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            $this->initializeClient($apiKey['api_key']);

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'updateCompanyDetails',
                'params' => $data,
                'id' => uniqid()
            ];

            Logger::debug('Making updateCompanyDetails request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/companies', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('UpdateCompanyDetails response received', [
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
                $this->companiesModel->updateCompanyDetails($data); 
                
                // Get updated data from API to ensure sync
                $updatedData = $this->getCompanyDetails(['api_key_id' => $data['api_key_id']]);
                if ($updatedData) {
                    $this->companiesModel->syncWithAPI($updatedData, $data['api_key_id']);
                }
            }

            return $result['result'] ?? true;

        } catch (\Exception $e) {
            Logger::error('UpdateCompanyDetails API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredCompanies($filters)
    {
        try {
            Logger::debug('CompaniesService::getFilteredCompanies called', [
                'filters' => $filters
            ]);

            return $this->companiesModel->getFilteredCompanies($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered companies', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getCompaniesFromDb($apiKeyId)
    {
        try {
            Logger::debug('CompaniesService::getCompaniesFromDb called', [
                'api_key_id' => $apiKeyId
            ]); 

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $companies = $this->companiesModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $companies,
                'total' => count($companies),
                'page' => 1,
                'perPage' => count($companies)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get companies from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
