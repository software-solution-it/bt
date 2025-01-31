<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\WebhookService;
use App\Core\Logger;

class WebhookController extends Controller
{
    private $webhookService;
    
    public function __construct()
    {
        $this->webhookService = new WebhookService();
    }
    
    public function addEvents($params = [])
    {
        try {
            Logger::debug('WebhookController::addEvents called', [
                'params' => $params,
                'raw_input' => file_get_contents('php://input')
            ]);

            // Aceitar tanto params direto quanto dentro de events
            $events = $params['events'] ?? [$params];

            if (empty($events)) {
                throw new \Exception('No events provided');
            }

            $result = $this->webhookService->processEvent([
                'events' => $events
            ]);
            
            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'status' => 'success',
                    'processed_events' => $result['processed_events']
                ],
                'id' => null
            ];

        } catch (\Exception $e) {
            Logger::error('Error processing webhook event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage()
                ],
                'id' => null
            ];
        }
    }

    public function updateSettings($params)
    {
        try {
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            $settings = [
                'jsonrpc' => '2.0',
                'method' => 'updateWebhookSettings',
                'params' => [
                    'serviceSettings' => [
                        'url' => 'https://api-sd.m3solutions.net.br/webhook',
                        'requireValidSslCertificate' => true
                    ],
                    'serviceType' => 'jsonRPC',
                    'status' => 1,
                    'subscribeToEventTypes' => [
                        'task-status' => true,  // Para eventos de scan
                        'modules' => true,
                        'sva' => true,
                        'registration' => true,
                        'av' => true,
                        'aph' => true,
                        'fw' => true,
                        'avc' => true
                    ]
                ]
            ];

            $response = $this->webhookService->makeApiCall($params['api_key_id'], $settings);
            return ['success' => true, 'data' => $response];

        } catch (\Exception $e) {
            Logger::error('Failed to update webhook settings', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
} 