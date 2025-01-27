<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\PushModel;
use App\Model\ApiKeysModel;

class PushService extends Service
{
    private $client;
    private $baseUrl;
    private $pushModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->pushModel = new PushModel();
        
        Logger::debug('Initializing PushService', [
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
            RequestOptions::CONNECT_TIMEOUT => 30,
            'curl' => [
                CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2,
                CURLOPT_SSL_CIPHER_LIST => 'TLSv1.2'
            ]
        ]);
    }

    private function validateServiceSettings($serviceSettings)
    {
        if (empty($serviceSettings['url'])) {
            throw new \Exception('Service URL is required');
        }

        if (!filter_var($serviceSettings['url'], FILTER_VALIDATE_URL)) {
            throw new \Exception('Invalid service URL format');
        }

        // Verifica se a URL usa HTTPS
        $urlParts = parse_url($serviceSettings['url']);
        if (!isset($urlParts['scheme']) || $urlParts['scheme'] !== 'https') {
            throw new \Exception('Service URL must use HTTPS protocol');
        }

        // Adiciona o path se não existir
        if (!isset($urlParts['path']) || empty($urlParts['path'])) {
            $serviceSettings['url'] .= '/api/v1.0/jsonrpc/push';
        }

        // Se for ngrok, ignora validação de TLS
        if (strpos($serviceSettings['url'], 'ngrok') !== false) {
            $serviceSettings['requireValidSslCertificate'] = false;
            return true;
        }

        // Para outras URLs, mantém a validação de TLS
        if (!isset($serviceSettings['requireValidSslCertificate'])) {
            $serviceSettings['requireValidSslCertificate'] = true;
        }

        return true;
    }

    private function makeRequest($method, $params = [], $requestId = null)
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

            // Valida as configurações do serviço para setPushEventSettings
            if ($method === 'setPushEventSettings' && isset($params['serviceSettings'])) {
                $this->validateServiceSettings($params['serviceSettings']);
                
                // Adiciona o header de autorização se não existir
                if (!isset($params['serviceSettings']['authorization'])) {
                    $params['serviceSettings']['authorization'] = 'Basic ' . base64_encode($apiKey['api_key'] . ':');
                }
            }
    
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => $requestId ?? uniqid()
            ];
    
            Logger::debug("Making {$method} request", [
                'requestBody' => $requestBody
            ]);
    
            // Configurações adicionais para ngrok ou endpoints inseguros
            $options = [
                RequestOptions::JSON => $requestBody,
                RequestOptions::VERIFY => false, // Desabilita verificação de SSL para ngrok
                'curl' => [
                    CURLOPT_SSLVERSION => CURL_SSLVERSION_TLSv1_2, // Força TLS 1.2
                    CURLOPT_SSL_CIPHER_LIST => 'TLSv1.2'          // Define lista de cifrões permitidos
                ]
            ];
    
            // Faz a requisição POST
            $response = $this->client->post('/api/v1.0/jsonrpc/push', $options);
    
            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);
    
            Logger::debug("{$method} response received", [
                'statusCode' => $statusCode,
                'response' => $result
            ]);
    
            // Trata erros de status HTTP
            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }
    
            // Trata erros no corpo da resposta
            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }
    
            return $result['result'] ?? null;
    
        } catch (\Exception $e) {
            Logger::error("{$method} API request failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
    

    public function setPushEventSettings($params)
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

            // Validação básica dos parâmetros
            if (!isset($params['status'])) {
                throw new \Exception('Status parameter is required');
            }

            if (!isset($params['serviceType'])) {
                throw new \Exception('Service type parameter is required');
            }

            if (!isset($params['serviceSettings']) || !isset($params['serviceSettings']['url'])) {
                throw new \Exception('Service URL is required');
            }

            // Adiciona api_key_id aos dados
            $params['serviceSettings']['api_key_id'] = $params['api_key_id'];

            return $this->makeRequest('setPushEventSettings', $params);
            
        } catch (\Exception $e) {
            Logger::error('Failed to set and sync push event settings', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    public function getPushEventSettings()
    {
        try {
            $apiResult = $this->makeRequest('getPushEventSettings');
            if ($apiResult) {
                // Sincroniza com o banco local
                $this->pushModel->syncWithApi($apiResult);
            }
            return $apiResult;
        } catch (\Exception $e) {
            Logger::error('Failed to get and sync push event settings', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function sendTestPushEvent($params)
    {
        return $this->makeRequest('sendTestPushEvent', $params);
    }

    public function getPushEventStats()
    {
        try {
            $apiResult = $this->makeRequest('getPushEventStats');
            if ($apiResult) {
                // Sincroniza com o banco local
                $this->pushModel->syncStatsWithApi($apiResult);
            }
            return $apiResult;
        } catch (\Exception $e) {
            Logger::error('Failed to get and sync push event stats', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function resetPushEventStats()
    {
        return $this->makeRequest('resetPushEventStats');
    }

    public function processEvent($event)
    {
        try {
            // Validação básica do evento
            if (!isset($event['module'])) {
                throw new \Exception('Event module is required');
            }

            if (!isset($event['companyId'])) {
                throw new \Exception('Company ID is required');
            }

            Logger::debug('Processing event', [
                'module' => $event['module'],
                'companyId' => $event['companyId']
            ]);

            // Atualiza estatísticas
            $this->updateEventStats($event);

            // Salva o evento no banco
            return $this->pushModel->saveEvent($event);

        } catch (\Exception $e) {
            Logger::error('Failed to process event', [
                'error' => $e->getMessage(),
                'event' => $event
            ]);
            throw $e;
        }
    }

    private function updateEventStats($event)
    {
        try {
            // Obtém estatísticas atuais
            $currentStats = $this->pushModel->getPushEventStats() ?? [
                'count' => [
                    'events' => 0,
                    'testEvents' => 0,
                    'sentMessages' => 0,
                    'errorMessages' => 0
                ],
                'error' => [
                    'connectionError' => 0,
                    'statusCode300' => 0,
                    'statusCode400' => 0,
                    'statusCode500' => 0,
                    'timeout' => 0
                ]
            ];

            // Incrementa contadores
            $currentStats['count']['events']++;
            if (isset($event['_testEvent_']) && $event['_testEvent_']) {
                $currentStats['count']['testEvents']++;
            }
            $currentStats['count']['sentMessages']++;

            // Atualiza estatísticas no banco
            $this->pushModel->updatePushStats($currentStats);

        } catch (\Exception $e) {
            Logger::error('Failed to update event stats', [
                'error' => $e->getMessage()
            ]);
            // Não lança exceção para não interromper o processamento do evento
        }
    }

    public function getFilteredPushSettings($filters)
    {
        try {
            Logger::debug('PushService::getFilteredPushSettings called', [
                'filters' => $filters
            ]);

            return $this->pushModel->getFilteredPushSettings($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered push settings', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPushFromDb($apiKeyId)
    {
        try {
            Logger::debug('PushService::getPushFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $pushSettings = $this->pushModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $pushSettings,
                'total' => count($pushSettings),
                'page' => 1,
                'perPage' => count($pushSettings)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get push settings from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
