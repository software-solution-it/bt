<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\PoliciesModel;
use App\Model\ApiKeysModel;

class PoliciesService extends Service
{
    private $client;
    private $baseUrl;
    private $policiesModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->policiesModel = new PoliciesModel();
        
        Logger::debug('Initializing PoliciesService', [
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
    
    public function getPoliciesList($params)
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
                'method' => 'getPoliciesList',
                'params' => [
                    'page' => $params['page'] ?? 1,
                    'perPage' => min($params['perPage'] ?? 30, 100)
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            $response = $this->client->post('/api/v1.0/jsonrpc/policies', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            $processedPolicies = [];
            $savedPolicies = 0;

            if (isset($result['result']['items']) && is_array($result['result']['items'])) {
                $totalPolicies = count($result['result']['items']);
                Logger::debug('Processing policies', [
                    'total_policies' => $totalPolicies
                ]);

                foreach ($result['result']['items'] as $policy) {
                    try {
                        // Evita processar a mesma política mais de uma vez
                        if (in_array($policy['id'], $processedPolicies)) {
                            Logger::debug('Policy already processed, skipping', [
                                'policy_id' => $policy['id']
                            ]);
                            continue;
                        }

                        // Busca detalhes da política
                        $detailsResult = $this->getPolicyDetails([
                            'policyId' => $policy['id'],
                            'api_key_id' => $params['api_key_id']
                        ]);

                        if ($detailsResult) {
                            // Prepara dados básicos da política
                            $policyData = [
                                'id' => $detailsResult['id'],
                                'name' => $detailsResult['name'],
                                'settings' => $detailsResult['settings']
                            ];

                            // Salva a política com seus detalhes
                            $this->policiesModel->savePolicy($policyData);
                            $savedPolicies++;
                            $processedPolicies[] = $policy['id'];

                            Logger::debug('Policy saved', [
                                'policy_id' => $policy['id'],
                                'name' => $policy['name'],
                                'saved_count' => $savedPolicies,
                                'total' => $totalPolicies
                            ]);
                        }

                    } catch (\Exception $e) {
                        Logger::error('Failed to get/save policy details', [
                            'policy_id' => $policy['id'],
                            'name' => $policy['name'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            Logger::info('Finished processing policies', [
                'total_processed' => $savedPolicies,
                'total_in_response' => $result['result']['total'] ?? 0,
                'unique_policies' => count($processedPolicies)
            ]);

            $returnData = $result['result'] ?? null;
            Logger::info('PoliciesService returning data', [
                'return_type' => gettype($returnData),
                'return_data' => $returnData
            ]);

            return $returnData;

        } catch (\Exception $e) {
            Logger::error('GetPoliciesList API request failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
    
    public function getPolicyDetails($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            if (!isset($params['policyId'])) {
                throw new \Exception('Policy ID is required');
            }

            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($params['api_key_id']);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            $this->initializeClient($apiKey['api_key']);

            // Corrigir o endpoint e estrutura da requisição
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getPolicyDetails',
                'params' => [
                    'policyId' => $params['policyId']
                ],
                'id' => uniqid('pd_')
            ];

            Logger::debug('Making getPolicyDetails request', [
                'policy_id' => $params['policyId'],
                'request' => $requestBody
            ]);

            // Corrigir o endpoint para corresponder ao padrão da API
            $response = $this->client->post('/api/v1.0/jsonrpc/policies', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            Logger::debug('getPolicyDetails response received', [
                'policy_id' => $params['policyId'],
                'response' => $result,
                'status_code' => $response->getStatusCode()
            ]);

            // Verifica se há erro na resposta
            if (isset($result['error'])) {
                throw new \Exception($result['error']['message'] ?? 'Unknown API error');
            }

            // Verifica e formata os dados da política
            if (!isset($result['result']) || !is_array($result['result'])) {
                throw new \Exception('Invalid response format from API');
            }

            $policyData = $result['result'];

            // Valida campos obrigatórios
            if (!isset($policyData['id']) || !isset($policyData['name'])) {
                Logger::error('Invalid policy data', [
                    'policy' => $policyData
                ]);
                throw new \Exception('Invalid policy data: missing required fields');
            }

            // Formata os dados da política
            $formattedPolicy = [
                'id' => $policyData['id'],
                'name' => $policyData['name'],
                'settings' => $policyData['settings'] ?? [],
                'api_key_id' => $params['api_key_id'],
                'last_updated' => date('Y-m-d H:i:s')
            ];

            // Salvar os detalhes da política
            $this->policiesModel->savePolicyDetails($params['policyId'], $formattedPolicy);

            return $formattedPolicy;

        } catch (\Exception $e) {
            Logger::error('GetPolicyDetails API request failed', [
                'error' => $e->getMessage(),
                'policy_id' => $params['policyId'] ?? null,
                'api_key_id' => $params['api_key_id'] ?? null
            ]);
            throw $e;
        }
    }

    public function getFilteredPolicies($filters)
    {
        try {
            if (!isset($filters['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            Logger::debug('PoliciesService::getFilteredPolicies called', [
                'filters' => $filters
            ]);

            return $this->policiesModel->getFilteredPolicies($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered policies', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPoliciesFromDb($apiKeyId)
    {
        try {
            Logger::debug('PoliciesService::getPoliciesFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $policies = $this->policiesModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $policies,
                'total' => count($policies),
                'page' => 1,
                'perPage' => count($policies)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get policies from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
 