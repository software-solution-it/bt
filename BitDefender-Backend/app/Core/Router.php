<?php

namespace App\Core;

use App\Core\Logger;

class Router
{
    private $routes = [];

    public function addRoute($method, $path, $controller, $action)
    {
        $this->routes[$method][$path] = [
            'controller' => $controller,
            'action' => $action
        ];
    }

    // Keep handleRequest for backward compatibility
    public function handleRequest($method, $path, $params = [])
    {
        try {
            Logger::debug('Router handling request', [
                'method' => $method,
                'path' => $path,
                'params' => $params
            ]);

            // Normalize path
            $path = trim($path, '/');
            
            // Check if route exists
            if (isset($this->routes[$method][$path])) {
                $route = $this->routes[$method][$path];
                $controllerName = "App\\Controllers\\{$route['controller']}";
                $actionName = $route['action'];
                
                $controller = new $controllerName();
                return $controller->$actionName($params);
            }

            throw new \Exception('Route not found');

        } catch (\Exception $e) {
            Logger::error('Router error', [
                'error' => $e->getMessage(),
                'path' => $path,
                'method' => $method
            ]);
            
            return json_encode([
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
    }

    public function route($method, $uri, $params = [])
    {
        try {
            Logger::debug('Router handling request', [
                'method' => $method,
                'path' => $uri,
                'params' => $params
            ]);

            // Parse the URI
            $parts = explode('/', trim($uri, '/'));
            $controller = $parts[0] ?? '';
            
            // Map endpoints to controllers and methods
            switch ($controller) {
                case 'machines':
                    $controllerClass = new \App\Controllers\SyncController();
                    
                    // Handle different endpoints
                    switch ($parts[1] ?? '') {
                        case 'inventory':
                            if ($method === 'POST') {
                                return $controllerClass->getMachineInventory($params);
                            }
                            break;
                    }
                    break;
            }

            throw new \Exception('Route not found');

        } catch (\Exception $e) {
            Logger::error('Router error', [
                'error' => $e->getMessage(),
                'uri' => $uri,
                'method' => $method
            ]);
            
            return json_encode([
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
    }

    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}