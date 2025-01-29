<?php

namespace App\Core;

use PDO;
use App\Core\Database;
use App\Core\Logger;

class Model
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct()
    {
        try {
            $this->db = new PDO(
                "mysql:host=" . getenv('DB_HOST') . 
                ";dbname=" . getenv('DB_DATABASE') . 
                ";port=" . getenv('DB_PORT'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD'),
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (\PDOException $e) {
            Logger::error('Database connection failed', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Database connection failed', 500);
        }
    }

    public function find($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id");
            $stmt->execute(['id' => $id]);
            return $stmt->fetch();
        } catch (\PDOException $e) {
            Logger::error("Error finding record in {$this->table}", [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function findBy(array $criteria)
    {
        $where = [];
        $values = [];
        foreach ($criteria as $key => $value) {
            $where[] = "$key = ?";
            $values[] = $value;
        }
        $whereClause = implode(' AND ', $where);
        
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE $whereClause");
        $stmt->execute($values);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $data)
    {
        $fields = implode(', ', array_keys($data));
        $values = implode(', ', array_fill(0, count($data), '?'));
        
        $stmt = $this->db->prepare("INSERT INTO {$this->table} ($fields) VALUES ($values)");
        $stmt->execute(array_values($data));
        
        return $this->db->lastInsertId();
    }

    public function update($id, array $data)
    {
        $fields = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
        }
        $fieldsStr = implode(', ', $fields);
        
        $values = array_values($data);
        $values[] = $id;
        
        $stmt = $this->db->prepare("UPDATE {$this->table} SET $fieldsStr WHERE {$this->primaryKey} = ?");
        return $stmt->execute($values);
    }

    public function delete($id)
    {
        $stmt = $this->db->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }
} 