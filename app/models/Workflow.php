<?php
// FILE: /app/models/Workflow.php

/**
 * SplashEstate CRM - Workflow Model
 * Manages automated workflows
 */

class Workflow extends Model {

    protected $table = 'workflows';

    /**
     * Get all workflows for a tenant
     * @param int $tenantId Tenant ID
     * @param string|null $status Filter by status
     * @return array Workflows
     */
    public function getAll($tenantId, $status = null) {
        $sql = "SELECT w.*, u.first_name, u.last_name,
                (SELECT COUNT(*) FROM workflow_actions WHERE workflow_id = w.id) as action_count
                FROM {$this->table} w
                LEFT JOIN users u ON w.created_by = u.id
                WHERE w.tenant_id = :tenant_id";

        if ($status) {
            $sql .= " AND w.status = :status";
        }

        $sql .= " ORDER BY w.execution_order, w.created_at DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);

        if ($status) {
            $stmt->bindValue(':status', $status);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get workflow by ID
     * @param int $id Workflow ID
     * @param int $tenantId Tenant ID
     * @return array|false Workflow data
     */
    public function getById($id, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $tenantId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get active workflows by trigger type
     * @param int $tenantId Tenant ID
     * @param string $triggerType Trigger type
     * @return array Workflows
     */
    public function getByTrigger($tenantId, $triggerType) {
        $sql = "SELECT * FROM {$this->table}
                WHERE tenant_id = :tenant_id
                AND trigger_type = :trigger_type
                AND status = 'active'
                ORDER BY execution_order ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':trigger_type' => $triggerType
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create workflow
     * @param array $data Workflow data
     * @return int|false Workflow ID
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, name, description, trigger_type, trigger_config, conditions, status, execution_order, created_by)
                VALUES
                (:tenant_id, :name, :description, :trigger_type, :trigger_config, :conditions, :status, :execution_order, :created_by)";

        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => $data['tenant_id'],
            ':name' => $data['name'],
            ':description' => isset($data['description']) ? $data['description'] : null,
            ':trigger_type' => $data['trigger_type'],
            ':trigger_config' => isset($data['trigger_config']) ? json_encode($data['trigger_config']) : null,
            ':conditions' => isset($data['conditions']) ? json_encode($data['conditions']) : null,
            ':status' => isset($data['status']) ? $data['status'] : 'active',
            ':execution_order' => isset($data['execution_order']) ? $data['execution_order'] : 0,
            ':created_by' => isset($data['created_by']) ? $data['created_by'] : null
        ]);

        return $result ? $this->conn->lastInsertId() : false;
    }

    /**
     * Update workflow
     * @param int $id Workflow ID
     * @param int $tenantId Tenant ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($id, $tenantId, $data) {
        $fields = array();
        $params = array(':id' => $id, ':tenant_id' => $tenantId);

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'];
        }
        if (isset($data['trigger_type'])) {
            $fields[] = 'trigger_type = :trigger_type';
            $params[':trigger_type'] = $data['trigger_type'];
        }
        if (isset($data['trigger_config'])) {
            $fields[] = 'trigger_config = :trigger_config';
            $params[':trigger_config'] = json_encode($data['trigger_config']);
        }
        if (isset($data['conditions'])) {
            $fields[] = 'conditions = :conditions';
            $params[':conditions'] = json_encode($data['conditions']);
        }
        if (isset($data['status'])) {
            $fields[] = 'status = :status';
            $params[':status'] = $data['status'];
        }
        if (isset($data['execution_order'])) {
            $fields[] = 'execution_order = :execution_order';
            $params[':execution_order'] = $data['execution_order'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table}
                SET " . implode(', ', $fields) . "
                WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete workflow
     * @param int $id Workflow ID
     * @param int $tenantId Tenant ID
     * @return bool Success
     */
    public function delete($id, $tenantId) {
        $sql = "DELETE FROM {$this->table}
                WHERE id = :id AND tenant_id = :tenant_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':tenant_id' => $tenantId
        ]);
    }

    /**
     * Get workflow actions
     * @param int $workflowId Workflow ID
     * @return array Actions
     */
    public function getActions($workflowId) {
        $sql = "SELECT * FROM workflow_actions
                WHERE workflow_id = :workflow_id
                ORDER BY execution_order ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':workflow_id' => $workflowId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add action to workflow
     * @param int $workflowId Workflow ID
     * @param array $actionData Action data
     * @return int|false Action ID
     */
    public function addAction($workflowId, $actionData) {
        $sql = "INSERT INTO workflow_actions
                (workflow_id, action_type, action_config, execution_order, delay_minutes)
                VALUES
                (:workflow_id, :action_type, :action_config, :execution_order, :delay_minutes)";

        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([
            ':workflow_id' => $workflowId,
            ':action_type' => $actionData['action_type'],
            ':action_config' => json_encode($actionData['action_config']),
            ':execution_order' => isset($actionData['execution_order']) ? $actionData['execution_order'] : 0,
            ':delay_minutes' => isset($actionData['delay_minutes']) ? $actionData['delay_minutes'] : 0
        ]);

        return $result ? $this->conn->lastInsertId() : false;
    }

    /**
     * Remove action from workflow
     * @param int $actionId Action ID
     * @return bool Success
     */
    public function removeAction($actionId) {
        $sql = "DELETE FROM workflow_actions WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $actionId]);
    }

    /**
     * Get execution history
     * @param int $workflowId Workflow ID
     * @param int $limit Limit
     * @return array Executions
     */
    public function getExecutionHistory($workflowId, $limit = 50) {
        $sql = "SELECT * FROM workflow_executions
                WHERE workflow_id = :workflow_id
                ORDER BY created_at DESC
                LIMIT :limit";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':workflow_id', $workflowId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get workflow statistics
     * @param int $workflowId Workflow ID
     * @return array Statistics
     */
    public function getStatistics($workflowId) {
        $sql = "SELECT
                    COUNT(*) as total_executions,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as successful,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    MAX(created_at) as last_execution
                FROM workflow_executions
                WHERE workflow_id = :workflow_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':workflow_id' => $workflowId]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
