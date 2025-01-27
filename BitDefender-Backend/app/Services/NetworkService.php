<?php

namespace App\Services;

use App\Core\Service;
use App\Config\Environment;
use App\Core\Logger;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use App\Model\NetworkModel;
use App\Model\ApiKeysModel;

class NetworkService extends Service
{
    private $client;
    private $baseUrl;
    private $networkModel;

    public function __construct()
    {
        $this->baseUrl = Environment::get('BITDEFENDER_API_URL');
        $this->networkModel = new NetworkModel();
        
        Logger::debug('Initializing NetworkService', [
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

    public function getEndpointsList($params)
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
                'method' => 'getEndpointsList',
                'params' => [
                    'page' => $params['page'] ?? 1,
                    'perPage' => min($params['perPage'] ?? 30, 100),
                    'filters' => [
                        'security' => [
                            'management' => [
                                'managedWithBest' => true
                            ]
                        ],
                        'depth' => [
                            'allItemsRecursively' => true
                        ]
                    ]
                ],
                'id' => $this->generateRequestId()
            ];

            Logger::debug('Making getEndpointsList request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = $this->handleResponse($response);

            // Log da resposta completa da API
            Logger::info('Raw API response', [
                'result' => $result
            ]);

            if (isset($result['result']['items'])) {
                $endpointsToSync = [
                    'api_key_id' => $params['api_key_id'],
                    'items' => []
                ];

                foreach ($result['result']['items'] as $endpoint) {
                    try {
                        if (!isset($endpoint['id'])) {
                            Logger::info('Endpoint without ID found, skipping', [
                                'endpoint' => $endpoint
                            ]);
                            continue;
                        }

                        // Log dos dados do endpoint antes de obter detalhes
                        Logger::info('Processing endpoint', [
                            'endpoint_id' => $endpoint['id'],
                            'raw_endpoint_data' => $endpoint
                        ]);

                        // Obtém detalhes completos do endpoint
                        $details = $this->getManagedEndpointDetails($endpoint['id'], $params['api_key_id']);
                        
                        // Log dos detalhes retornados pela API
                        Logger::info('Endpoint details from API', [
                            'endpoint_id' => $endpoint['id'],
                            'details' => $details
                        ]);

                        if ($details) {
                            // Combina os dados do endpoint com os detalhes
                            $formattedEndpoint = [
                                'endpoint' => [
                                    'endpoint_id' => $endpoint['id'],
                                    'name' => $endpoint['name'],
                                    'group_id' => $endpoint['groupId'] ?? null,
                                    'api_key_id' => $params['api_key_id'],
                                    'is_managed' => $endpoint['isManaged'] ?? true,
                                    'status' => $this->determineStatus($details),
                                    'ip_address' => $endpoint['ip'] ?? null,
                                    'mac_address' => isset($endpoint['macs'][0]) ? $endpoint['macs'][0] : null,
                                    'operating_system' => $endpoint['operatingSystemVersion'] ?? null,
                                    'operating_system_version' => $endpoint['operatingSystemVersion'] ?? null,
                                    'label' => $endpoint['label'] ?? '',
                                    'last_seen' => isset($details['last_seen']) ? date('Y-m-d H:i:s', strtotime($details['last_seen'])) : null,
                                    'machine_type' => $endpoint['machineType'] ?? 0,
                                    'company_id' => $details['company_id'] ?? null,
                                    'group_name' => $details['group_name'] ?? null,
                                    'policy_id' => isset($endpoint['policy']) ? $endpoint['policy']['id'] : null,
                                    'policy_name' => isset($endpoint['policy']) ? $endpoint['policy']['name'] : null,
                                    'policy_applied' => isset($endpoint['policy']['applied']) ? ($endpoint['policy']['applied'] ? 1 : 0) : 0,
                                    'malware_status' => $details['malware_status'] ?? null,
                                    'agent_info' => $details['agent_info'] ?? null,
                                    'state' => $details['state'] ?? 0,
                                    'modules' => $details['modules'] ?? null,
                                    'managed_with_best' => $endpoint['managedWithBest'] ?? false,
                                    'fqdn' => $endpoint['fqdn'] ?? null,
                                    'macs' => isset($endpoint['macs']) ? json_encode($endpoint['macs']) : null
                                ]
                            ];

                            Logger::info('Formatted endpoint data before sync', [
                                'endpoint_id' => $endpoint['id'],
                                'formatted_data' => $formattedEndpoint
                            ]);

                            $endpointsToSync['items'][] = $formattedEndpoint;
                        }
                    } catch (\Exception $e) {
                        Logger::error('Failed to process endpoint', [
                            'endpoint_id' => $endpoint['id'] ?? 'unknown',
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ]);
                        continue;
                    }
                }

                if (!empty($endpointsToSync['items'])) {
                    // Log antes de sincronizar com o banco
                    Logger::debug('Data to be synced with database', [
                        'total_endpoints' => count($endpointsToSync['items']),
                        'endpoints_data' => $endpointsToSync
                    ]);

                    $this->networkModel->syncEndpoints($endpointsToSync);
                }

                return $endpointsToSync;
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetEndpointsList API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function determineStatus($details)
    {
        if (!isset($details['state'])) {
            return 'unknown';
        }

        $stateMap = [
            0 => 'unknown',
            1 => 'online',
            2 => 'offline',
            3 => 'suspended'
        ];

        return $stateMap[$details['state']] ?? 'unknown';
    }

    private function formatEndpointData($data)
    {
        // Garante que os campos obrigatórios estejam presentes
        $formattedData = [
            'endpoint_id' => $data['id'] ?? null,
            'name' => $data['name'] ?? '',
            'group_id' => $data['group']['id'] ?? null,
            'api_key_id' => $data['api_key_id'] ?? null,
            'is_managed' => isset($data['isManaged']) ? ($data['isManaged'] ? 1 : 0) : 1,
            'status' => $data['status'] ?? 'unknown',
            'ip_address' => $data['ip'] ?? null,
            'mac_address' => isset($data['macs'][0]) ? $data['macs'][0] : null,
            'operating_system' => $data['operatingSystem'] ?? null,
            'operating_system_version' => $data['operatingSystemVersion'] ?? null,
            'label' => $data['label'] ?? null,
            'last_seen' => isset($data['lastSeen']) ? date('Y-m-d H:i:s', strtotime($data['lastSeen'])) : null,
            'machine_type' => $data['machineType'] ?? 0,
            'company_id' => $data['companyId'] ?? null,
            'group_name' => $data['group']['name'] ?? null,
            'policy_id' => $data['policy']['id'] ?? null,
            'policy_name' => $data['policy']['name'] ?? null,
            'policy_applied' => isset($data['policy']['applied']) ? ($data['policy']['applied'] ? 1 : 0) : 0, 
            'malware_status' => isset($data['malwareStatus']) ? json_encode($data['malwareStatus']) : null, 
            'agent_info' => isset($data['agent']) ? json_encode($data['agent']) : null,
            'state' => $this->determineState($data),
            'modules' => isset($data['modules']) ? json_encode($data['modules']) : null,
            'managed_with_best' => isset($data['managedWithBest']) ? ($data['managedWithBest'] ? 1 : 0) : 0,
            'fqdn' => $data['fqdn'] ?? null,
            'macs' => isset($data['macs']) ? json_encode($data['macs']) : null
        ];

        // Valida campos obrigatórios
        if (!$formattedData['endpoint_id']) {
            Logger::error('Missing required field: endpoint_id', [
                'data' => $data
            ]);
            throw new \Exception('Missing required field: endpoint_id');
        }

        return $formattedData;
    }

    public function getManagedEndpointDetails($endpointId, $apiKeyId)
    {
        try {
            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $apiKeysModel = new ApiKeysModel();
            $apiKey = $apiKeysModel->find($apiKeyId);
            
            if (!$apiKey || !$apiKey['is_active']) {
                throw new \Exception('Invalid or inactive API Key');
            }

            $this->initializeClient($apiKey['api_key']);

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getManagedEndpointDetails',
                'params' => [
                    'endpointId' => $endpointId
                ],
                'id' => $this->generateRequestId()
            ]; 

            Logger::debug('Making getManagedEndpointDetails request', [
                'requestBody' => $requestBody,
                'endpointId' => $endpointId
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            // Log detalhado da resposta
            Logger::debug('GetManagedEndpointDetails raw response', [
                'statusCode' => $statusCode,
                'endpointId' => $endpointId,
                'fullResponse' => $result,
                'resultData' => $result['result'] ?? null
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Log dos dados antes da formatação
            if (isset($result['result'])) {
                Logger::debug('Endpoint details before formatting', [
                    'endpointId' => $endpointId,
                    'rawData' => $result['result']
                ]);

                $result['result']['state'] = $this->determineState($result['result']);
                
                // Log dos dados após determinar o state
                Logger::debug('Endpoint details after state determination', [
                    'endpointId' => $endpointId,
                    'state' => $result['result']['state'],
                    'dataBeforeFormat' => $result['result']
                ]);

                // Formatar os dados antes de retornar
                $result['result'] = $this->formatEndpointData($result['result']);

                // Log dos dados após a formatação
                Logger::debug('Endpoint details after formatting', [
                    'endpointId' => $endpointId,
                    'formattedData' => $result['result']
                ]);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetManagedEndpointDetails API request failed', [
                'endpointId' => $endpointId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    private function determineState($endpointData)
    {
        // Mapeamento de estados conforme documentação
        if (!isset($endpointData['state'])) {
            return 0; // unknown
        }

        // Se já for um número, retorna ele mesmo
        if (is_numeric($endpointData['state'])) {
            return (int)$endpointData['state'];
        }

        // Mapeamento de string para inteiro
        $stateMap = [
            'active' => 1,    // online
            'online' => 1,
            'offline' => 2,
            'suspended' => 3,
            'unknown' => 0
        ];

        return $stateMap[strtolower($endpointData['state'])] ?? 0;
    }

    public function createCustomGroup($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'createCustomGroup',
                'params' => [
                    'groupName' => $params['groupName'],
                    'parentId' => $params['parentId'] ?? null
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making createCustomGroup request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('CreateCustomGroup response received', [
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
            Logger::error('CreateCustomGroup API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function deleteCustomGroup($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'deleteCustomGroup',
                'params' => [
                    'groupId' => $params['groupId'],
                    'force' => $params['force'] ?? false
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making deleteCustomGroup request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('DeleteCustomGroup response received', [
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
            Logger::error('DeleteCustomGroup API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getCustomGroupsList($params)
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
                'method' => 'getCustomGroupsList',
                'params' => [],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making getCustomGroupsList request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GetCustomGroupsList response received', [
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
                Logger::debug('Syncing custom groups with database', [
                    'count' => count($result['result']['items'] ?? [])
                ]);
                
                // Adicionar api_key_id ao resultado antes de sincronizar
                $result['result']['api_key_id'] = $params['api_key_id'];
                $this->networkModel->syncCustomGroups($result['result']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetCustomGroupsList API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function moveEndpoints($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'moveEndpoints',
                'params' => [
                    'endpointIds' => $params['endpointIds'],
                    'groupId' => $params['groupId']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making moveEndpoints request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('MoveEndpoints response received', [
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
                $this->networkModel->moveEndpoints($params['endpointIds'], $params['groupId']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('MoveEndpoints API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function deleteEndpoint($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'deleteEndpoint',
                'params' => [
                    'endpointId' => $params['endpointId']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making deleteEndpoint request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('DeleteEndpoint response received', [
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
                $this->networkModel->deleteEndpoint($params['endpointId']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('DeleteEndpoint API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function moveCustomGroup($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'moveCustomGroup',
                'params' => [
                    'groupId' => $params['groupId'],
                    'parentId' => $params['parentId']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making moveCustomGroup request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('MoveCustomGroup response received', [
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
            Logger::error('MoveCustomGroup API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getNetworkInventoryItems($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getNetworkInventoryItems',
                'params' => [
                    'page' => $params['page'] ?? 1,
                    'perPage' => min($params['perPage'] ?? 100, 100),
                    'filters' => [
                        'type' => [
                            'computers' => true,
                            'virtualMachines' => false
                        ],
                        'depth' => [
                            'allItemsRecursively' => true
                        ]
                    ]
                ],
                'id' => $this->generateRequestId()
            ];

            Logger::info('Making getNetworkInventoryItems request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = json_decode($response->getBody()->getContents(), true);

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Sincroniza com o banco de dados
            if (isset($result['result']['items'])) {
                $this->networkModel->syncNetworkInventory($result['result']['items'], $params['api_key_id']);
            }

            // Busca os dados formatados do banco
            $formattedResult = $this->networkModel->getInventory(
                $params['page'] ?? 1,
                $params['perPage'] ?? 100,
                [
                    'api_key_id' => $params['api_key_id'],
                    'type' => [
                        'computers' => true,
                        'virtualMachines' => false
                    ]
                ]
            );

            Logger::debug('Formatted result from database', [
                'total_items' => count($formattedResult['items'] ?? []),
                'total' => $formattedResult['total'] ?? 0,
                'sample_item' => !empty($formattedResult['items']) ? array_keys($formattedResult['items'][0]) : []
            ]);

            return $formattedResult;

        } catch (\Exception $e) {
            Logger::error('GetNetworkInventoryItems API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function createScanTask($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'createScanTask', 
                'params' => [
                    'targetIds' => $params['targetIds'],
                    'type' => $params['type'],
                    'name' => $params['name'] ?? null
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::info('Making createScanTask request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $result = json_decode($responseBody, true);

            Logger::info('CreateScanTask response received', [
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
            Logger::info('CreateScanTask API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getScanTasksList($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'getScanTasksList',
                'params' => [
                    'name' => $params['name'] ?? null,
                    'status' => $params['status'] ?? null,
                    'page' => $params['page'] ?? 1,
                    'perPage' => min($params['perPage'] ?? 30, 100)
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making getScanTasksList request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('GetScanTasksList response received', [
                'statusCode' => $statusCode,
                'response' => $result
            ]);

            if ($statusCode !== 200) {
                throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
            }

            if (isset($result['error'])) {
                throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
            }

            // Adiciona sincronização com o banco
            if ($statusCode === 200 && isset($result['result'])) {
                Logger::debug('Syncing scan tasks with database', [
                    'count' => count($result['result']['items'] ?? [])
                ]);
                
                $this->networkModel->syncScanTasks($result['result'], $params['api_key_id']);
            }

            return $result['result'] ?? null;

        } catch (\Exception $e) {
            Logger::error('GetScanTasksList API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function setEndpointLabel($params)
    {
        try {
            $requestBody = [
                'jsonrpc' => '2.0',
                'method' => 'setEndpointLabel',
                'params' => [
                    'endpointId' => $params['endpointId'],
                    'label' => $params['label']
                ],
                'id' => $this->generateRequestId($params['id'] ?? null)
            ];

            Logger::debug('Making setEndpointLabel request', [
                'requestBody' => $requestBody
            ]);

            $response = $this->client->post('/api/v1.0/jsonrpc/network', [
                RequestOptions::JSON => $requestBody
            ]);

            $statusCode = $response->getStatusCode();
            $responseBody = $response->getBody()->getContents();
            $result = json_decode($responseBody, true);

            Logger::debug('SetEndpointLabel response received', [
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
            Logger::error('SetEndpointLabel API request failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    public function getFilteredNetworks($filters)
    {
        try {
            if (!isset($filters['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            Logger::debug('NetworkService::getFilteredNetworks called', [
                'filters' => $filters
            ]);

            return $this->networkModel->getFilteredNetworks($filters); 

        } catch (\Exception $e) {
            Logger::error('Failed to get filtered networks', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getNetworksFromDb($apiKeyId)
    {
        try {
            Logger::debug('NetworkService::getNetworksFromDb called', [
                'api_key_id' => $apiKeyId
            ]);

            if (!isset($apiKeyId)) {
                throw new \Exception('API Key ID is required');
            }

            $networks = $this->networkModel->findByApiKeyId($apiKeyId);

            return [
                'items' => $networks,
                'total' => count($networks),
                'page' => 1,
                'perPage' => count($networks)
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get networks from database', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncScanTasks($tasks, $apiKeyId)
    {
        try {
            Logger::debug('NetworkService::syncScanTasks called', [
                'tasks_count' => count($tasks['items'] ?? [])
            ]);

            return $this->networkModel->syncScanTasks($tasks, $apiKeyId);

        } catch (\Exception $e) {
            Logger::error('Failed to sync scan tasks', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function handleResponse($response)
    {
        $statusCode = $response->getStatusCode();
        $responseBody = $response->getBody()->getContents();
        $result = json_decode($responseBody, true);

        if ($statusCode !== 200) {
            throw new \Exception("HTTP Error: {$statusCode}. Response: {$responseBody}");
        }

        if (isset($result['error'])) {
            throw new \Exception($result['error']['data']['details'] ?? $result['error']['message']);
        }

        return $result;
    }
}
