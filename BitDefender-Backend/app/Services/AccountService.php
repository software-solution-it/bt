<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\AccountModel;
use App\Model\ApiKeysModel;

class AccountService extends Service
{
    private $client;
    private $baseUrl;
    private $accountModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->accountModel = new AccountModel();
        
        Logger::debug('Initializing AccountService', [
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

    public function getAccounts($params)
    {
        try {
            // Verifica se foi fornecido um api_key_id
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Busca o token da API no banco de dados
            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($params['api_key_id']);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            // Inicializa o cliente com o token correto
            $this->initializeClient($apiKey['api_key']);

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getAccountsList',
                'params' => [
                    'page' => (int)($params['page'] ?? 1),
                    'perPage' => min((int)($params['perPage'] ?? 30), 100)
                ],
                'id' => uniqid()
            ];

            $endpoint = '/api/v1.0/jsonrpc/accounts';
            
            Logger::debug('Making API request', [
                'endpoint' => $endpoint,
                'request' => $requestBody,
                'headers' => $this->client->getConfig('headers')
            ]);

            $response = $this->client->post($endpoint, [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('API response received', [
                'statusCode' => $statusCode,
                'response' => $result,
                'rawResponse' => $responseBody,
                'headers' => $response->getHeaders()
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if ($statusCode === 200) {
                // Sync with database
                $this->accountModel->syncWithAPI($result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function createAccount(array $data)
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
                'method' => 'createAccount',
                'params' => [
                    'email' => $data['email'],
                    'profile' => $data['profile'],
                    'password' => $data['password'] ?? null,
                    'role' => $data['role'] ?? 1,
                    'rights' => $data['rights'] ?? null,
                    'targetIds' => $data['targetIds'] ?? []
                ],
                'id' => uniqid()
            ];

            Logger::debug('Making createAccount request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/accounts', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('CreateAccount response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200 && $statusCode !== 201) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if ($statusCode === 200 || $statusCode === 201) {
                // Sync with database
                $this->accountModel->syncWithAPI($result['result'], $data['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('CreateAccount API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function updateAccount($accountId, array $data)
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

            // Verifica se o accountId é um ID válido do MongoDB (24 caracteres hexadecimais)
            if (!preg_match('/^[0-9a-f]{24}$/', $accountId)) {
                throw new \Exception('Invalid accountId format. Must be a 24-character hexadecimal string.');
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'updateAccount',
                'params' => [
                    'accountId' => $accountId, // Não precisa converter para string, já é string
                    'profile' => [
                        'fullName' => $data['name'] ?? null,
                        'language' => $data['language'] ?? null,
                        'timezone' => $data['timezone'] ?? null
                    ],
                    'email' => $data['email'] ?? null,
                    'role' => $data['role'] ?? null,
                    'rights' => $data['rights'] ?? null
                ],
                'id' => uniqid()
            ];

            // Remove campos nulos
            $requestBody['params'] = array_filter($requestBody['params'], function($value) {
                return !is_null($value) && (!is_array($value) || !empty(array_filter($value)));
            });

            Logger::debug('Making updateAccount request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/accounts', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('UpdateAccount response received', [
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
                $this->accountModel->syncWithAPI($result['result'], $data['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('UpdateAccount API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function deleteAccount($accountId)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'deleteAccount',
                'params' => [
                    'accountId' => $accountId
                ],
                'id' => uniqid()
            ];

            $response = $this->client->post('/api/v1.0/jsonrpc/accounts', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('DeleteAccount response received', [
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
                // Remove from database
                $this->accountModel->deleteByApiId($accountId);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('DeleteAccount API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredAccounts($filters)
    {
        try {
            Logger::debug('AccountService::getFilteredAccounts called', [
                'filters' => $filters
            ]);

            return $this->accountModel->getFilteredAccounts($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered accounts', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getAccountsFromDb($apiKeyId)
    {
        try {
            Logger::debug('AccountService::getAccountsFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $accounts = $this->accountModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $accounts,
                'total' => count($accounts),
                'page' => 1,
                'perPage' => count($accounts)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get accounts from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}