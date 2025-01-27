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

    public function handleRequest($method, $path, $params = [])
    {
        try {
            Logger::debug('Router handling request', [
                'method' => $method,
                'path' => $path,
                'params' => $params
            ]);

            if (!isset($this->routes[$method][$path])) {
                throw new \Exception('Route not found');
            }

            $route = $this->routes[$method][$path];
            $controllerName = "App\\Controllers\\{$route['controller']}";
            $actionName = $route['action'];

            if (!class_exists($controllerName)) {
                throw new \Exception("Controller {$controllerName} not found");
            }

            $controller = new $controllerName();
            if (!method_exists($controller, $actionName)) {
                throw new \Exception("Action {$actionName} not found in controller {$controllerName}");
            }

            $response = $controller->$actionName($params);

            // Se a resposta já for uma string JSON, retorna diretamente
            if (is_string($response) && $this->isJson($response)) {
                return $response;
            }

            // Caso contrário, formata a resposta no padrão JSON-RPC
            return json_encode([
                'jsonrpc' => '2.0',
                'result' => $response,
                'id' => $params['id'] ?? null
            ]);

        } catch (\Exception $e) {
            Logger::error('Router error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32601,
                    'message' => $e->getMessage()
                ],
                'id' => $params['id'] ?? null
            ]);
        }
    }

    private function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }
}