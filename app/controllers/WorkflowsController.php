<?php
/**
 * Workflows Controller
 *
 * Manages workflow automation - create, edit, and monitor automated workflows
 */

require_once APP_PATH . '/models/Workflow.php';
require_once APP_PATH . '/helpers/WorkflowEngine.php';

class WorkflowsController extends BaseController
{
    private $workflowModel;
    private $workflowEngine;

    public function __construct()
    {
        parent::__construct();
        $this->workflowModel = new Workflow();
        $this->workflowEngine = new WorkflowEngine();

        // Require authentication
        if (!isset($_SESSION['user_id'])) {
            redirect('/auth/login');
            exit;
        }

        // Check permission
        if (!can('manage_workflows')) {
            $_SESSION['error'] = 'You do not have permission to manage workflows';
            redirect('/dashboard');
            exit;
        }
    }

    /**
     * Display list of workflows
     */
    public function index()
    {
        $tenantId = $_SESSION['tenant_id'];

        // Get filter parameters
        $status = isset($_GET['status']) ? $_GET['status'] : 'all';
        $triggerType = isset($_GET['trigger']) ? $_GET['trigger'] : 'all';

        // Build filter conditions
        $conditions = array('tenant_id' => $tenantId);

        if ($status !== 'all') {
            $conditions['status'] = $status;
        }

        if ($triggerType !== 'all') {
            $conditions['trigger_type'] = $triggerType;
        }

        // Get workflows
        $workflows = $this->workflowModel->getAll($conditions);

        // Get statistics for each workflow
        foreach ($workflows as &$workflow) {
            $workflow['stats'] = $this->workflowModel->getStatistics($workflow['id']);
        }

        // Available trigger types
        $triggerTypes = array(
            'lead_created' => 'Lead Created',
            'lead_updated' => 'Lead Updated',
            'lead_status_changed' => 'Lead Status Changed',
            'client_created' => 'Client Created',
            'property_created' => 'Property Created',
            'deal_created' => 'Deal Created',
            'deal_status_changed' => 'Deal Status Changed',
            'deal_won' => 'Deal Won',
            'deal_lost' => 'Deal Lost',
            'task_created' => 'Task Created',
            'task_completed' => 'Task Completed',
            'scheduled' => 'Scheduled (Time-based)'
        );

        $data = array(
            'workflows' => $workflows,
            'triggerTypes' => $triggerTypes,
            'currentStatus' => $status,
            'currentTrigger' => $triggerType
        );

        $this->view('workflows/index', $data);
    }

    /**
     * Show create workflow form
     */
    public function create()
    {
        $data = array(
            'triggerTypes' => $this->getTriggerTypes(),
            'actionTypes' => $this->getActionTypes(),
            'operators' => $this->getOperators()
        );

        $this->view('workflows/create', $data);
    }

