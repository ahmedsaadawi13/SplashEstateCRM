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
     * Advanced search for leads using SearchHelper
     * @param int $tenantId Tenant ID
     * @param array $filters Advanced filter parameters
     * @param int $page Page number
     * @param int $perPage Records per page
     * @return array Leads with user info
     */
    public function advancedSearch($tenantId, $filters = array(), $page = 1, $perPage = 20) {
        require_once APP_PATH . '/helpers/SearchHelper.php';
        $search = new SearchHelper();

        // Base query
        $baseQuery = "SELECT l.*,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
                FROM {$this->table} l
                LEFT JOIN users u ON l.assigned_to = u.id";

        // Apply filters using SearchHelper
        if (!empty($filters['status'])) {
            $search->where('l.status', $filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            $search->where('l.assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['source'])) {
            $search->where('l.source', $filters['source']);
        }

        if (!empty($filters['interest_type'])) {
            $search->where('l.interest_type', $filters['interest_type']);
        }

        // Budget range filter
        if (!empty($filters['budget_min']) || !empty($filters['budget_max'])) {
            $search->numericRange('l.budget_min',
                isset($filters['budget_min']) ? $filters['budget_min'] : null,
                isset($filters['budget_max']) ? $filters['budget_max'] : null
            );
        }

        // Date range filter
        if (!empty($filters['created_from']) || !empty($filters['created_to'])) {
            $search->dateRange('l.created_at',
                isset($filters['created_from']) ? $filters['created_from'] : null,
                isset($filters['created_to']) ? $filters['created_to'] : null
            );
        }

        // Full-text search across multiple fields
        if (!empty($filters['search'])) {
            $search->search(
                array('l.first_name', 'l.last_name', 'l.email', 'l.phone', 'l.notes'),
                $filters['search']
            );
        }

        // Sorting
        $sortField = !empty($filters['sort']) ? $filters['sort'] : 'l.created_at';
        $sortDir = !empty($filters['direction']) && strtoupper($filters['direction']) === 'ASC' ? 'ASC' : 'DESC';
        $search->orderBy($sortField, $sortDir);

        // Pagination
        $offset = ($page - 1) * $perPage;
        $search->limit($perPage, $offset);

        // Execute query
        return $search->execute($baseQuery, "l.tenant_id = $tenantId");
    }

    /**
     * Get advanced search count
     * @param int $tenantId Tenant ID
     * @param array $filters Advanced filter parameters
     * @return int Total count
     */
    public function advancedSearchCount($tenantId, $filters = array()) {
        require_once APP_PATH . '/helpers/SearchHelper.php';
        $search = new SearchHelper();

        // Apply same filters as advancedSearch (without pagination)
        if (!empty($filters['status'])) {
            $search->where('status', $filters['status']);
        }

        if (!empty($filters['assigned_to'])) {
            $search->where('assigned_to', $filters['assigned_to']);
        }

        if (!empty($filters['source'])) {
            $search->where('source', $filters['source']);
        }

        if (!empty($filters['interest_type'])) {
            $search->where('interest_type', $filters['interest_type']);
        }

        if (!empty($filters['budget_min']) || !empty($filters['budget_max'])) {
            $search->numericRange('budget_min',
                isset($filters['budget_min']) ? $filters['budget_min'] : null,
                isset($filters['budget_max']) ? $filters['budget_max'] : null
            );
        }

        if (!empty($filters['created_from']) || !empty($filters['created_to'])) {
            $search->dateRange('created_at',
                isset($filters['created_from']) ? $filters['created_from'] : null,
                isset($filters['created_to']) ? $filters['created_to'] : null
            );
        }

        if (!empty($filters['search'])) {
            $search->search(
                array('first_name', 'last_name', 'email', 'phone', 'notes'),
                $filters['search']
            );
        }

        return $search->count($this->table, "tenant_id = $tenantId");
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
