<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\Logger;
use App\Core\ExceptionHandler;
use App\Config\Environment;

Environment::load(__DIR__ . '/.env');

ExceptionHandler::register();

try {
    // Get the request URI and method
    $uri = $_SERVER['REQUEST_URI'];
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Remove query string and decode URI
    $uri = strtok($uri, '?');
    $uri = urldecode($uri);
    
    // Get JSON body for POST requests
    $body = file_get_contents('php://input');
    $params = [];
    
    if (!empty($body)) {
        $decoded = json_decode($body, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $params = $decoded['params'] ?? [];
        }
    }

    Logger::debug('Request received', [
        'uri' => $uri,
        'method' => $method,
        'input' => $body
    ]);

    // Initialize router
    $router = new Router();
    
    // Add routes
    $router->addRoute('POST', 'machines/inventory', 'SyncController', 'getMachineInventory');
    
    // Handle the request using the registered routes
    $response = $router->handleRequest($method, $uri, $params);
    
    // Output response
    header('Content-Type: application/json');
    echo $response;

} catch (Exception $e) {
    Logger::error('Application error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'jsonrpc' => '2.0',
        'error' => [
            'code' => -32603,
            'message' => 'Internal error',
            'data' => [
                'details' => $e->getMessage()
            ]
        ]
    ]);
}