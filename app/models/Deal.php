<?php
// FILE: /app/models/Deal.php

/**
 * SplashEstate CRM - Deal Model
 * Handles deal/pipeline management
 */

class Deal extends Model {

    protected $table = 'deals';

    /**
     * Get deals with related info
     * @param int $tenantId Tenant ID
     * @param int $page Page number
     * @param int $perPage Records per page
     * @param array $filters Filters
     * @return array Deals
     */
    public function getDealsWithInfo($tenantId, $page = 1, $perPage = 20, $filters = array()) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT d.*,
                CONCAT(c.first_name, ' ', c.last_name) as client_name,
                p.title as property_title,
                CONCAT(u.first_name, ' ', u.last_name) as agent_name
                FROM {$this->table} d
                LEFT JOIN clients c ON d.client_id = c.id
                LEFT JOIN properties p ON d.property_id = p.id
                LEFT JOIN users u ON d.assigned_to = u.id
                WHERE d.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        // Add filters
        if (!empty($filters['stage'])) {
            $sql .= " AND d.stage = :stage";
            $params[':stage'] = $filters['stage'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND d.assigned_to = :assigned_to";
            $params[':assigned_to'] = $filters['assigned_to'];
        }

        // Search
        if (!empty($filters['search'])) {
            $sql .= " AND d.title LIKE :search";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY d.created_at DESC LIMIT :limit OFFSET :offset";

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
     * Get active deals count
     * @param int $tenantId Tenant ID
     * @return int Count
     */
    public function getActiveDealsCount($tenantId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND stage NOT IN ('closed_won', 'closed_lost')";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();

        return (int)$result['count'];
    }

    /**
     * Create deal
     * @param array $data Deal data
     * @return int|bool Deal ID or false
     */
    public function createDeal($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, client_id, property_id, assigned_to, title, deal_value, commission, stage, probability, expected_close_date, notes, created_at)
                VALUES (:tenant_id, :client_id, :property_id, :assigned_to, :title, :deal_value, :commission, :stage, :probability, :expected_close_date, :notes, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':client_id', isset($data['client_id']) ? $data['client_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':property_id', isset($data['property_id']) ? $data['property_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':assigned_to', isset($data['assigned_to']) ? $data['assigned_to'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':deal_value', isset($data['deal_value']) ? $data['deal_value'] : null);
        $stmt->bindValue(':commission', isset($data['commission']) ? $data['commission'] : null);
        $stmt->bindValue(':stage', isset($data['stage']) ? $data['stage'] : 'lead');
        $stmt->bindValue(':probability', isset($data['probability']) ? $data['probability'] : 0, PDO::PARAM_INT);
        $stmt->bindValue(':expected_close_date', isset($data['expected_close_date']) ? $data['expected_close_date'] : null);
        $stmt->bindValue(':notes', isset($data['notes']) ? $data['notes'] : null);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Update deal stage
     * @param int $id Deal ID
     * @param int $tenantId Tenant ID
     * @param string $stage New stage
     * @return bool Success status
     */
    public function updateStage($id, $tenantId, $stage) {
        $sql = "UPDATE {$this->table} SET stage = :stage, updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':stage', $stage);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
