<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\MachineService;
use App\Core\Logger;

class MachineController extends Controller
{
    private $machineService;

    public function __construct()
    {
        $this->machineService = new MachineService();
    }

    private function sendJsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }

    public function getInventory($params = [])
    {
        try {
            $apiKeyId = $params['api_key_id'] ?? null;
            if (!$apiKeyId) {
                throw new \Exception('API Key ID is required');
            }

            // Extrair os filtros
            $tables = $params['tables'] ?? null;
            $filters = $params['filters'] ?? [];

            // Se tables não for especificado, retorna tudo
            if (!$tables) {
                $result = $this->machineService->getAllInventoryData($apiKeyId, $filters);
            } else {
                // Se for string, converte para array
                if (is_string($tables)) {
                    $tables = [$tables];
                }
                $result = $this->machineService->getAllInventoryData([
                    'api_key_id' => $apiKeyId,
                    'tables' => $tables,
                    'filters' => $filters
                ]);
            }

            // Se for uma única tabela, retorna diretamente o resultado
            if (isset($result['items'])) {
                return [
                    'jsonrpc' => '2.0',
                    'result' => $result,
                    'id' => null
                ];
            }

            // Se for múltiplas tabelas, mantém a estrutura original
            return [
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => null
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get inventory', [
                'error' => $e->getMessage(),
                'params' => $params
            ]);
            throw $e;
        }
    }

    private function getRequestParams()
    {
        $jsonBody = file_get_contents('php://input');
        $params = json_decode($jsonBody, true);
        return $params['params'] ?? [];
    }
}
