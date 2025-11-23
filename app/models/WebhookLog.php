<?php
// FILE: /app/models/WebhookLog.php

/**
 * SplashEstate CRM - Webhook Log Model
 * Manages webhook delivery logs
 */

class WebhookLog extends Model {

    protected $table = 'webhook_logs';

    /**
     * Create webhook log entry
     * @param array $data Log data
     * @return int|false Log ID
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (webhook_id, event_type, payload, response_code, response_body, attempt, status, error_message, delivered_at, created_at)
                VALUES
                (:webhook_id, :event_type, :payload, :response_code, :response_body, :attempt, :status, :error_message, :delivered_at, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':webhook_id', $data['webhook_id'], PDO::PARAM_INT);
        $stmt->bindValue(':event_type', $data['event_type']);
        $stmt->bindValue(':payload', isset($data['payload']) ? $data['payload'] : null);
        $stmt->bindValue(':response_code', isset($data['response_code']) ? $data['response_code'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':response_body', isset($data['response_body']) ? $data['response_body'] : null);
        $stmt->bindValue(':attempt', isset($data['attempt']) ? $data['attempt'] : 1, PDO::PARAM_INT);
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'pending');
        $stmt->bindValue(':error_message', isset($data['error_message']) ? $data['error_message'] : null);
        $stmt->bindValue(':delivered_at', isset($data['delivered_at']) ? $data['delivered_at'] : null);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Update webhook log
     * @param int $id Log ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($id, $data) {
        $fields = array();
        $params = array(':id' => $id);

        if (isset($data['response_code'])) {
            $fields[] = "response_code = :response_code";
            $params[':response_code'] = $data['response_code'];
        }
        if (isset($data['response_body'])) {
            $fields[] = "response_body = :response_body";
            $params[':response_body'] = $data['response_body'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        if (isset($data['error_message'])) {
            $fields[] = "error_message = :error_message";
            $params[':error_message'] = $data['error_message'];
        }
        if (isset($data['delivered_at'])) {
            $fields[] = "delivered_at = :delivered_at";
            $params[':delivered_at'] = $data['delivered_at'];
        }
        if (isset($data['attempt'])) {
            $fields[] = "attempt = :attempt";
            $params[':attempt'] = $data['attempt'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $fields) . "
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Get logs for a webhook
     * @param int $webhookId Webhook ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Logs
     */
    public function getByWebhook($webhookId, $limit = 50, $offset = 0) {
        $sql = "SELECT * FROM {$this->table}
                WHERE webhook_id = :webhook_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':webhook_id', $webhookId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get logs for a tenant
     * @param int $tenantId Tenant ID
     * @param int $limit Limit
     * @param int $offset Offset
     * @return array Logs with webhook info
     */
    public function getByTenant($tenantId, $limit = 50, $offset = 0) {
        $sql = "SELECT wl.*, w.name as webhook_name, w.url as webhook_url
                FROM {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                WHERE w.tenant_id = :tenant_id
                ORDER BY wl.created_at DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get failed logs for retry
     * @param int $maxRetries Maximum retries
     * @return array Failed logs
     */
    public function getFailedForRetry($maxRetries = 3) {
        $sql = "SELECT wl.*, w.url, w.secret, w.retry_enabled, w.max_retries
                FROM {$this->table} wl
                JOIN webhooks w ON wl.webhook_id = w.id
                WHERE wl.status = 'failed'
                AND w.status = 'active'
                AND w.retry_enabled = 1
                AND wl.attempt < w.max_retries
                AND wl.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY wl.created_at ASC
                LIMIT 100";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Count logs by status
     * @param int $webhookId Webhook ID
     * @return array Status counts
     */
    public function countByStatus($webhookId) {
        $sql = "SELECT status, COUNT(*) as count
                FROM {$this->table}
                WHERE webhook_id = :webhook_id
                GROUP BY status";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':webhook_id', $webhookId, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();
        $counts = array(
            'pending' => 0,
            'success' => 0,
            'failed' => 0,
            'retrying' => 0
        );

        foreach ($results as $row) {
            $counts[$row['status']] = (int)$row['count'];
        }

        return $counts;
    }

    /**
     * Delete old logs
     * @param int $days Days to keep
     * @return int Deleted count
     */
    public function deleteOldLogs($days = 30) {
        $sql = "DELETE FROM {$this->table}
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }
}
