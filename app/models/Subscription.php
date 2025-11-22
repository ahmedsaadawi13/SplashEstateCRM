<?php
// FILE: /app/models/Subscription.php

/**
 * SplashEstate CRM - Subscription Model
 * Handles tenant subscriptions and plans
 */

class Subscription extends Model {

    protected $table = 'tenant_subscriptions';

    /**
     * Create trial subscription for new tenant
     * @param int $tenantId Tenant ID
     * @return int|bool Subscription ID or false
     */
    public function createTrialSubscription($tenantId) {
        // Get free trial plan
        $planModel = $this->model('Plan');
        $trialPlan = $planModel->getBySlug('free-trial');

        if (!$trialPlan) {
            return false;
        }

        $data = array(
            'tenant_id' => $tenantId,
            'plan_id' => $trialPlan['id'],
            'status' => 'active',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'auto_renew' => 0
        );

        return $this->create($data);
    }

    /**
     * Create subscription
     * @param array $data Subscription data
     * @return int|bool Subscription ID or false
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (tenant_id, plan_id, status, start_date, end_date, auto_renew, created_at)
                VALUES (:tenant_id, :plan_id, :status, :start_date, :end_date, :auto_renew, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':plan_id', $data['plan_id'], PDO::PARAM_INT);
        $stmt->bindValue(':status', $data['status']);
        $stmt->bindValue(':start_date', $data['start_date']);
        $stmt->bindValue(':end_date', isset($data['end_date']) ? $data['end_date'] : null);
        $stmt->bindValue(':auto_renew', isset($data['auto_renew']) ? $data['auto_renew'] : 1, PDO::PARAM_INT);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Get active subscription for tenant with plan details
     * @param int $tenantId Tenant ID
     * @return mixed Subscription data or false
     */
    public function getActiveSubscription($tenantId) {
        $sql = "SELECT ts.*, p.name as plan_name, p.max_leads, p.max_properties, p.max_agents
                FROM {$this->table} ts
                JOIN plans p ON ts.plan_id = p.id
                WHERE ts.tenant_id = :tenant_id
                AND ts.status = 'active'
                AND (ts.end_date IS NULL OR ts.end_date > NOW())
                ORDER BY ts.created_at DESC
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch();
    }

    /**
     * Update subscription
     * @param int $id Subscription ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updateSubscription($id, $data) {
        $fields = array();
        foreach (array_keys($data) as $field) {
            $fields[] = "{$field} = :{$field}";
        }
        $fieldList = implode(', ', $fields);

        $sql = "UPDATE {$this->table} SET {$fieldList}, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Cancel subscription
     * @param int $id Subscription ID
     * @return bool Success status
     */
    public function cancel($id) {
        $sql = "UPDATE {$this->table} SET status = 'cancelled', updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Get subscription history for tenant
     * @param int $tenantId Tenant ID
     * @return array Subscriptions
     */
    public function getHistory($tenantId) {
        $sql = "SELECT ts.*, p.name as plan_name, p.price
                FROM {$this->table} ts
                JOIN plans p ON ts.plan_id = p.id
                WHERE ts.tenant_id = :tenant_id
                ORDER BY ts.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Check if resource quota exceeded
     * @param int $tenantId Tenant ID
     * @param string $resource Resource type
     * @return bool True if within quota
     */
    public function checkQuota($tenantId, $resource) {
        return checkQuota($tenantId, $resource);
    }

    /**
     * Get usage statistics
     * @param int $tenantId Tenant ID
     * @return array Usage statistics
     */
    public function getUsageStats($tenantId) {
        $stats = array();

        // Count leads
        $sql = "SELECT COUNT(*) as count FROM leads WHERE tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['leads_count'] = (int)$result['count'];

        // Count properties
        $sql = "SELECT COUNT(*) as count FROM properties WHERE tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['properties_count'] = (int)$result['count'];

        // Count agents
        $sql = "SELECT COUNT(*) as count FROM users WHERE tenant_id = :tenant_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch();
        $stats['agents_count'] = (int)$result['count'];

        return $stats;
    }

    /**
     * Helper to get Plan model
     */
    private function model($modelName) {
        require_once '../app/models/' . $modelName . '.php';
        return new $modelName();
    }
}
