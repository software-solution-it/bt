<?php

namespace App\Core;

use App\Config\Database;
use PDO;

abstract class Model
{
    protected $db;
    protected $table;
    protected $primaryKey = 'id';

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function find($id)
    {
        $stmt = $this->db->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
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