<?php

namespace App\Services;

use App\Model\AuthModel;
use Firebase\JWT\JWT;
use App\Core\Logger;

class AuthService {
    private $authModel;
    private $secretKey;

    public function __construct() {
        $this->authModel = new AuthModel();
        $this->secretKey = getenv('JWT_SECRET_KEY') ?: 'your-secret-key-here';
    }

    public function getAuthModel() {
        return $this->authModel;
    }

    public function authenticate($email, $password) {
        try {
            Logger::debug('Starting authentication', [
                'email' => $email
            ]);

            $user = $this->authModel->getUserByEmail($email);

            Logger::debug('User found', [
                'user_exists' => (bool)$user
            ]);

            if (!$user) {
                throw new \Exception('Usuário ou senha inválidos', 401);
            }

            Logger::debug('Verifying password', [
                'provided_password' => $password,
                'stored_hash' => $user['password']
            ]);

            if (!password_verify($password, $user['password'])) {
                throw new \Exception('Usuário ou senha inválidos', 401);
            }

            // Gerar token JWT
            $token = $this->generateToken($user);

            return [
                'email' => $user['email'],
                'name' => $user['name'],
                'token' => $token
            ];

        } catch (\Exception $e) {
            Logger::error('Authentication failed', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            throw $e;
        }
    }

    private function generateToken($user) {
        $issuedAt = time();
        $expire = $issuedAt + (60 * 60 * 24); // 24 horas

        $payload = [
            'iat' => $issuedAt,
            'exp' => $expire,
            'user' => [
                'id' => $user['id'],
                'email' => $user['email']
            ]
        ];

        return JWT::encode($payload, $this->secretKey, 'HS256');
    }
}
