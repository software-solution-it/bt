<?php

namespace App\Services;

use App\Model\ApiKeysModel;
use App\Core\Logger;

class ApiKeysService
{
    private $apiKeysModel;

    public function __construct()
    {
        $this->apiKeysModel = new ApiKeysModel();
    }

    public function getAllKeys()
    {
        try {
            Logger::debug('ApiKeysService::getAllKeys called');
            return $this->apiKeysModel->getAllKeys();
        } catch (\Exception $e) {
            Logger::error('Error getting all API keys', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function createKey($data)
    {
        try {
            Logger::debug('ApiKeysService::createKey called', [
                'data' => $data
            ]);

            $this->validateKeyData($data);

            return $this->apiKeysModel->createKey([
                'name' => $data['name'],
                'api_key' => $data['api_key'],
                'is_active' => $data['is_active'] ?? true
            ]);
        } catch (\Exception $e) {
            Logger::error('Error creating API key', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateKey($id, $data)
    {
        try {
            Logger::debug('ApiKeysService::updateKey called', [
                'id' => $id,
                'data' => $data
            ]);

            $this->validateKeyData($data);

            return $this->apiKeysModel->updateKey($id, [
                'name' => $data['name'],
                'api_key' => $data['api_key'],
                'is_active' => $data['is_active'] ?? true
            ]);
        } catch (\Exception $e) {
            Logger::error('Error updating API key', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function deleteKey($id)
    {
        try {
            Logger::debug('ApiKeysService::deleteKey called', [
                'id' => $id
            ]);

            if (empty($id)) {
                throw new \InvalidArgumentException('ID is required');
            }

            return $this->apiKeysModel->deleteKey($id);
        } catch (\Exception $e) {
            Logger::error('Error deleting API key', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function validateKeyData($data)
    {
        if (empty($data['name'])) {
            throw new \InvalidArgumentException('Name is required');
        }

        if (empty($data['api_key'])) {
            throw new \InvalidArgumentException('API key is required');
        }

        if (isset($data['is_active']) && !is_bool($data['is_active'])) {
            throw new \InvalidArgumentException('is_active must be a boolean value');
        }
    }
} 