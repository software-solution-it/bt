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
    
    public function addEvents($request)
    {
        try {
            $body = file_get_contents('php://input');
            $data = json_decode($body, true);

            Logger::debug('Received webhook event', [
                'data' => $data
            ]);

            $result = $this->webhookService->processEvent($data);
            
            // Retorna diretamente o resultado do processamento
            return [
                'status' => 'success',
                'processed_events' => $result['processed_events']
            ];

        } catch (\Exception $e) {
            Logger::error('Error processing webhook event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
} 