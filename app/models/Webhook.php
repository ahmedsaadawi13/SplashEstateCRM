<?php
// FILE: /app/models/Webhook.php

/**
 * SplashEstate CRM - Webhook Model
 * Manages webhook configurations
 */

class Webhook extends Model {

    protected $table = 'webhooks';

    /**
     * Get all webhooks for a tenant
     * @param int $tenantId Tenant ID
     * @return array Webhooks
     */
    public function getAll($tenantId) {
        $sql = "SELECT w.*, u.first_name, u.last_name
                FROM {$this->table} w
                LEFT JOIN users u ON w.created_by = u.id
                WHERE w.tenant_id = :tenant_id
                ORDER BY w.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Get active webhooks for a tenant and event
     * @param int $tenantId Tenant ID
     * @param string $eventType Event type
     * @return array Active webhooks
     */
    public function getActiveForEvent($tenantId, $eventType) {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND status = 'active'
                AND (events LIKE :event_exact OR events LIKE :event_wildcard)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':event_exact', '%' . $eventType . '%');
        $stmt->bindValue(':event_wildcard', '%*%');
        $stmt->execute();

        $webhooks = $stmt->fetchAll();

        // Filter to ensure exact match or wildcard
        $filtered = array();
        foreach ($webhooks as $webhook) {
            $events = array_map('trim', explode(',', $webhook['events']));
            if (in_array($eventType, $events) || in_array('*', $events)) {
                $filtered[] = $webhook;
            }
        }

        return $filtered;
    }

    /**
     * Get webhook by ID
     * @param int $id Webhook ID
     * @param int $tenantId Tenant ID
     * @return array|false Webhook data
     */
    public function getById($id, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Create webhook
     * @param array $data Webhook data
     * @return int|false Webhook ID
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, name, url, secret, events, status, retry_enabled, max_retries, created_by, created_at)
                VALUES
                (:tenant_id, :name, :url, :secret, :events, :status, :retry_enabled, :max_retries, :created_by, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':url', $data['url']);
        $stmt->bindValue(':secret', isset($data['secret']) ? $data['secret'] : null);
        $stmt->bindValue(':events', $data['events']);
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'active');
        $stmt->bindValue(':retry_enabled', isset($data['retry_enabled']) ? $data['retry_enabled'] : 1, PDO::PARAM_INT);
        $stmt->bindValue(':max_retries', isset($data['max_retries']) ? $data['max_retries'] : 3, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', isset($data['created_by']) ? $data['created_by'] : null, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Update webhook
     * @param int $id Webhook ID
     * @param int $tenantId Tenant ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($id, $tenantId, $data) {
        $fields = array();
        $params = array(':id' => $id, ':tenant_id' => $tenantId);

        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = $data['name'];
        }
        if (isset($data['url'])) {
            $fields[] = "url = :url";
            $params[':url'] = $data['url'];
        }
        if (isset($data['secret'])) {
            $fields[] = "secret = :secret";
            $params[':secret'] = $data['secret'];
        }
        if (isset($data['events'])) {
            $fields[] = "events = :events";
            $params[':events'] = $data['events'];
        }
        if (isset($data['status'])) {
            $fields[] = "status = :status";
            $params[':status'] = $data['status'];
        }
        if (isset($data['retry_enabled'])) {
            $fields[] = "retry_enabled = :retry_enabled";
            $params[':retry_enabled'] = $data['retry_enabled'];
        }
        if (isset($data['max_retries'])) {
            $fields[] = "max_retries = :max_retries";
            $params[':max_retries'] = $data['max_retries'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $fields) . ", updated_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete webhook
     * @param int $id Webhook ID
     * @param int $tenantId Tenant ID
     * @return bool Success
     */
    public function delete($id, $tenantId) {
        $sql = "DELETE FROM {$this->table}
                WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Get webhook statistics
     * @param int $webhookId Webhook ID
     * @return array Statistics
     */
    public function getStats($webhookId) {
        $sql = "SELECT
                    COUNT(*) as total_deliveries,
                    SUM(CASE WHEN status = 'success' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    MAX(delivered_at) as last_delivery
                FROM webhook_logs
                WHERE webhook_id = :webhook_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':webhook_id', $webhookId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Generate random webhook secret
     * @return string Secret
     */
    public function generateSecret() {
        return 'whsec_' . bin2hex(random_bytes(24));
    }

    /**
     * Get available event types
     * @return array Event types with descriptions
     */
    public function getAvailableEvents() {
        return array(
            'lead.created' => 'New lead created',
            'lead.updated' => 'Lead updated',
            'lead.deleted' => 'Lead deleted',
            'client.created' => 'New client created',
            'client.updated' => 'Client updated',
            'property.created' => 'New property listed',
            'property.updated' => 'Property updated',
            'property.deleted' => 'Property removed',
            'deal.created' => 'New deal created',
            'deal.updated' => 'Deal status changed',
            'deal.won' => 'Deal won',
            'deal.lost' => 'Deal lost',
            'task.created' => 'New task created',
            'task.completed' => 'Task completed',
            '*' => 'All events'
        );
    }
}
