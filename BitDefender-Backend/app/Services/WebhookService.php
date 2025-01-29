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

    public function processEvent($params)
    {
        try {
            Logger::debug('Processing webhook event', [
                'params' => $params
            ]);

            if (!isset($params['params']['events'])) {
                throw new \Exception('No events found in webhook payload');
            }

            $results = [];
            foreach ($params['params']['events'] as $event) {
                // Extrai o endpoint_id do evento baseado no tipo
                $endpointId = $this->getEndpointId($event);
                
                // Busca o endpoint relacionado
                $endpoint = null;
                if ($endpointId) {
                    $endpoint = $this->networkModel->findEndpoint($endpointId);
                }

                // Prepara os dados do evento
                $eventData = [
                    'endpoint_id' => $endpointId,
                    'api_key_id' => $endpoint['api_key_id'] ?? null,
                    'event_type' => $event['module'],
                    'event_data' => json_encode($event),
                    'severity' => $this->determineSeverity($event),
                    'status' => 'new',
                    'computer_name' => $event['computer_name'] ?? $event['computerName'] ?? null,
                    'computer_ip' => $event['computer_ip'] ?? $event['computerIp'] ?? null,
                    'created_at' => date('Y-m-d H:i:s')
                ];

                // Salva o evento
                $eventId = $this->webhookModel->saveEvent($eventData);

                $results[] = [
                    'event_id' => $eventId,
                    'endpoint_id' => $endpointId,
                    'event_type' => $eventData['event_type']
                ];

                Logger::info('Webhook event processed', [
                    'event_id' => $eventId,
                    'event_type' => $event['module']
                ]);
            }

            return [
                'status' => 'success',
                'processed_events' => $results
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to process webhook event', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    private function getEndpointId($event)
    {
        return $event['endpointId'] ?? 
               $event['computer_id'] ?? 
               null;
    }

    private function determineSeverity($event)
    {
        switch ($event['module']) {
            case 'av':
                if (isset($event['malware_type']) && 
                    in_array($event['malware_type'], ['ransomware', 'exploit'])) {
                    return 'high';
                }
                return 'medium';

            case 'network-sandboxing':
            case 'avc':
            case 'hd':
                return 'high';

            case 'fw':
            case 'aph':
            case 'dp':
            case 'exchange-malware':
                return 'medium';

            case 'modules':
            case 'registration':
            case 'task-status':
            case 'sva':
            case 'sva-load':
            case 'supa-update-status':
                return 'low';

            default:
                return 'info'; 
        }
    }
} 