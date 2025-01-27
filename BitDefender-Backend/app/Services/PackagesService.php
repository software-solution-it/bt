<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\PackagesModel;
use App\Model\ApiKeysModel;

class PackagesService extends Service
{
    private $client;
    private $baseUrl;
    private $packagesModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->packagesModel = new PackagesModel();
        
        Logger::debug('Initializing PackagesService', [
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

    public function getInstallationLinks($params)
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
                'method' => 'getInstallationLinks',
                'params' => isset($params['packageName']) ? ['packageName' => $params['packageName']] : [],
                'id' => $this->generateRequestId()
            ];

            Logger::debug('Making getInstallationLinks request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/packages', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Adiciona api_key_id aos links
            if (isset($result['result']) && is_array($result['result'])) {
                foreach ($result['result'] as &$link) {
                    $link['api_key_id'] = $params['api_key_id'];
                }
                $this->packagesModel->syncInstallationLinks($result['result']);
            }

            return $result['result'] ?? [];

        } catch (\Exception $e) {
            Logger::error('GetInstallationLinks API request failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createPackage($params)
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

            // Estrutura o pacote corretamente
            $package = [
                'packageName' => $params['packageName'] ?? '',
                'description' => $params['description'] ?? '',
                'language' => $params['language'] ?? 'en_US',
                'modules' => [
                    'antimalware' => 1,
                    'firewall' => 1,
                    'contentControl' => 1,
                    'deviceControl' => 1,
                    'powerUser' => 0,
                    'advancedThreatControl' => 1,
                    'applicationControl' => 0,
                    'encryption' => 0,
                    'networkAttackDefense' => 1,
                    'antiTampering' => 1,
                    'advancedAntiExploit' => 1,
                    'userControl' => 1,
                    'antiphishing' => 1,
                    'trafficScan' => 1,
                    'edrSensor' => 1,
                    'hyperDetect' => 1,
                    'remoteEnginesScanning' => 0,
                    'sandboxAnalyzer' => 0,
                    'riskManagement' => 1
                ],
                'roles' => [
                    'relay' => 0,
                    'exchange' => 0
                ],
                'scanMode' => [
                    'type' => 1,
                    'computers' => ['main' => 3],
                    'vms' => ['main' => 3],
                    'ec2' => ['main' => 1, 'fallback' => 2],
                    'azure' => ['main' => 3]
                ],
                'settings' => [
                    'removeCompetitors' => true,
                    'scanBeforeInstall' => false,
                    'customGroupId' => $params['settings']['customGroupId'] ?? null
                ],
                'deploymentOptions' => [
                    'type' => 1
                ],
                'productType' => 0
            ];

            // Mescla com os parâmetros fornecidos, mantendo a estrutura base
            if (isset($params['modules'])) {
                $package['modules'] = array_merge($package['modules'], $params['modules']);
            }
            if (isset($params['roles'])) {
                $package['roles'] = array_merge($package['roles'], $params['roles']);
            }
            if (isset($params['scanMode'])) {
                $package['scanMode'] = array_merge($package['scanMode'], $params['scanMode']);
            }
            if (isset($params['settings'])) {
                $package['settings'] = array_merge($package['settings'], $params['settings']);
            }
            if (isset($params['deploymentOptions'])) {
                $package['deploymentOptions'] = array_merge($package['deploymentOptions'], $params['deploymentOptions']);
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'createPackage',
                'params' => ['package' => $package],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making createPackage request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/packages', [
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
            Logger::error('CreatePackage API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    public function getPackagesList($params)
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

            Logger::info('Starting getPackagesList', [
                'params' => [
                    'api_key_id' => $params['api_key_id'],
                    'page' => $params['page'] ?? 1,
                    'perPage' => $params['perPage'] ?? 100
                ]
            ]);

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getPackagesList',
                'params' => [
                    'page' => $params['page'] ?? 1,
                    'perPage' => min($params['perPage'] ?? 30, 100)
                ],
                'id' => $this->generateRequestId()
            ];

            Logger::info('Making getPackagesList request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/packages', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Processa cada pacote e busca seus detalhes
            if (isset($result['result']['items'])) {
                foreach ($result['result']['items'] as &$package) {
                    $basicInfo = [
                        'package_id' => $package['id'],
                        'api_key_id' => $params['api_key_id'],
                        'name' => $package['name'],
                        'type' => $package['type'] ?? 0
                    ];

                    try {
                        $details = $this->getPackageDetails(['packageId' => $package['id'], 'api_key_id' => $params['api_key_id']]);
                        $this->packagesModel->syncPackageDetails($basicInfo, $details);
                        $package['details'] = $details;
                    } catch (\Exception $e) {
                        Logger::error('Failed to get package details', [
                            'package_id' => $package['id'],
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            // Busca os installation links
            try {
                $installationLinks = $this->getInstallationLinks(['api_key_id' => $params['api_key_id']]);
                if ($installationLinks) {
                    $result['result']['installationLinks'] = $installationLinks;
                }
            } catch (\Exception $e) {
                Logger::error('Failed to get installation links', [
                    'error' => $e->getMessage()
                ]);
            }

            return $result['result'];

        } catch (\Exception $e) {
            Logger::error('GetPackagesList API request failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function extractPackageId($url) {
        if (preg_match('/packageId=([^&]+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }

    public function syncInstallationLinks($links)
    {
        try {
            if (empty($links)) {
                Logger::debug('No installation links to sync');
                return true;
            }

            Logger::info('Processing installation links', [
                'total_links' => count($links)
            ]);

            // Processar cada link
            foreach ($links as &$link) {
                $link['company_id'] = $link['companyId'] ?? null;
                $link['company_name'] = $link['companyName'] ?? null;
            }

            Logger::debug('Calling model syncInstallationLinks', [
                'processed_links' => $links
            ]);

            return $this->packagesModel->syncInstallationLinks($links);

        } catch (\Exception $e) {
            Logger::error('Failed to sync installation links', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function deletePackage($params)
    {
        try {
            if (empty($params['packageId'])) {
                throw new \Exception('Package ID is required');
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'deletePackage',
                'params' => [
                    'packageId' => $params['packageId']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making deletePackage request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/packages', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('DeletePackage response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('DeletePackage API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    
    public function getPackageDetails($params)
    {
        try {
            if (!isset($params['packageId'])) {
                throw new \Exception('Package ID is required');
            }
 
            // Primeiro tenta obter do banco local
            $localDetails = $this->packagesModel->getPackageDetails($params['packageId']);
            if ($localDetails) {
                return $localDetails;
            }

            // Se não encontrar, busca da API
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getPackageDetails',
                'params' => [
                    'packageId' => $params['packageId']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making getPackageDetails request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/packages', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('Package details response received', [
                'statusCode' => $statusCode,
                'result' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            if (!isset($result['result'])) {
                throw new \Exception('Invalid response format: missing result object');
            }

            // Separe os dados em basicInfo e details
            $basicInfo = [
                'package_id' => $params['packageId'],
                'api_key_id' => $params['api_key_id'],
                'name' => $result['result']['packageName'], 
                'type' => 0
            ];

            $details = $result['result'];

            // Mapeia os campos conforme a documentação
            $packageDetails = [
                'package_id' => $params['packageId'],
                'packageName' => $result['result']['packageName'] ?? '',
                'description' => $result['result']['description'] ?? '',
                'language' => $result['result']['language'] ?? 'en_US',
                'modules' => [
                    'antimalware' => $result['result']['modules']['antimalware'] ?? 1,
                    'firewall' => $result['result']['modules']['firewall'] ?? 1,
                    'contentControl' => $result['result']['modules']['contentControl'] ?? 1,
                    'deviceControl' => $result['result']['modules']['deviceControl'] ?? 1,
                    'powerUser' => $result['result']['modules']['powerUser'] ?? 0,
                    'advancedThreatControl' => $result['result']['modules']['advancedThreatControl'] ?? 1,
                    'applicationControl' => $result['result']['modules']['applicationControl'] ?? 0,
                    'encryption' => $result['result']['modules']['encryption'] ?? 0,
                    'networkAttackDefense' => $result['result']['modules']['networkAttackDefense'] ?? 1,
                    'antiTampering' => $result['result']['modules']['antiTampering'] ?? 1,
                    'advancedAntiExploit' => $result['result']['modules']['advancedAntiExploit'] ?? 1,
                    'userControl' => $result['result']['modules']['userControl'] ?? 1,
                    'antiphishing' => $result['result']['modules']['antiphishing'] ?? 1,
                    'trafficScan' => $result['result']['modules']['trafficScan'] ?? 1,
                    'edrSensor' => $result['result']['modules']['edrSensor'] ?? 1,
                    'hyperDetect' => $result['result']['modules']['hyperDetect'] ?? 1,
                    'remoteEnginesScanning' => $result['result']['modules']['remoteEnginesScanning'] ?? 0,
                    'sandboxAnalyzer' => $result['result']['modules']['sandboxAnalyzer'] ?? 0,
                    'riskManagement' => $result['result']['modules']['riskManagement'] ?? 1
                ],
                'roles' => [
                    'relay' => $result['result']['roles']['relay'] ?? 0,
                    'exchange' => $result['result']['roles']['exchange'] ?? 0
                ],
                'scanMode' => $result['result']['scanMode'] ?? [
                    'type' => 1,
                    'computers' => ['main' => 3],
                    'vms' => ['main' => 3],
                    'ec2' => ['main' => 1, 'fallback' => 2],
                    'azure' => ['main' => 3]
                ],
                'settings' => $result['result']['settings'] ?? [
                    'removeCompetitors' => true,
                    'scanBeforeInstall' => false,
                    'customGroupId' => null
                ],
                'deploymentOptions' => $result['result']['deploymentOptions'] ?? [
                    'type' => 1
                ],
                'api_key_id' => $params['api_key_id'] ?? null
            ];

            // Sincroniza com o banco de dados
            $this->packagesModel->syncPackageDetails($basicInfo, $details);

            return $packageDetails;

        } catch (\Exception $e) {
            Logger::error('GetPackageDetails API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredPackages($filters)
    {
        try {
            Logger::debug('PackagesService::getFilteredPackages called', [
                'filters' => $filters
            ]);

            return $this->packagesModel->getFilteredPackages($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered packages', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPackagesFromDb($apiKeyId)
    {
        try {
            Logger::debug('PackagesService::getPackagesFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $packages = $this->packagesModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $packages,
                'total' => count($packages),
                'page' => 1,
                'perPage' => count($packages)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get packages from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        } 
    }
}
