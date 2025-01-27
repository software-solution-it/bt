<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\AccountService;
use App\Core\Logger;

class AccountController extends Controller
{
    private $accountService;

    public function __construct()
    {
        $this->accountService = new AccountService();
    }

    private function validateRole($role) {
        $validRoles = [1, 2, 3, 5];
        return in_array($role, $validRoles);
    }

    private function validateRights($rights, $role) {
        if ($role !== 5 && !empty($rights)) {
            return false;
        }

        if ($role === 5 && empty($rights)) {
            return false;
        }

        $validRights = ['manageNetworks', 'manageUsers', 'manageReports', 'companyManager'];
        foreach ($rights as $key => $value) {
            if (!in_array($key, $validRights) || !is_bool($value)) {
                return false;
            }
        }
        return true;
    }

    private function validatePassword($password) {
        if (empty($password)) {
            return true; // Password é opcional
        }
        
        return strlen($password) >= 6 
            && preg_match('/[A-Z]/', $password)     // uppercase
            && preg_match('/[a-z]/', $password)     // lowercase
            && preg_match('/[0-9]/', $password)     // number
            && preg_match('/[^A-Za-z0-9]/', $password); // special char
    }

    public function createAccount($params)
    {
        try {
            Logger::debug('AccountController::createAccount called', [
                'params' => $params
            ]);

            // Validações obrigatórias
            if (empty($params['email']) || !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Invalid or missing email address'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            if (empty($params['profile']) || empty($params['profile']['fullName'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Profile fullName is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de senha
            if (!$this->validatePassword($params['password'] ?? null)) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Password must be at least 6 characters and contain uppercase, lowercase, number and special character'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de role
            if (!empty($params['role']) && !$this->validateRole($params['role'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Invalid role. Must be 1 (Company Administrator), 2 (Network Administrator), 3 (Reporter) or 5 (Custom)'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de rights para role 5
            if (!$this->validateRights($params['rights'] ?? [], $params['role'] ?? 1)) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Rights must be specified for role 5 (Custom) and must be valid'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->accountService->createAccount($params);
            
            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 201);

        } catch (\Exception $e) {
            Logger::error('Error in createAccount', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function updateAccount($params)
    {
        try {
            Logger::debug('AccountController::updateAccount called', [
                'params' => $params
            ]);

            if (empty($params['accountId'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Account ID is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de email se fornecido
            if (!empty($params['email']) && !filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Invalid email address'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de senha se fornecida
            if (!empty($params['password']) && !$this->validatePassword($params['password'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Password must be at least 6 characters and contain uppercase, lowercase, number and special character'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de role se fornecida
            if (!empty($params['role']) && !$this->validateRole($params['role'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Invalid role. Must be 1 (Company Administrator), 2 (Network Administrator), 3 (Reporter) or 5 (Custom)'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Validação de rights para role 5
            if (!$this->validateRights($params['rights'] ?? [], $params['role'] ?? null)) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Rights must be specified for role 5 (Custom) and must be valid'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            // Faz o update
            $result = $this->accountService->updateAccount(
                $params['accountId'],
                $params
            );

            if ($result) {
                // Busca os dados atualizados
                $accounts = $this->accountService->getAccounts();
                $updatedAccount = null;
                
                if ($accounts && isset($accounts['items'])) {
                    foreach ($accounts['items'] as $account) {
                        if ($account['id'] === $params['accountId']) {
                            $updatedAccount = $account;
                            break;
                        }
                    }
                }

                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'result' => $updatedAccount,
                    'id' => $params['id'] ?? null
                ], 200);
            }

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Failed to update account',
                    'data' => [
                        'details' => 'Update operation failed'
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);

        } catch (\Exception $e) {
            Logger::error('Error in updateAccount', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function deleteAccount($params)
    {
        try {
            Logger::debug('AccountController::deleteAccount called', [
                'params' => $params
            ]);

            if (empty($params['accountId'])) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Account ID is required'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->accountService->deleteAccount($params['accountId']);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in deleteAccount', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }

    public function getAccountsList($params)
    {
        try {
            Logger::debug('AccountController::getAccountsList called', [
                'params' => $params
            ]);

            // Converte para inteiro e aplica validações
            $page = isset($params['page']) ? (int)$params['page'] : 1;
            $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 30;

            // Validações adicionais
            if ($page < 1) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'Page must be greater than 0'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            if ($perPage < 1 || $perPage > 100) {
                return $this->jsonResponse([
                    'jsonrpc' => '2.0',
                    'error' => [
                        'code' => -32602,
                        'message' => 'Invalid params',
                        'data' => [
                            'details' => 'PerPage must be between 1 and 100'
                        ]
                    ],
                    'id' => $params['id'] ?? null
                ], 400);
            }

            $result = $this->accountService->getAccounts($page, $perPage);
            
            Logger::debug('Accounts list result', [
                'result' => $result
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'result' => $result,
                'id' => $params['id'] ?? null
            ], 200);

        } catch (\Exception $e) {
            Logger::error('Error in getAccountsList', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return $this->jsonResponse([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => -32603,
                    'message' => 'Internal error',
                    'data' => [
                        'details' => $e->getMessage()
                    ]
                ],
                'id' => $params['id'] ?? null
            ], 500);
        }
    }
}