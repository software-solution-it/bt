<?php

namespace App\Model;

use App\Core\Model;
use App\Core\Logger;

class ReportsModel extends Model
{
    protected $table = 'reports';
    protected $primaryKey = 'report_id';

    public function __construct()
    {
        parent::__construct();
        $this->createTables();
    }

    private function createTables()
    {
        try {
            // Tabela principal de relatórios
            $this->db->exec("CREATE TABLE IF NOT EXISTS reports (
                report_id VARCHAR(24) PRIMARY KEY,
                api_key_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                type VARCHAR(50) NOT NULL,
                format VARCHAR(20) NOT NULL,
                status VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                parameters JSON NULL,
                file_size BIGINT NULL,
                download_url TEXT NULL,
                expires_at TIMESTAMP NULL,
                INDEX idx_api_key (api_key_id),
                INDEX idx_status (status),
                INDEX idx_type (type),
                INDEX idx_created (created_at),
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

            // Verifica se a coluna api_key_id existe
            $columns = $this->db->query("SHOW COLUMNS FROM reports LIKE 'api_key_id'")->fetchAll();
            if (empty($columns)) {
                // Adiciona a coluna api_key_id se não existir
                $this->db->exec("ALTER TABLE reports 
                    ADD COLUMN api_key_id INT NOT NULL AFTER report_id,
                    ADD INDEX idx_api_key (api_key_id),
                    ADD FOREIGN KEY (api_key_id) REFERENCES api_keys(id)");
            }

            // Tabela de histórico de downloads
            $this->db->exec("CREATE TABLE IF NOT EXISTS report_downloads (
                id INT AUTO_INCREMENT PRIMARY KEY,
                report_id VARCHAR(24) NOT NULL,
                api_key_id INT NOT NULL,
                downloaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                INDEX idx_api_key (api_key_id),
                FOREIGN KEY (report_id) REFERENCES reports(report_id) ON DELETE CASCADE,
                FOREIGN KEY (api_key_id) REFERENCES api_keys(id)
            )");

        } catch (\PDOException $e) {
            Logger::error('Failed to create reports tables', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function syncReports($reports)
    {
        try {
            $this->db->beginTransaction();

            if (!isset($reports['api_key_id'])) {
                throw new \Exception('API Key ID is required for syncing reports');
            }

            foreach ($reports['items'] ?? [] as $report) {
                $reportData = [
                    'name' => $report['name'] ?? 'Untitled Report',
                    'type' => $report['type'] ?? 'unknown',
                    'format' => $report['format'] ?? 'pdf',
                    'status' => $report['status'] ?? 'pending',
                    'api_key_id' => $reports['api_key_id'],
                    'completed_at' => $report['completedAt'] ?? null,
                    'parameters' => json_encode($report['parameters'] ?? null),
                    'file_size' => $report['fileSize'] ?? null,
                    'download_url' => $report['downloadUrl'] ?? null,
                    'expires_at' => $report['expiresAt'] ?? null
                ];

                if (!empty($report['reportId'])) {
                    $existing = $this->find($report['reportId']);
                    if ($existing) {
                        $this->update($report['reportId'], $reportData);
                    } else {
                        $reportData['report_id'] = $report['reportId'];
                        $this->create($reportData);
                    }
                }
            }

            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            Logger::error('Failed to sync reports', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function logDownload($reportId, $ipAddress = null, $userAgent = null)
    {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO report_downloads 
                (report_id, ip_address, user_agent) 
                VALUES (?, ?, ?)"
            );

            return $stmt->execute([
                $reportId,
                $ipAddress,
                $userAgent
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to log report download', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getReportDetails($reportId)
    {
        try {
            $report = $this->find($reportId);
            if (!$report) {
                return null;
            }

            return [
                'reportId' => $report['report_id'],
                'name' => $report['name'],
                'type' => $report['type'],
                'format' => $report['format'],
                'status' => $report['status'],
                'createdAt' => $report['created_at'],
                'completedAt' => $report['completed_at'],
                'parameters' => json_decode($report['parameters'], true),
                'fileSize' => $report['file_size'],
                'downloadUrl' => $report['download_url'],
                'expiresAt' => $report['expires_at']
            ];
        } catch (\Exception $e) {
            Logger::error('Failed to get report details', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function markAsDeleted($reportId)
    {
        try {
            return $this->update($reportId, ['status' => 'deleted']);
        } catch (\Exception $e) {
            Logger::error('Failed to mark report as deleted', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function updateDownloadLinks($reportId, $downloadUrl, $expiresAt)
    {
        try {
            return $this->update($reportId, [
                'download_url' => $downloadUrl,
                'expires_at' => $expiresAt
            ]);
        } catch (\Exception $e) {
            Logger::error('Failed to update download links', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function getFilteredReports($filters)
    {
        try {
            $query = "SELECT * FROM {$this->table} WHERE 1=1";
            $params = [];

            if (!empty($filters['api_key_id'])) {
                $query .= " AND api_key_id = ?";
                $params[] = $filters['api_key_id'];
            }

            if (!empty($filters['name'])) {
                $query .= " AND name LIKE ?";
                $params[] = '%' . $filters['name'] . '%';
            }

            if (!empty($filters['type'])) {
                $query .= " AND type = ?";
                $params[] = $filters['type'];
            }

            if (!empty($filters['status'])) {
                $query .= " AND status = ?";
                $params[] = $filters['status'];
            }

            if (!empty($filters['created_after'])) {
                $query .= " AND created_at >= ?";
                $params[] = $filters['created_after'];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            return $stmt->fetchAll();

        } catch (\PDOException $e) {
            Logger::error('Failed to get filtered reports', [
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
                $result['parameters'] = json_decode($result['parameters'], true);
            }

            return $results;
        } catch (\PDOException $e) {
            Logger::error('Failed to find reports by API key ID', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}
