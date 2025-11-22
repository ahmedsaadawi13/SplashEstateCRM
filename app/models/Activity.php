<?php
// FILE: /app/models/Activity.php

/**
 * SplashEstate CRM - Activity Model
 * Handles activity tracking (calls, meetings, emails, notes)
 */

class Activity extends Model {

    protected $table = 'activities';

    /**
     * Get activities by related entity
     * @param int $tenantId Tenant ID
     * @param string $type Related entity type
     * @param int $relatedId Related entity ID
     * @return array Activities
     */
    public function getByRelated($tenantId, $type, $relatedId) {
        $sql = "SELECT a.*,
                CONCAT(u.first_name, ' ', u.last_name) as user_name
                FROM {$this->table} a
                LEFT JOIN users u ON a.user_id = u.id
                WHERE a.tenant_id = :tenant_id
                AND a.related_to_type = :type
                AND a.related_to_id = :related_id
                ORDER BY a.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':type', $type);
        $stmt->bindValue(':related_id', $relatedId, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Create activity
     * @param array $data Activity data
     * @return int|bool Activity ID or false
     */
    public function createActivity($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, user_id, activity_type, related_to_type, related_to_id, subject, description, scheduled_at, completed, created_at)
                VALUES (:tenant_id, :user_id, :activity_type, :related_to_type, :related_to_id, :subject, :description, :scheduled_at, :completed, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':user_id', isset($data['user_id']) ? $data['user_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':activity_type', $data['activity_type']);
        $stmt->bindValue(':related_to_type', isset($data['related_to_type']) ? $data['related_to_type'] : null);
        $stmt->bindValue(':related_to_id', isset($data['related_to_id']) ? $data['related_to_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':subject', $data['subject']);
        $stmt->bindValue(':description', isset($data['description']) ? $data['description'] : null);
        $stmt->bindValue(':scheduled_at', isset($data['scheduled_at']) ? $data['scheduled_at'] : null);
        $stmt->bindValue(':completed', isset($data['completed']) ? $data['completed'] : 0, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }
}
