<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class PackagesModel extends Model
{
    protected $table = 'packages';
    protected $primaryKey = 'package_name';

    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    public function savePackage($basicInfo, $details)
    {
        try {
            $this->db->beginTransaction();

            // Log para debug
            Logger::debug('Attempting to save package', [
                'package_id' => $basicInfo['id'],
                'name' => $basicInfo['name']
            ]);

            $sql = "INSERT INTO packages (
                package_id, api_key_id, name, type, description,
                language, modules, roles, scan_mode, settings,
                deployment_options, product_type, package_name
            ) VALUES (
                :package_id, :api_key_id, :name, :type, :description,
                :language, :modules, :roles, :scan_mode, :settings,
                :deployment_options, :product_type, :package_name
            ) ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                type = VALUES(type),
                description = VALUES(description),
                language = VALUES(language),
                modules = VALUES(modules),
                roles = VALUES(roles),
                scan_mode = VALUES(scan_mode),
                settings = VALUES(settings),
                deployment_options = VALUES(deployment_options),
                product_type = VALUES(product_type),
                package_name = VALUES(package_name)";

            $stmt = $this->db->prepare($sql);
            
            $stmt->execute([
                ':package_id' => $basicInfo['id'],
                ':api_key_id' => $basicInfo['api_key_id'],
                ':name' => $basicInfo['name'],
                ':type' => $basicInfo['type'],
                ':description' => $details['description'] ?? null,
                ':language' => $details['language'] ?? null,
                ':modules' => isset($details['modules']) ? json_encode($details['modules']) : null,
                ':roles' => isset($details['roles']) ? json_encode($details['roles']) : null,
                ':scan_mode' => isset($details['scanMode']) ? json_encode($details['scanMode']) : null,
                ':settings' => isset($details['settings']) ? json_encode($details['settings']) : null,
                ':deployment_options' => isset($details['deploymentOptions']) ? json_encode($details['deploymentOptions']) : null,
                ':product_type' => $details['productType'] ?? null,
                ':package_name' => $basicInfo['name']
            ]);

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to save package', [
                'error' => $e->getMessage(),
                'package_id' => $basicInfo['id'] ?? null
            ]);
            throw $e;
        }
    }

    private function createTables()
    {
        try {
            // Tabela principal de pacotes
            $this->db->exec("CREATE TABLE IF NOT EXISTS packages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                package_id VARCHAR(24) NOT NULL,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                package_name VARCHAR(255) NOT NULL,
                type INT NOT NULL,
                description TEXT NULL,
                language VARCHAR(10) NULL,
                modules JSON NULL,
                roles JSON NULL,
                scan_mode JSON NULL,
                settings JSON NULL,
                deployment_options JSON NULL,
                product_type INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_package_id (package_id),
                INDEX idx_api_key (api_key_id),
                INDEX idx_name (name),
                UNIQUE KEY uk_package_id_api_key (package_id, api_key_id)
            )");

            // Tabela de links de instalação
            $this->db->exec("CREATE TABLE IF NOT EXISTS installation_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                package_name VARCHAR(255) NOT NULL,
                api_key_id INT NOT NULL,
                company_id VARCHAR(24) NULL,
                company_name VARCHAR(255) NULL,
                link_type VARCHAR(50) NOT NULL,
                value TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_package (package_name),
                INDEX idx_api_key (api_key_id),
                INDEX idx_company (company_id),
                UNIQUE KEY uk_package_link_type (package_name, api_key_id, link_type)
            )");

        } catch (\PDOException $e) {
            Logger::error('Failed to create packages tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function updateTableStructure()
    {
        try {
            // Atualiza a estrutura da tabela installation_links
            $this->db->exec("
                ALTER TABLE installation_links 
                DROP COLUMN IF EXISTS os_type,
                CHANGE COLUMN IF EXISTS url value TEXT NOT NULL
            ");

            Logger::debug('Installation links table structure updated');
        } catch (\PDOException $e) {
            Logger::error('Failed to update table structure', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncPackages($result)
    {
        try {
            if (!isset($result['items']) || empty($result['items'])) {
                Logger::debug('No packages to sync');
                return true;
            }
    
            $this->db->beginTransaction();
    
            foreach ($result['items'] as $package) {
                if (!isset($package['package_id']) || !isset($package['name'])) {
                    Logger::error('Missing required fields', ['package' => $package]);
                    continue;
                }
    
                try {
                    // Verifica se o pacote já existe
                    $stmt = $this->db->prepare("SELECT 1 FROM packages WHERE package_id = ?");
                    $stmt->execute([$package['package_id']]);
    
                    if ($stmt->fetch()) {
                        // Update
                        $stmt = $this->db->prepare(
                            "UPDATE packages 
                            SET name = ?, type = ? 
                            WHERE package_id = ?"
                        );
                        $stmt->execute([
                            $package['name'],
                            $package['type'] ?? 0,
                            $package['package_id']
                        ]);
                        Logger::debug('Updated package', ['package_id' => $package['package_id']]);
                    } else {
                        // Insert
                        $stmt = $this->db->prepare(
                            "INSERT INTO packages 
                            (package_id, name, type) 
                            VALUES (?, ?, ?)"
                        );
                        $stmt->execute([
                            $package['package_id'],
                            $package['name'],
                            $package['type'] ?? 0
                        ]);
                        Logger::debug('Created new package', ['package_id' => $package['package_id']]);
                    }
                } catch (\Exception $e) {
                    Logger::error('Failed to process package', [
                        'package_id' => $package['package_id'],
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }
    
            $this->db->commit();
            Logger::debug('Packages sync completed');
            return true;
    
        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync packages', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
 
    public function syncInstallationLinks($links)
    {
        try {
            if (empty($links)) {
                Logger::debug('No installation links to sync');
                return true;
            }

            $this->db->beginTransaction();

            foreach ($links as $link) {
                // Mapeia cada tipo de link para uma entrada separada
                $linkTypes = [
                    ['installLinkWindows', 'windows'],
                    ['installLinkMac', 'mac'],
                    ['installLinkMacArm', 'mac_arm'],
                    ['installLinkMacDownloader', 'mac_downloader'],
                    ['installLinkLinux', 'linux'],
                    ['fullKitWindowsX32', 'windows_x32_full'],
                    ['fullKitWindowsX64', 'windows_x64_full'],
                    ['fullKitWindowsArm64', 'windows_arm64_full'],
                    ['fullKitLinuxX32', 'linux_x32_full'],
                    ['fullKitLinuxX64', 'linux_x64_full'],
                    ['fullKitLinuxArm64', 'linux_arm64_full']
                ];

                foreach ($linkTypes as [$key, $type]) {
                    if (!empty($link[$key])) {
                        $sql = "INSERT INTO installation_links (
                            package_name, api_key_id, company_id, company_name, 
                            link_type, value
                        ) VALUES (
                            :package_name, :api_key_id, :company_id, :company_name,
                            :link_type, :value
                        ) ON DUPLICATE KEY UPDATE 
                            value = VALUES(value),
                            company_name = VALUES(company_name)";

                        $stmt = $this->db->prepare($sql);
                        $stmt->execute([
                            ':package_name' => $link['packageName'],
                            ':api_key_id' => $link['api_key_id'] ?? null,
                            ':company_id' => $link['companyId'] ?? null,
                            ':company_name' => $link['companyName'] ?? null,
                            ':link_type' => $type,
                            ':value' => $link[$key]
                        ]);
                    }
                }
            }

            $this->db->commit();
            Logger::debug('Installation links synced successfully');
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync installation links', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getPackageDetails($packageId, $apiKeyId = null)
    {
        try {
            $sql = "SELECT * FROM packages WHERE package_id = ?";
            $params = [$packageId];

            if ($apiKeyId !== null) {
                $sql .= " AND api_key_id = ?";
                $params[] = $apiKeyId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $package = $stmt->fetch();
            
            if (!$package) {
                return null;
            }

            // Decodifica os campos JSON
            return [
                'package_id' => $package['package_id'],
                'api_key_id' => $package['api_key_id'],
                'name' => $package['name'],
                'package_name' => $package['package_name'],
                'type' => $package['type'],
                'description' => $package['description'],
                'language' => $package['language'],
                'modules' => json_decode($package['modules'], true),
                'roles' => json_decode($package['roles'], true),
                'scanMode' => json_decode($package['scan_mode'], true),
                'settings' => json_decode($package['settings'], true),
                'deploymentOptions' => json_decode($package['deployment_options'], true),
                'productType' => $package['product_type']
            ];

        } catch (\Exception $e) {
            Logger::error('Failed to get package details', [
                'error' => $e->getMessage(),
                'package_id' => $packageId,
                'api_key_id' => $apiKeyId
            ]);
            throw $e;
        }
    }

    public function deletePackage($packageId)
    {
        try {
            return $this->update($packageId, ['status' => 'deleted']);
        } catch (\Exception $e) {
            Logger::error('Failed to delete package', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getInstallationLinks($packageName = null)
    {
        try {
            $sql = "SELECT il.* FROM installation_links il";
            $params = [];

            if ($packageName) {
                $sql .= " WHERE il.package_name = ?";
                $params[] = $packageName;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            $links = $stmt->fetchAll();
            $formattedLinks = [];
            
            foreach ($links as $link) {
                $formattedLinks[$link['link_type']] = $link['value'];
            }

            return $formattedLinks;
        } catch (\Exception $e) {
            Logger::error('Failed to get installation links', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncPackageDetails($basicInfo, $details)
    {
        try {
            if (!isset($basicInfo['package_id'])) {
                Logger::error('Missing package ID', ['package' => $basicInfo]);
                return false;
            }

            $this->db->beginTransaction();

            $packageData = [
                'package_id' => $basicInfo['package_id'],
                'api_key_id' => $basicInfo['api_key_id'],
                'name' => $basicInfo['name'],
                'package_name' => $details['packageName'] ?? $basicInfo['name'],
                'type' => $basicInfo['type'] ?? 0,
                'description' => $details['description'] ?? null,
                'language' => $details['language'] ?? 'en_US',
                'modules' => isset($details['modules']) ? json_encode($details['modules']) : null,
                'roles' => isset($details['roles']) ? json_encode($details['roles']) : null,
                'scan_mode' => isset($details['scanMode']) ? json_encode($details['scanMode']) : null,
                'settings' => isset($details['settings']) ? json_encode($details['settings']) : null,
                'deployment_options' => isset($details['deploymentOptions']) ? json_encode($details['deploymentOptions']) : null,
                'product_type' => $details['productType'] ?? 0
            ];

            // Verifica se o pacote já existe
            $stmt = $this->db->prepare("SELECT 1 FROM packages WHERE package_id = ? AND api_key_id = ?");
            $stmt->execute([$packageData['package_id'], $packageData['api_key_id']]);

            if ($stmt->fetch()) {
                // Update
                $sets = [];
                $values = [];
                foreach ($packageData as $key => $value) {
                    if ($key !== 'package_id' && $key !== 'api_key_id') {
                        $sets[] = "$key = ?";
                        $values[] = $value;
                    }
                }
                // Adiciona os valores para a cláusula WHERE
                $values[] = $packageData['package_id'];
                $values[] = $packageData['api_key_id'];

                $sql = "UPDATE packages SET " . implode(', ', $sets) . " WHERE package_id = ? AND api_key_id = ?";
                $stmt = $this->db->prepare($sql);
                $stmt->execute($values);
                
                Logger::debug('Updated package', [
                    'package_id' => $packageData['package_id'],
                    'api_key_id' => $packageData['api_key_id']
                ]);
            } else {
                // Insert
                $fields = implode(', ', array_keys($packageData));
                $placeholders = implode(', ', array_fill(0, count($packageData), '?'));
                
                $sql = "INSERT INTO packages ($fields) VALUES ($placeholders)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array_values($packageData));
                
                Logger::debug('Created new package', [
                    'package_id' => $packageData['package_id'],
                    'api_key_id' => $packageData['api_key_id']
                ]);
            }

            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync package details', [
                'error' => $e->getMessage(),
                'package_id' => $packageData['package_id'] ?? null,
                'api_key_id' => $packageData['api_key_id'] ?? null
            ]);
            throw $e;
        }
    }

    public function getFilteredPackages($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['name'])) {
                $query .= " AND name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['type'])) {
                $query .= " AND type = ?";
                $params[] = $filters['type'];
            }

            if (!empty($filters['created_after'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['created_after'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered packages', [
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
            $results = $stmt->fetchAll();

            // Decodifica os campos JSON para cada resultado
            foreach ($results as &$result) {
                $result['modules'] = json_decode($result['modules'], true);
                $result['roles'] = json_decode($result['roles'], true);
                $result['scan_mode'] = json_decode($result['scan_mode'], true);
                $result['settings'] = json_decode($result['settings'], true);
                $result['deployment_options'] = json_decode($result['deployment_options'], true);
            }

            return $results;
        } catch (\PDOException $e) {
            Logger::error('Failed to find packages by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
