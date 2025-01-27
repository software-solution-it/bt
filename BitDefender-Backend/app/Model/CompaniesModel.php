<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class CompaniesModel extends Model
{
    protected $table = 'companies';
    protected $primaryKey = 'id';

    public function __construct()
    {
        parent::__construct();
        $this->createTableIfNotExists();
    }

    private function createTableIfNotExists()
    {
        $sql = "CREATE TABLE IF NOT EXISTS {$this->table} (
            id VARCHAR(24) PRIMARY KEY,
            api_key_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            address TEXT NULL,
            phone VARCHAR(50) NULL,
            country VARCHAR(2) NULL,
            state VARCHAR(100) NULL,
            city VARCHAR(100) NULL,
            postal_code VARCHAR(20) NULL,
            timezone VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
        )";

        try {
            $this->db->exec($sql);
        } catch (\PDOException $e) {
            Logger::error('Failed to create companies table', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncWithAPI($apiData, $apiKeyId)
    {
        try {
            $this->db->beginTransaction();
 
            $companyData = [
                'id' => $apiData['id'] ?? '1',
                'api_key_id' => $apiKeyId,
                'name' => $apiData['name'],
                'address' => $apiData['address'] ?? null,
                'phone' => $apiData['phone'] ?? null,
                'country' => $apiData['country'] ?? null,
                'state' => $apiData['state'] ?? null,
                'city' => $apiData['city'] ?? null,
                'postal_code' => $apiData['postalCode'] ?? null,
                'timezone' => $apiData['timezone'] ?? null
            ];

            $existingCompany = $this->find($companyData['id']);

            if ($existingCompany) {
                $this->update($companyData['id'], $companyData);
            } else {
                $this->create($companyData);
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync company with API', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getCompanyDetails()
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} LIMIT 1");
            $stmt->execute();
            return $stmt->fetch();
        } catch (\PDOException $e) {
            Logger::error('Failed to get company details', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateCompanyDetails(array $data)
    {
        try {
            $company = $this->getCompanyDetails();
            if (!$company) {
                // Se não existir, cria um novo registro
                return $this->create(array_merge(['id' => '1'], $data));
            }
            // Se existir, atualiza
            return $this->update($company['id'], $data);
        } catch (\PDOException $e) {
            Logger::error('Failed to update company details', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredCompanies($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['name'])) {
                $query .= " AND name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['country'])) {
                $query .= " AND country = ?";
                $params[] = $filters['country'];
            }

            if (!empty($filters['created_after'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['created_after'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered companies', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function findByApiKeyId($apiKeyId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE api_key_id = ?");
            $stmt->execute([$apiKeyId]);
            return $stmt->fetchAll();
        } catch (\PDOException $e) {
            Logger::error('Failed to find companies by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
