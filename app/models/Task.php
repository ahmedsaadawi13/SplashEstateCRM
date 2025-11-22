<?php
// FILE: /app/models/Task.php

/**
 * SplashEstate CRM - Task Model
 * Handles task management
 */

class Task extends Model {

    protected $table = 'tasks';

    /**
     * Get tasks with user info
     * @param int $tenantId Tenant ID
     * @param int $page Page number
     * @param int $perPage Records per page
     * @param array $filters Filters
     * @return array Tasks
     */
    public function getTasksWithInfo($tenantId, $page = 1, $perPage = 20, $filters = array()) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT t.*,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name,
                CONCAT(u2.first_name, ' ', u2.last_name) as created_by_name
                FROM {$this->table} t
                LEFT JOIN users u ON t.assigned_to = u.id
                LEFT JOIN users u2 ON t.created_by = u2.id
                WHERE t.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        // Add filters
        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = :assigned_to";
            $params[':assigned_to'] = $filters['assigned_to'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        $sql .= " ORDER BY t.due_date ASC, t.priority DESC LIMIT :limit OFFSET :offset";

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
     * Count tasks by assignee
     * @param int $tenantId Tenant ID
     * @param int $userId User ID
     * @param string $status Task status
     * @return int Count
     */
    public function countByAssignee($tenantId, $userId, $status = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE tenant_id = :tenant_id AND assigned_to = :user_id";

        if ($status !== null) {
            $sql .= " AND status = :status";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);

        if ($status !== null) {
            $stmt->bindValue(':status', $status);
        }

        $stmt->execute();
        $result = $stmt->fetch();

        return (int)$result['count'];
    }

    /**
     * Get upcoming tasks
     * @param int $tenantId Tenant ID
     * @param int $userId User ID
     * @param int $limit Limit
     * @return array Tasks
     */
    public function getUpcoming($tenantId, $userId, $limit = 5) {
        $sql = "SELECT t.*,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
                FROM {$this->table} t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.tenant_id = :tenant_id
                AND t.assigned_to = :user_id
                AND t.status IN ('pending', 'in_progress')
                AND t.due_date >= NOW()
                ORDER BY t.due_date ASC
                LIMIT :limit";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Create task
     * @param array $data Task data
     * @return int|bool Task ID or false
     */
    public function createTask($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, assigned_to, created_by, related_to_type, related_to_id, title, description, due_date, priority, status, created_at)
                VALUES (:tenant_id, :assigned_to, :created_by, :related_to_type, :related_to_id, :title, :description, :due_date, :priority, :status, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':assigned_to', isset($data['assigned_to']) ? $data['assigned_to'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', isset($data['created_by']) ? $data['created_by'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':related_to_type', isset($data['related_to_type']) ? $data['related_to_type'] : null);
        $stmt->bindValue(':related_to_id', isset($data['related_to_id']) ? $data['related_to_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':description', isset($data['description']) ? $data['description'] : null);
        $stmt->bindValue(':due_date', isset($data['due_date']) ? $data['due_date'] : null);
        $stmt->bindValue(':priority', isset($data['priority']) ? $data['priority'] : 'medium');
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'pending');

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Mark task as completed
     * @param int $id Task ID
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function markCompleted($id, $tenantId) {
        $sql = "UPDATE {$this->table} SET status = 'completed', completed_at = NOW(), updated_at = NOW() WHERE id = :id AND tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
