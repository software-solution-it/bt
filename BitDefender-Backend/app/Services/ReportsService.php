<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\ReportsModel;
use App\Model\ApiKeysModel;

class ReportsService extends Service
{
    private $client;
    private $baseUrl;
    private $reportsModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->reportsModel = new ReportsModel();
        
        Logger::debug('Initializing ReportsService', [
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
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/reports', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug("{$method} response received", [
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
            Logger::error("{$method} API request failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function createReport($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Ensure 'format' is set
            if (empty($params['format'])) {
                throw new \Exception('Format is required');
            }

            $result = $this->makeRequest('createReport', $params);

            if ($result && isset($result['reportId'])) {
                // Adiciona api_key_id ao salvar no banco
                $result['api_key_id'] = $params['api_key_id'];
                $this->reportsModel->syncReports(['items' => [$result]]);
            }

            return $result;

        } catch (\Exception $e) {
            Logger::error('CreateReport API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getReportsList($params = [])
    {
        try {
            $result = $this->makeRequest('getReportsList', $params);

            if ($result && isset($result['items'])) {
                // Adiciona api_key_id ao resultado antes de sincronizar
                $result['api_key_id'] = $params['api_key_id'];
                $this->reportsModel->syncReports($result);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('GetReportsList failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getDownloadLinks($params)
    {
        try {
            if (empty($params['reportId'])) {
                throw new \Exception('Report ID is required');
            }

            $result = $this->makeRequest('getDownloadLinks', $params);

            if ($result && isset($result['downloadUrl'])) {
                // Update download links in database
                $this->reportsModel->updateDownloadLinks(
                    $params['reportId'],
                    $result['downloadUrl'],
                    $result['expiresAt'] ?? null
                );

                // Log download attempt
                $this->reportsModel->logDownload(
                    $params['reportId'],
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    $_SERVER['HTTP_USER_AGENT'] ?? null
                );
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('GetDownloadLinks failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function deleteReport($params)
    {
        try {
            if (empty($params['reportId'])) {
                throw new \Exception('Report ID is required');
            }

            $result = $this->makeRequest('deleteReport', $params);

            if ($result) {
                // Mark as deleted in database
                $this->reportsModel->markAsDeleted($params['reportId']);
            }

            return $result;
        } catch (\Exception $e) {
            Logger::error('DeleteReport failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredReports($filters)
    {
        try {
            if (!isset($filters['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            Logger::debug('ReportsService::getFilteredReports called', [
                'filters' => $filters
            ]);

            return $this->reportsModel->getFilteredReports($filters);

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered reports', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getReportsFromDb($apiKeyId)
    {
        try {
            Logger::debug('ReportsService::getReportsFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $reports = $this->reportsModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $reports,
                'total' => count($reports),
                'page' => 1,
                'perPage' => count($reports)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get reports from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function generateRequestId($clientId = null) {
        return $clientId ?? uniqid('bd_', true);
    }
}
