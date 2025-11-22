<?php
// FILE: /app/models/Lead.php

/**
 * SplashEstate CRM - Lead Model
 * Handles lead/prospect management
 */

class Lead extends Model {

    protected $table = 'leads';

    /**
     * Get leads with assigned user info
     * @param int $tenantId Tenant ID
     * @param int $page Page number
     * @param int $perPage Records per page
     * @param array $filters Filters
     * @return array Leads with user info
     */
    public function getLeadsWithUser($tenantId, $page = 1, $perPage = 20, $filters = array()) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT l.*,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
                FROM {$this->table} l
                LEFT JOIN users u ON l.assigned_to = u.id
                WHERE l.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND l.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND l.assigned_to = :assigned_to";
            $params[':assigned_to'] = $filters['assigned_to'];
        }

        if (!empty($filters['source'])) {
            $sql .= " AND l.source = :source";
            $params[':source'] = $filters['source'];
        }

        if (!empty($filters['interest_type'])) {
            $sql .= " AND l.interest_type = :interest_type";
            $params[':interest_type'] = $filters['interest_type'];
        }

        // Search
        if (!empty($filters['search'])) {
            $sql .= " AND (l.first_name LIKE :search OR l.last_name LIKE :search OR l.email LIKE :search OR l.phone LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get lead by ID with user info
     * @param int $id Lead ID
     * @param int $tenantId Tenant ID
     * @return mixed Lead data or false
     */
    public function getLeadWithUser($id, $tenantId) {
        $sql = "SELECT l.*,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
                u.email as assigned_to_email
                FROM {$this->table} l
                LEFT JOIN users u ON l.assigned_to = u.id
                WHERE l.id = :id AND l.tenant_id = :tenant_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Create lead
     * @param array $data Lead data
     * @return int|bool Lead ID or false
     */
    public function createLead($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, assigned_to, first_name, last_name, email, phone, source, status, interest_type, budget_min, budget_max, notes, created_at)
                VALUES (:tenant_id, :assigned_to, :first_name, :last_name, :email, :phone, :source, :status, :interest_type, :budget_min, :budget_max, :notes, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':assigned_to', isset($data['assigned_to']) ? $data['assigned_to'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', isset($data['last_name']) ? $data['last_name'] : null);
        $stmt->bindValue(':email', isset($data['email']) ? $data['email'] : null);
        $stmt->bindValue(':phone', isset($data['phone']) ? $data['phone'] : null);
        $stmt->bindValue(':source', isset($data['source']) ? $data['source'] : 'Website');
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'new');
        $stmt->bindValue(':interest_type', isset($data['interest_type']) ? $data['interest_type'] : null);
        $stmt->bindValue(':budget_min', isset($data['budget_min']) ? $data['budget_min'] : null);
        $stmt->bindValue(':budget_max', isset($data['budget_max']) ? $data['budget_max'] : null);
        $stmt->bindValue(':notes', isset($data['notes']) ? $data['notes'] : null);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Update lead status
     * @param int $id Lead ID
     * @param int $tenantId Tenant ID
     * @param string $status New status
     * @return bool Success status
     */
    public function updateStatus($id, $tenantId, $status) {
        $sql = "UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Convert lead to client
     * @param int $id Lead ID
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function convertToClient($id, $tenantId) {
        $lead = $this->getById($id, $tenantId);

        if (!$lead) {
            return false;
        }

        // Create client
        $clientModel = new Client();
        $clientData = array(
            'tenant_id' => $tenantId,
            'lead_id' => $id,
            'first_name' => $lead['first_name'],
            'last_name' => $lead['last_name'],
            'email' => $lead['email'],
            'phone' => $lead['phone'],
            'client_type' => $lead['interest_type'] === 'buy' ? 'buyer' : 'seller',
            'status' => 'active',
            'notes' => $lead['notes']
        );

        $clientId = $clientModel->create($clientData);

        if ($clientId) {
            // Update lead status
            $this->updateStatus($id, $tenantId, 'won');
            return $clientId;
        }

        return false;
    }

    /**
     * Get lead sources
     * @param int $tenantId Tenant ID
     * @return array Sources
     */
    public function getSources($tenantId) {
        $sql = "SELECT DISTINCT source FROM {$this->table} WHERE tenant_id = :tenant_id AND source IS NOT NULL ORDER BY source";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
