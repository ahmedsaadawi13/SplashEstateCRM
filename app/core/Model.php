<?php
// FILE: /app/core/Model.php

/**
 * SplashEstate CRM - Base Model Class
 * All models extend this class
 */

class Model {
    protected $db;
    protected $conn;
    protected $table;

    /**
     * Constructor - Initialize database connection
     */
    public function __construct() {
        $this->db = new Database();
        $this->conn = $this->db->connect();
    }

    /**
     * Get all records with pagination and tenant filtering
     * @param int $tenantId Tenant ID for filtering
     * @param int $page Current page number
     * @param int $perPage Records per page
     * @param array $filters Additional filters
     * @return array Records
     */
    public function getAll($tenantId, $page = 1, $perPage = 20, $filters = array()) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :tenant_id";

        // Add additional filters
        $params = array(':tenant_id' => $tenantId);

        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                if ($value !== '' && $value !== null) {
                    $sql .= " AND {$field} = :{$field}";
                    $params[":{$field}"] = $value;
                }
            }
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);

        // Bind parameters
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get single record by ID with tenant verification
     * @param int $id Record ID
     * @param int $tenantId Tenant ID
     * @return mixed Record or false
     */
    public function getById($id, $tenantId) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id AND tenant_id = :tenant_id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Create new record
     * @param array $data Record data
     * @return int|bool Last insert ID or false
     */
    public function create($data) {
        $fields = array_keys($data);
        $values = array_values($data);

        $fieldList = implode(', ', $fields);
        $placeholders = ':' . implode(', :', $fields);

        $sql = "INSERT INTO {$this->table} ({$fieldList}) VALUES ({$placeholders})";
        $stmt = $this->conn->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    /**
     * Update record with tenant verification
     * @param int $id Record ID
     * @param int $tenantId Tenant ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function update($id, $tenantId, $data) {
        $fields = array();
        foreach (array_keys($data) as $field) {
            $fields[] = "{$field} = :{$field}";
        }
        $fieldList = implode(', ', $fields);

        $sql = "UPDATE {$this->table} SET {$fieldList} WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Delete record with tenant verification
     * @param int $id Record ID
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function delete($id, $tenantId) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Count total records with tenant filtering
     * @param int $tenantId Tenant ID
     * @param array $filters Additional filters
     * @return int Total count
     */
    public function count($tenantId, $filters = array()) {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        if (!empty($filters)) {
            foreach ($filters as $field => $value) {
                if ($value !== '' && $value !== null) {
                    $sql .= " AND {$field} = :{$field}";
                    $params[":{$field}"] = $value;
                }
            }
        }

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $result = $stmt->fetch();
        return (int)$result['total'];
    }

    /**
     * Search records
     * @param int $tenantId Tenant ID
     * @param string $searchTerm Search term
     * @param array $searchFields Fields to search in
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Records
     */
    public function search($tenantId, $searchTerm, $searchFields = array(), $page = 1, $perPage = 20) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT * FROM {$this->table} WHERE tenant_id = :tenant_id";

        if (!empty($searchTerm) && !empty($searchFields)) {
            $searchConditions = array();
            foreach ($searchFields as $field) {
                $searchConditions[] = "{$field} LIKE :search";
            }
            $sql .= " AND (" . implode(' OR ', $searchConditions) . ")";
        }

        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);

        if (!empty($searchTerm) && !empty($searchFields)) {
            $stmt->bindValue(':search', '%' . $searchTerm . '%');
        }

        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }
}
