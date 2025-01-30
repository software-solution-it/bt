<?php

namespace App\Services;

use App\Core\Service;
use App\Core\Logger;
use App\Model\WebhookModel;
use App\Model\NetworkModel;

class WebhookService extends Service
{
    private $webhookModel;
    private $networkModel;

    public function __construct()
    {
        $this->webhookModel = new WebhookModel();
        $this->networkModel = new NetworkModel();
    }

    public function processEvent($data)
    {
        try {
            Logger::debug('Processing webhook event', [
                'data' => $data
            ]);

            if (!isset($data['events']) || !is_array($data['events'])) {
                throw new \Exception('Invalid event format');
            }

            $processedEvents = 0;
            foreach ($data['events'] as $event) {
                // Validar campos obrigatórios
                if (!isset($event['computer_name']) || !isset($event['module'])) {
                    Logger::info('Skipping invalid event', ['event' => $event]);
                    continue;
                }

                // Preparar dados para inserção
                $eventData = [
                    'endpoint_id' => $event['computer_id'] ?? null,
                    'computer_name' => $event['computer_name'],
                    'computer_ip' => $event['computer_ip'] ?? null,
                    'event_type' => $event['module'],
                    'event_data' => json_encode($event),
                    'severity' => $this->determineSeverity($event),
                    'status' => 'pending',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                // Usar o webhookModel ao invés de inserir diretamente
                $this->webhookModel->saveEvent($eventData);
                $processedEvents++;
            }

            return [
                'processed_events' => $processedEvents
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to process webhook event', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e; 
        }
    }

    private function determineSeverity($event)
    {
        // Lógica para determinar a severidade baseada no tipo de evento
        if (isset($event['_testEvent_']) && $event['_testEvent_'] === true) {
            return 'low';
        }
        
        // Adicione mais lógica de severidade aqui
        return 'medium';
    }
} 