<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Core\Logger;

class AuthController { 
    private $authService;

    public function __construct() {
        $this->authService = new AuthService();
    }
 
    public function login($params) {
        try {
            Logger::debug('Login request received', [
                'params' => $params,
                'raw_input' => file_get_contents('php://input')
            ]);

            if (!isset($params['email']) || !isset($params['password'])) {
                Logger::error('Missing credentials', [
                    'email_exists' => isset($params['email']),
                    'password_exists' => isset($params['password'])
                ]);
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'success' => false,
                        'error' => 'Email e senha são obrigatórios'
                    ],
                    'id' => null
                ]);
            }

            $result = $this->authService->authenticate($params['email'], $params['password']);

            if ($result) {
                Logger::info('Login successful', ['email' => $params['email']]);
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'result' => [
                        'success' => true,
                        'data' => [
                            'user' => [
                                'email' => $result['email'],
                                'name' => $result['name']
                            ],
                            'token' => $result['token']
                        ]
                    ],
                    'id' => null
                ]);
            }

            Logger::error('Invalid credentials', ['email' => $params['email']]);
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => [
                    'success' => false,
                    'error' => 'Usuário ou senha inválidos'
                ],
                'id' => null
            ]);

        } catch (\Exception $e) {
            Logger::error('Login failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'email' => $params['email'] ?? 'not provided',
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => [
                    'success' => false,
                    'error' => 'Usuário ou senha inválidos',
                    'code' => $e->getCode()
                ],
                'id' => null
            ]);
        }
    }

    public function logout() {
        try {
            // Log do início do processo de logout
            Logger::info('Iniciando processo de logout');
            
            // Invalida o token atual (você pode manter uma lista negra de tokens)
            $token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION'] ?? '');
            
            if ($token) {
                // Aqui você pode adicionar o token a uma lista negra se necessário
                Logger::info('Token invalidado', ['token' => $token]);
            }
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => [
                    'success' => true,
                    'message' => 'Logout realizado com sucesso'
                ],
                'id' => null
            ]);
            
        } catch (\Exception $e) {
            Logger::error('Logout failed', ['error' => $e->getMessage()]);
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => [
                    'success' => false,
                    'error' => 'Erro ao realizar logout: ' . $e->getMessage()
                ],
                'id' => null
            ]);
        }
    }

    public function testAuth() {
        try {
            $authModel = new \App\Model\AuthModel();
            $result = $authModel->verifyDefaultUserPassword();
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => [
                    'success' => true,
                    'data' => [
                        'password_valid' => $result,
                        'test_user' => $authModel->getUserByEmail('admin@bitdefender.com')
                    ]
                ],
                'id' => null
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage()
                ],
                'id' => null
            ]);
        }
    }

    public function createDefaultUser() {
        try {
            $authModel = new \App\Model\AuthModel();
            $result = $authModel->createDefaultUser();
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => [
                    'success' => true,
                    'data' => [
                        'created' => $result,
                        'user' => $authModel->getUserByEmail('admin@bitdefender.com')
                    ]
                ],
                'id' => null
            ]);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => $e->getMessage()
                ],
                'id' => null
            ]);
        }
    }

    private function jsonResponse($response) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
