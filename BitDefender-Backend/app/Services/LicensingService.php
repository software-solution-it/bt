<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\LicensingModel;
use App\Model\ApiKeysModel;

class LicensingService extends Service
{
    private $client;
    private $baseUrl;
    private $licensingModel;
    private $apiKeysModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->licensingModel = new LicensingModel();
        $this->apiKeysModel = new ApiKeysModel();
        
        Logger::debug('Initializing LicensingService', [
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

    public function getLicenseInfo($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Busca a API key do banco
            $apiKeyData = $this->apiKeysModel->find($params['api_key_id']);
            if (!$apiKeyData) {
                throw new \Exception('API Key not found');
            }

            $this->initializeClient($apiKeyData['api_key']);

            // Primeiro tenta obter do banco local
            $localLicense = $this->licensingModel->getLicenseInfo($params['api_key_id']);
            if ($localLicense && !$this->isLicenseInfoStale($localLicense)) {
                return $this->formatLicenseResponse($localLicense);
            } 

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getLicenseInfo',
                'params' => [],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            $response = $this->client->post('/api/v1.0/jsonrpc/licensing', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if ($statusCode === 200 && isset($result['result'])) {
                // Sync with database
                $this->licensingModel->syncLicenseInfo($result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetLicenseInfo API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function setLicenseKey($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            if (!isset($params['licenseKey'])) {
                throw new \Exception('License key is required');
            }

            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($params['api_key_id']);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            $this->initializeClient($apiKey['api_key']);

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'setLicenseKey',
                'params' => [
                    'licenseKey' => $params['licenseKey']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            $response = $this->client->post('/api/v1.0/jsonrpc/licensing', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            if ($statusCode === 200) {
                // Após definir a nova chave, atualiza as informações da licença
                $licenseInfo = $this->getLicenseInfo($params);
                if ($licenseInfo) {
                    $this->licensingModel->syncLicenseInfo($licenseInfo, $params['api_key_id']);
                }
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('SetLicenseKey API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getMonthlyUsage($params)
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

            $targetMonth = $params['targetMonth'] ?? date('m/Y');

            // Primeiro tenta obter do banco local
            $localUsage = $this->licensingModel->getMonthlyUsage($targetMonth);
            if ($localUsage && !$this->isUsageDataStale($localUsage)) {
                return $this->formatUsageResponse($localUsage);
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getMonthlyUsage',
                'params' => [
                    'targetMonth' => $targetMonth
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            $response = $this->client->post('/api/v1.0/jsonrpc/licensing', [
                RequestOptions::JSON => $requestBody
            ]);
 
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true); 

            if ($statusCode === 200 && isset($result['result'])) {
                // Sync with database
                $this->licensingModel->syncMonthlyUsage($targetMonth, $result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetMonthlyUsage API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function isLicenseInfoStale($licenseInfo)
    {
        // Considera informações com mais de 1 hora como desatualizadas
        return (strtotime($licenseInfo['updated_at']) < (time() - 3600));
    }

    private function isUsageDataStale($usageData)
    {
        // Considera dados de uso com mais de 1 dia como desatualizados
        return (strtotime($usageData['updated_at']) < (time() - 86400));
    }

    private function formatLicenseResponse($localLicense)
    {
        return [
            'isAddon' => (bool)$localLicense['is_addon'],
            'expiryDate' => $localLicense['expiry_date'],
            'usedSlots' => (int)$localLicense['used_slots'],
            'totalSlots' => (int)$localLicense['total_slots'],
            'licenseKey' => $localLicense['license_key'],
            'ownUse' => $localLicense['own_use'],
            'resell' => $localLicense['resell'],
            'subscriptionType' => (int)$localLicense['subscription_type']
        ];
    }

    private function formatUsageResponse($localUsage)
    {
        return [
            'totalEndpoints' => $localUsage['total_endpoints'],
            'activeEndpoints' => $localUsage['active_endpoints'],
            'details' => $localUsage['usage_details']
        ];
    }

    public function getFilteredLicenses($filters)
    {
        try {
            Logger::debug('LicensingService::getFilteredLicenses called', [
                'filters' => $filters
            ]);

            return $this->licensingModel->getFilteredLicenses($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered licenses', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getLicensesFromDb($apiKeyId)
    {
        try {
            Logger::debug('LicensingService::getLicensesFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $licenses = $this->licensingModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $licenses,
                'total' => count($licenses),
                'page' => 1,
                'perPage' => count($licenses)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get licenses from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
