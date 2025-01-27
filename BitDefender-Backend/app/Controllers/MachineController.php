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

    public function getInventory()
    {
        try {
            $params = $this->getRequestParams();

            $page = max(1, (int)($params['page'] ?? 1));
            $perPage = min(100, max(1, (int)($params['perPage'] ?? 30)));

            $filters = [
                'type' => [
                    'computers' => $params['filters']['type']['computers'] ?? true,
                    'virtualMachines' => $params['filters']['type']['virtualMachines'] ?? true
                ],
                'depth' => [
                    'allItemsRecursively' => $params['filters']['depth']['allItemsRecursively'] ?? true
                ]
            ];

            $result = $this->machineService->getMachineInventory([
                'page' => $page,
                'perPage' => $perPage,
                'filters' => $filters,
                'api_key_id' => $params['api_key_id'] ?? null
            ]);

            $this->sendJsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? uniqid('mc_')
            ]);

        } catch (\Exception $e) {
            Logger::error('Failed to get machine inventory', [
                'error' => $e->getMessage()
            ]);

            $this->sendJsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => 500,
                    'message' => 'Internal Server Error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    private function getRequestParams()
    {
        $jsonBody = file_get_contents('php://input');
        $params = json_decode($jsonBody, true);
        return $params['params'] ?? [];
    }
}