    /**
     * Store new workflow
     */
    public function store()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/workflows/create');
            exit;
        }

        $tenantId = $_SESSION['tenant_id'];

        // Validate required fields
        $required = array('name', 'trigger_type');
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = "Field '{$field}' is required";
                redirect('/workflows/create');
                exit;
            }
        }

        // Prepare workflow data
        $workflowData = array(
            'tenant_id' => $tenantId,
            'name' => sanitize($_POST['name']),
            'description' => sanitize($_POST['description']),
            'trigger_type' => $_POST['trigger_type'],
            'trigger_config' => isset($_POST['trigger_config']) ? json_encode($_POST['trigger_config']) : null,
            'conditions' => isset($_POST['conditions']) ? json_encode($_POST['conditions']) : null,
            'status' => isset($_POST['status']) ? $_POST['status'] : 'active',
            'execution_order' => isset($_POST['execution_order']) ? (int)$_POST['execution_order'] : 0
        );

        // Create workflow
        $workflowId = $this->workflowModel->create($workflowData);

        if ($workflowId) {
            // Add actions if provided
            if (isset($_POST['actions']) && is_array($_POST['actions'])) {
                foreach ($_POST['actions'] as $index => $action) {
                    $actionData = array(
                        'action_type' => $action['type'],
                        'action_config' => json_encode($action['config']),
                        'execution_order' => $index,
                        'delay_minutes' => isset($action['delay']) ? (int)$action['delay'] : 0
                    );

                    $this->workflowModel->addAction($workflowId, $actionData);
                }
            }

            $_SESSION['success'] = 'Workflow created successfully';
            redirect('/workflows');
        } else {
            $_SESSION['error'] = 'Failed to create workflow';
            redirect('/workflows/create');
        }
    }

    /**
     * Show edit workflow form
     */
    public function edit($id)
    {
        $tenantId = $_SESSION['tenant_id'];

        $workflow = $this->workflowModel->getById($id);

        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $_SESSION['error'] = 'Workflow not found';
            redirect('/workflows');
            exit;
        }

        // Get workflow actions
        $workflow['actions'] = $this->workflowModel->getActions($id);

        // Decode JSON fields
        $workflow['trigger_config'] = json_decode($workflow['trigger_config'], true);
        $workflow['conditions'] = json_decode($workflow['conditions'], true);

        $data = array(
            'workflow' => $workflow,
            'triggerTypes' => $this->getTriggerTypes(),
            'actionTypes' => $this->getActionTypes(),
            'operators' => $this->getOperators()
        );

        $this->view('workflows/edit', $data);
    }

    /**
     * Update workflow
     */
    public function update($id)
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('/workflows');
            exit;
        }

        $tenantId = $_SESSION['tenant_id'];

        $workflow = $this->workflowModel->getById($id);

        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $_SESSION['error'] = 'Workflow not found';
            redirect('/workflows');
            exit;
        }

        // Prepare update data
        $workflowData = array(
            'name' => sanitize($_POST['name']),
            'description' => sanitize($_POST['description']),
            'trigger_type' => $_POST['trigger_type'],
            'trigger_config' => isset($_POST['trigger_config']) ? json_encode($_POST['trigger_config']) : null,
            'conditions' => isset($_POST['conditions']) ? json_encode($_POST['conditions']) : null,
            'status' => $_POST['status'],
            'execution_order' => (int)$_POST['execution_order']
        );

        // Update workflow
        if ($this->workflowModel->update($id, $workflowData)) {
            // Remove old actions
            $this->workflowModel->removeAllActions($id);

            // Add new actions
            if (isset($_POST['actions']) && is_array($_POST['actions'])) {
                foreach ($_POST['actions'] as $index => $action) {
                    $actionData = array(
                        'action_type' => $action['type'],
                        'action_config' => json_encode($action['config']),
                        'execution_order' => $index,
                        'delay_minutes' => isset($action['delay']) ? (int)$action['delay'] : 0
                    );

                    $this->workflowModel->addAction($id, $actionData);
                }
            }

            $_SESSION['success'] = 'Workflow updated successfully';
        } else {
            $_SESSION['error'] = 'Failed to update workflow';
        }

        redirect('/workflows');
    }

    /**
     * Delete workflow
     */
    public function delete($id)
    {
        $tenantId = $_SESSION['tenant_id'];

        $workflow = $this->workflowModel->getById($id);

        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $_SESSION['error'] = 'Workflow not found';
            redirect('/workflows');
            exit;
        }

        if ($this->workflowModel->delete($id)) {
            $_SESSION['success'] = 'Workflow deleted successfully';
        } else {
            $_SESSION['error'] = 'Failed to delete workflow';
        }

        redirect('/workflows');
    }

    /**
     * Toggle workflow status (active/inactive)
     */
    public function toggle($id)
    {
        $tenantId = $_SESSION['tenant_id'];

        $workflow = $this->workflowModel->getById($id);

        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $_SESSION['error'] = 'Workflow not found';
            redirect('/workflows');
            exit;
        }

        $newStatus = $workflow['status'] === 'active' ? 'inactive' : 'active';

        if ($this->workflowModel->update($id, array('status' => $newStatus))) {
            $_SESSION['success'] = "Workflow {$newStatus}";
        } else {
            $_SESSION['error'] = 'Failed to update workflow status';
        }

        redirect('/workflows');
    }

    /**
     * View workflow execution history
     */
    public function executions($id)
    {
        $tenantId = $_SESSION['tenant_id'];

        $workflow = $this->workflowModel->getById($id);

        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $_SESSION['error'] = 'Workflow not found';
            redirect('/workflows');
            exit;
        }

        // Get executions with pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $executions = $this->workflowModel->getExecutions($id, $perPage, $offset);
        $totalExecutions = $this->workflowModel->countExecutions($id);
        $totalPages = ceil($totalExecutions / $perPage);

        $data = array(
            'workflow' => $workflow,
            'executions' => $executions,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalExecutions' => $totalExecutions
        );

        $this->view('workflows/executions', $data);
    }

    /**
     * View single execution details
     */
    public function execution($executionId)
    {
        $tenantId = $_SESSION['tenant_id'];

        $execution = $this->workflowModel->getExecutionById($executionId);

        if (!$execution) {
            $_SESSION['error'] = 'Execution not found';
            redirect('/workflows');
            exit;
        }

        // Verify workflow belongs to tenant
        $workflow = $this->workflowModel->getById($execution['workflow_id']);
        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $_SESSION['error'] = 'Execution not found';
            redirect('/workflows');
            exit;
        }

        // Get action logs for this execution
        $actionLogs = $this->workflowModel->getActionLogs($executionId);

        // Decode JSON fields
        $execution['trigger_data'] = json_decode($execution['trigger_data'], true);
        $execution['error_message'] = $execution['error_message'] ? json_decode($execution['error_message'], true) : null;

        foreach ($actionLogs as &$log) {
            $log['result'] = json_decode($log['result'], true);
            $log['error_message'] = $log['error_message'] ? json_decode($log['error_message'], true) : null;
        }

        $data = array(
            'workflow' => $workflow,
            'execution' => $execution,
            'actionLogs' => $actionLogs
        );

        $this->view('workflows/execution_detail', $data);
    }

    /**
     * Test workflow with sample data
     */
    public function test($id)
    {
        $tenantId = $_SESSION['tenant_id'];

        $workflow = $this->workflowModel->getById($id);

        if (!$workflow || $workflow['tenant_id'] != $tenantId) {
            $this->jsonResponse(array('success' => false, 'message' => 'Workflow not found'), 404);
            return;
        }

        // Get test data from request
        $testData = json_decode(file_get_contents('php://input'), true);

        if (empty($testData)) {
            $testData = array(
                'id' => 999,
                'name' => 'Test Record',
                'email' => 'test@example.com',
                'status' => 'active'
            );
        }

        // Execute workflow with test data
        try {
            $executionId = $this->workflowEngine->executeWorkflow($workflow, $testData);

            if ($executionId) {
                $execution = $this->workflowModel->getExecutionById($executionId);
                $actionLogs = $this->workflowModel->getActionLogs($executionId);

                $this->jsonResponse(array(
                    'success' => true,
                    'message' => 'Workflow executed successfully',
                    'execution' => $execution,
                    'actions' => $actionLogs
                ));
            } else {
                $this->jsonResponse(array(
                    'success' => false,
                    'message' => 'Workflow execution failed'
                ), 500);
            }
        } catch (Exception $e) {
            $this->jsonResponse(array(
                'success' => false,
                'message' => 'Error executing workflow: ' . $e->getMessage()
            ), 500);
        }
    }

    /**
     * Get available trigger types
     */
    private function getTriggerTypes()
    {
        return array(
            'lead_created' => 'Lead Created',
            'lead_updated' => 'Lead Updated',
            'lead_status_changed' => 'Lead Status Changed',
            'client_created' => 'Client Created',
            'property_created' => 'Property Created',
            'deal_created' => 'Deal Created',
            'deal_status_changed' => 'Deal Status Changed',
            'deal_won' => 'Deal Won',
            'deal_lost' => 'Deal Lost',
            'task_created' => 'Task Created',
            'task_completed' => 'Task Completed',
            'scheduled' => 'Scheduled (Time-based)'
        );
    }

    /**
     * Get available action types
     */
    private function getActionTypes()
    {
        return array(
            'send_email' => array(
                'name' => 'Send Email',
                'icon' => 'envelope',
                'fields' => array('to', 'subject', 'body')
            ),
            'create_task' => array(
                'name' => 'Create Task',
                'icon' => 'tasks',
                'fields' => array('title', 'description', 'assigned_to', 'due_date')
            ),
            'update_field' => array(
                'name' => 'Update Field',
                'icon' => 'edit',
                'fields' => array('entity_type', 'field_name', 'field_value')
            ),
            'assign_to_user' => array(
                'name' => 'Assign to User',
                'icon' => 'user',
                'fields' => array('user_id', 'entity_type')
            ),
            'send_webhook' => array(
                'name' => 'Send Webhook',
                'icon' => 'paper-plane',
                'fields' => array('url', 'method', 'headers', 'payload')
            ),
            'send_notification' => array(
                'name' => 'Send Notification',
                'icon' => 'bell',
                'fields' => array('user_id', 'title', 'message')
            )
        );
    }

    /**
     * Get available condition operators
     */
    private function getOperators()
    {
        return array(
            'equals' => 'Equals',
            'not_equals' => 'Not Equals',
            'contains' => 'Contains',
            'not_contains' => 'Does Not Contain',
            'greater_than' => 'Greater Than',
            'less_than' => 'Less Than',
            'greater_than_or_equal' => 'Greater Than or Equal',
            'less_than_or_equal' => 'Less Than or Equal',
            'is_empty' => 'Is Empty',
            'is_not_empty' => 'Is Not Empty',
            'starts_with' => 'Starts With',
            'ends_with' => 'Ends With'
        );
    }
}
