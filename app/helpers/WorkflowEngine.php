<?php
// FILE: /app/helpers/WorkflowEngine.php

/**
 * SplashEstate CRM - Workflow Execution Engine
 * Executes automated workflows based on triggers
 */

class WorkflowEngine {

    private $workflowModel;
    private $db;
    private $conn;

    public function __construct() {
        require_once APP_PATH . '/models/Workflow.php';
        $this->workflowModel = new Workflow();
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Trigger workflows for an event
     * @param int $tenantId Tenant ID
     * @param string $triggerType Trigger type
     * @param array $data Trigger data
     * @return array Execution results
     */
    public function trigger($tenantId, $triggerType, $data) {
        // Get active workflows for this trigger
        $workflows = $this->workflowModel->getByTrigger($tenantId, $triggerType);

        if (empty($workflows)) {
            return array(
                'executed' => 0,
                'workflows' => array()
            );
        }

        $results = array();

        foreach ($workflows as $workflow) {
            // Check if conditions are met
            if (!$this->checkConditions($workflow, $data)) {
                continue;
            }

            // Execute workflow
            $executionId = $this->executeWorkflow($workflow, $data);
            $results[] = array(
                'workflow_id' => $workflow['id'],
                'workflow_name' => $workflow['name'],
                'execution_id' => $executionId
            );
        }

        return array(
            'executed' => count($results),
            'workflows' => $results
        );
    }

    /**
     * Execute a workflow
     * @param array $workflow Workflow data
     * @param array $triggerData Data that triggered the workflow
     * @return int|false Execution ID
     */
    private function executeWorkflow($workflow, $triggerData) {
        // Create execution record
        $executionId = $this->createExecution($workflow['id'], $triggerData);

        if (!$executionId) {
            return false;
        }

        // Update execution status
        $this->updateExecutionStatus($executionId, 'running');

        try {
            // Get workflow actions
            $actions = $this->workflowModel->getActions($workflow['id']);

            // Execute actions in order
            foreach ($actions as $action) {
                $this->executeAction($executionId, $action, $triggerData);
            }

            // Mark as completed
            $this->updateExecutionStatus($executionId, 'completed');

            return $executionId;

        } catch (Exception $e) {
            error_log('Workflow execution error: ' . $e->getMessage());
            $this->updateExecutionStatus($executionId, 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Check if workflow conditions are met
     * @param array $workflow Workflow data
     * @param array $data Trigger data
     * @return bool Conditions met
     */
    private function checkConditions($workflow, $data) {
        if (empty($workflow['conditions'])) {
            return true;
        }

        $conditions = json_decode($workflow['conditions'], true);
        if (!is_array($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $data)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     * @param array $condition Condition config
     * @param array $data Data to check
     * @return bool Condition met
     */
    private function evaluateCondition($condition, $data) {
        $field = $condition['field'];
        $operator = $condition['operator'];
        $value = $condition['value'];

        // Get field value from data
        $fieldValue = isset($data[$field]) ? $data[$field] : null;

        switch ($operator) {
            case 'equals':
                return $fieldValue == $value;
            case 'not_equals':
                return $fieldValue != $value;
            case 'contains':
                return strpos($fieldValue, $value) !== false;
            case 'not_contains':
                return strpos($fieldValue, $value) === false;
            case 'greater_than':
                return $fieldValue > $value;
            case 'less_than':
                return $fieldValue < $value;
            case 'is_empty':
                return empty($fieldValue);
            case 'is_not_empty':
                return !empty($fieldValue);
            default:
                return true;
        }
    }

    /**
     * Execute a single action
     * @param int $executionId Execution ID
     * @param array $action Action data
     * @param array $triggerData Trigger data
     * @return bool Success
     */
    private function executeAction($executionId, $action, $triggerData) {
        $actionType = $action['action_type'];
        $config = json_decode($action['action_config'], true);

        // Apply delay if specified
        if ($action['delay_minutes'] > 0) {
            // In production, this would be queued for later execution
            // For now, we'll just log it
            $this->logAction($executionId, $action['id'], 'skipped', 'Delayed execution not implemented yet');
            return true;
        }

        try {
            $result = null;

            switch ($actionType) {
                case 'send_email':
                    $result = $this->actionSendEmail($config, $triggerData);
                    break;

                case 'create_task':
                    $result = $this->actionCreateTask($config, $triggerData);
                    break;

                case 'update_field':
                    $result = $this->actionUpdateField($config, $triggerData);
                    break;

                case 'assign_to_user':
                    $result = $this->actionAssignToUser($config, $triggerData);
                    break;

                case 'send_webhook':
                    $result = $this->actionSendWebhook($config, $triggerData);
                    break;

                case 'send_notification':
                    $result = $this->actionSendNotification($config, $triggerData);
                    break;

                default:
                    $result = 'Unknown action type';
            }

            $this->logAction($executionId, $action['id'], 'completed', json_encode($result));
            return true;

        } catch (Exception $e) {
            $this->logAction($executionId, $action['id'], 'failed', $e->getMessage());
            return false;
        }
    }

    /**
     * Action: Send Email
     */
    private function actionSendEmail($config, $triggerData) {
        require_once APP_PATH . '/helpers/EmailService.php';
        $emailService = new EmailService();

        $to = $this->replacePlaceholders($config['to'], $triggerData);
        $subject = $this->replacePlaceholders($config['subject'], $triggerData);
        $body = $this->replacePlaceholders($config['body'], $triggerData);

        return $emailService->sendEmail($to, $subject, $body);
    }

    /**
     * Action: Create Task
     */
    private function actionCreateTask($config, $triggerData) {
        $sql = "INSERT INTO tasks (tenant_id, related_to_type, related_to_id, assigned_to, title, description, due_date, priority, status, created_at)
                VALUES (:tenant_id, :related_to_type, :related_to_id, :assigned_to, :title, :description, :due_date, :priority, 'pending', NOW())";

        $stmt = $this->conn->prepare($sql);

        $dueDate = isset($config['due_date_offset'])
            ? date('Y-m-d H:i:s', strtotime('+' . $config['due_date_offset'] . ' days'))
            : null;

        return $stmt->execute([
            ':tenant_id' => $triggerData['tenant_id'],
            ':related_to_type' => isset($triggerData['entity_type']) ? $triggerData['entity_type'] : null,
            ':related_to_id' => isset($triggerData['entity_id']) ? $triggerData['entity_id'] : null,
            ':assigned_to' => isset($config['assigned_to']) ? $config['assigned_to'] : null,
            ':title' => $this->replacePlaceholders($config['title'], $triggerData),
            ':description' => $this->replacePlaceholders($config['description'], $triggerData),
            ':due_date' => $dueDate,
            ':priority' => isset($config['priority']) ? $config['priority'] : 'normal'
        ]);
    }

    /**
     * Action: Update Field
     */
    private function actionUpdateField($config, $triggerData) {
        $table = $config['table'];
        $field = $config['field'];
        $value = $this->replacePlaceholders($config['value'], $triggerData);
        $entityId = $triggerData['entity_id'];

        $sql = "UPDATE {$table} SET {$field} = :value WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':value' => $value,
            ':id' => $entityId
        ]);
    }

    /**
     * Action: Assign to User
     */
    private function actionAssignToUser($config, $triggerData) {
        $table = isset($triggerData['entity_type']) ? $triggerData['entity_type'] . 's' : 'leads';
        $userId = $config['user_id'];
        $entityId = $triggerData['entity_id'];

        $sql = "UPDATE {$table} SET assigned_to = :user_id WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':user_id' => $userId,
            ':id' => $entityId
        ]);
    }

    /**
     * Action: Send Webhook
     */
    private function actionSendWebhook($config, $triggerData) {
        $webhookService = new WebhookService();
        return $webhookService->trigger(
            $triggerData['tenant_id'],
            $config['event_type'],
            $triggerData
        );
    }

    /**
     * Action: Send Notification
     */
    private function actionSendNotification($config, $triggerData) {
        // This would integrate with a notification system
        // For now, just log it
        return true;
    }

    /**
     * Replace placeholders in text with actual data
     * @param string $text Text with placeholders
     * @param array $data Data to replace
     * @return string Text with replaced values
     */
    private function replacePlaceholders($text, $data) {
        foreach ($data as $key => $value) {
            if (is_scalar($value)) {
                $text = str_replace('{{' . $key . '}}', $value, $text);
            }
        }
        return $text;
    }

    /**
     * Create execution record
     */
    private function createExecution($workflowId, $triggerData) {
        $sql = "INSERT INTO workflow_executions (workflow_id, trigger_data, status, started_at)
                VALUES (:workflow_id, :trigger_data, 'pending', NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':workflow_id' => $workflowId,
            ':trigger_data' => json_encode($triggerData)
        ]);

        return $this->conn->lastInsertId();
    }

    /**
     * Update execution status
     */
    private function updateExecutionStatus($executionId, $status, $errorMessage = null) {
        $sql = "UPDATE workflow_executions
                SET status = :status,
                    error_message = :error_message,
                    completed_at = " . ($status == 'completed' || $status == 'failed' ? 'NOW()' : 'NULL') . "
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':status' => $status,
            ':error_message' => $errorMessage,
            ':id' => $executionId
        ]);
    }

    /**
     * Log action execution
     */
    private function logAction($executionId, $actionId, $status, $result) {
        $sql = "INSERT INTO workflow_action_logs (execution_id, action_id, status, result, executed_at)
                VALUES (:execution_id, :action_id, :status, :result, NOW())";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':execution_id' => $executionId,
            ':action_id' => $actionId,
            ':status' => $status,
            ':result' => $result
        ]);
    }
}
