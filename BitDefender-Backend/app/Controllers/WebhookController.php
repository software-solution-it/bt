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
} 