<?php

namespace App\Core;

class Controller
{
    protected function jsonResponse($data, $statusCode = 200)
    {
        try {
            Logger::debug('Controller::jsonResponse - Starting', [
                'data_type' => gettype($data),
                'status_code' => $statusCode
            ]);

            header('Content-Type: application/json');
            http_response_code($statusCode);
            
            // Se já for uma string JSON, não codifica novamente
            if (is_string($data)) {
                try {
                    // Verifica se é um JSON válido
                    json_decode($data);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Logger::debug('Controller::jsonResponse - Data is already valid JSON string');
                        echo $data;
                        exit;
                    }
                } catch (\Exception $e) {
                    Logger::error('Controller::jsonResponse - Error checking JSON string', [
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Se chegou aqui, precisa codificar
            try {
                Logger::debug('Controller::jsonResponse - Encoding data to JSON');
                $jsonString = json_encode($data);
                
                if ($jsonString === false) {
                    throw new \Exception('JSON encode failed: ' . json_last_error_msg());
                }

                Logger::debug('Controller::jsonResponse - Successfully encoded response');
                echo $jsonString;
                exit;

            } catch (\Exception $e) {
                Logger::error('Controller::jsonResponse - Error encoding JSON', [
                    'error' => $e->getMessage(),
                    'data' => $data
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Logger::error('Controller::jsonResponse - Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $data
            ]);

            // Retorna um erro genérico em caso de falha
            echo json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => 'Error processing response'
                    ]
                ]
            ]);
            exit;
        }
    }

    protected function errorResponse($message, $code = 400)
    {
        return $this->jsonResponse([
            'jsonrpc' => '2.0',
            'error' => [
                'message' => $message
            ]
        ], $code);
    }

    protected function getRequestData()
    {
        try {
            $rawBody = file_get_contents('php://input');
            
            if (empty($rawBody)) {
                return [];
            }

            // Se já for um array, retorna diretamente
            if (is_array($rawBody)) {
                return $rawBody;
            }

            // Tenta decodificar apenas se for string
            $data = json_decode($rawBody, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON: ' . json_last_error_msg());
            }

            return $data; 
        } catch (\Exception $e) {
            Logger::error('Failed to parse request data', [
                'error' => $e->getMessage(),
                'raw_body' => $rawBody
            ]);
            throw $e;
        }
    }
}