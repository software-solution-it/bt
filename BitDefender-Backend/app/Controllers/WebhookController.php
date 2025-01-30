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

    public function getEvents($params = [])
    {
        try {
            Logger::debug('WebhookController::getEvents called', [
                'params' => $params
            ]);

            // Validar parâmetros necessários
            if (!isset($params['api_key_id'])) {
                throw new \Exception('API Key ID is required');
            }

            // Buscar eventos do webhook
            $query = "SELECT * FROM webhook_events 
                     WHERE api_key_id = :api_key_id 
                     ORDER BY created_at DESC 
                     LIMIT 100";
                     
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                'api_key_id' => $params['api_key_id']
            ]);

            $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Processar os eventos
            $processedEvents = array_map(function($event) {
                if (isset($event['event_data']) && is_string($event['event_data'])) {
                    $event['event_data'] = json_decode($event['event_data'], true);
                }
                return $event;
            }, $events);

            return [
                'jsonrpc' => '2.0',
                'result' => [
                    'items' => $processedEvents,
                    'total' => count($processedEvents)
                ],
                'id' => null
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get webhook events', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }
} 