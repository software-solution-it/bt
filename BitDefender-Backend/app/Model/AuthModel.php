<?php

namespace App\Model;

use App\Core\Logger;
use App\Core\Model;

class AuthModel extends Model {
    protected $table = 'users';
    protected $primaryKey = 'id';

    public function __construct() {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists() {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            Logger::error('Failed to create users table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getUserByEmail($email) {
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $params = [':email' => $email];

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            Logger::debug('getUserByEmail result', [
                'email' => $email,
                'found' => (bool)$stmt->rowCount(),
                'user' => $user
            ]);

            return $user;

        } catch (\PDOException $e) {
            Logger::error('Database error in getUserByEmail', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            throw new \Exception('Erro ao buscar usuário', 500);
        }
    }

    public function createUser($userData) {
        try {
            $query = "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)";
            $params = [
                ':name' => $userData['name'],
                ':email' => $userData['email'],
                ':password' => password_hash($userData['password'], PASSWORD_DEFAULT)
            ];

            $stmt = $this->db->prepare($query);
            return $stmt->execute($params);

        } catch (\PDOException $e) {
            Logger::error('Database error in createUser', [
                'error' => $e->getMessage(),
                'email' => $userData['email']
            ]);
            throw new \Exception('Erro ao criar usuário', 500);
        }
    }

    public function createDefaultUser() {
        try {
            // Verifica se o usuário já existe
            $existingUser = $this->getUserByEmail('admin@bitdefender.com');
            Logger::debug('Checking existing user', [
                'exists' => (bool)$existingUser,
                'user' => $existingUser
            ]);

            if ($existingUser) {
                // Atualiza a senha do usuário existente
                $query = "UPDATE users SET password = :password WHERE email = :email";
                $params = [
                    ':email' => 'admin@bitdefender.com',
                    ':password' => password_hash('admin123', PASSWORD_DEFAULT)
                ];
                
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute($params);
                
                $updatedUser = $this->getUserByEmail('admin@bitdefender.com');
                Logger::debug('Default user password updated', [
                    'success' => $result,
                    'user' => $updatedUser,
                    'password_valid' => password_verify('admin123', $updatedUser['password'])
                ]);
                
                return $result;
            }

            // Cria usuário padrão
            $result = $this->createUser([
                'name' => 'Administrador',
                'email' => 'admin@bitdefender.com',
                'password' => 'admin123'
            ]);

            Logger::debug('Default user creation result', [
                'success' => $result,
                'user' => $this->getUserByEmail('admin@bitdefender.com')
            ]);

            return $result;

        } catch (\PDOException $e) {
            Logger::error('Error creating/updating default user', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Erro ao criar/atualizar usuário padrão', 500);
        }
    }

    public function verifyDefaultUserPassword() {
        try {
            $user = $this->getUserByEmail('admin@bitdefender.com');
            if (!$user) {
                Logger::error('Default user not found');
                return false;
            }

            Logger::debug('Default user password verification', [
                'stored_hash' => $user['password'],
                'test_hash' => password_hash('admin123', PASSWORD_DEFAULT),
                'verification_result' => password_verify('admin123', $user['password'])
            ]);

            return password_verify('admin123', $user['password']);
        } catch (\Exception $e) {
            Logger::error('Password verification failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
