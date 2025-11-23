<?php
// FILE: /app/controllers/CalendarController.php

/**
 * SplashEstate CRM - Calendar Controller
 * Handles calendar view and task scheduling
 */

class CalendarController extends Controller {

    private $taskModel;

    public function __construct() {
        $this->taskModel = $this->model('Task');
    }

    /**
     * Main calendar view
     */
    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $userId = $_SESSION['user_id'];

        // Get users for filter
        $userModel = $this->model('User');
        $users = $userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Calendar',
            'users' => $users
        );

        $this->view('calendar/index', $data);
    }

    /**
     * Get tasks for calendar (JSON API)
     */
    public function getTasks() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $userId = $_SESSION['user_id'];

        // Get date range from request
        $start = isset($_GET['start']) ? $_GET['start'] : null;
        $end = isset($_GET['end']) ? $_GET['end'] : null;

        // Get filter parameters
        $assignedTo = isset($_GET['assigned_to']) ? $_GET['assigned_to'] : null;
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $priority = isset($_GET['priority']) ? $_GET['priority'] : null;

        // Build filters
        $filters = array();
        if ($assignedTo) {
            $filters['assigned_to'] = $assignedTo;
        }
        if ($status) {
            $filters['status'] = $status;
        }
        if ($priority) {
            $filters['priority'] = $priority;
        }

        // Get tasks within date range
        $tasks = $this->getTasksInDateRange($tenantId, $start, $end, $filters);

        // Format tasks for FullCalendar
        $events = array();
        foreach ($tasks as $task) {
            $events[] = array(
                'id' => $task['id'],
                'title' => $task['title'],
                'start' => $task['due_date'],
                'end' => $task['due_date'],
                'backgroundColor' => $this->getTaskColor($task['priority'], $task['status']),
                'borderColor' => $this->getTaskColor($task['priority'], $task['status']),
                'extendedProps' => array(
                    'description' => $task['description'],
                    'status' => $task['status'],
                    'priority' => $task['priority'],
                    'assigned_to' => $task['assigned_to'],
                    'assigned_to_name' => $task['assigned_to_name'],
                    'related_to' => $task['related_to'],
                    'related_type' => $task['related_type']
                )
            );
        }

        $this->jsonResponse($events);
    }

    /**
     * Update task due date (drag and drop)
     */
    public function updateTaskDate() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['task_id']) || empty($input['new_date'])) {
            $this->jsonResponse(array('error' => 'Missing required parameters'), 400);
            return;
        }

        $taskId = $input['task_id'];
        $newDate = $input['new_date'];

        // Verify task belongs to tenant
        $task = $this->taskModel->getById($taskId, $tenantId);
        if (!$task) {
            $this->jsonResponse(array('error' => 'Task not found'), 404);
            return;
        }

        // Update due date
        $sql = "UPDATE tasks SET due_date = :due_date, updated_at = NOW()
                WHERE id = :id AND tenant_id = :tenant_id";

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare($sql);
            $success = $stmt->execute(array(
                ':due_date' => $newDate,
                ':id' => $taskId,
                ':tenant_id' => $tenantId
            ));

            if ($success) {
                // Log activity
                logActivity($tenantId, $_SESSION['user_id'], 'task_rescheduled', 'task', $taskId);

                $this->jsonResponse(array(
                    'success' => true,
                    'message' => 'Task rescheduled successfully'
                ));
            } else {
                $this->jsonResponse(array('error' => 'Failed to update task'), 500);
            }
        } catch(PDOException $e) {
            error_log("Update task date error: " . $e->getMessage());
            $this->jsonResponse(array('error' => 'Database error'), 500);
        }
    }

    /**
     * Create new task from calendar
     */
    public function createTask() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['title']) || empty($input['due_date'])) {
            $this->jsonResponse(array('error' => 'Missing required fields'), 400);
            return;
        }

        $taskData = array(
            'tenant_id' => $tenantId,
            'title' => $input['title'],
            'description' => isset($input['description']) ? $input['description'] : null,
            'due_date' => $input['due_date'],
            'priority' => isset($input['priority']) ? $input['priority'] : 'normal',
            'status' => 'pending',
            'assigned_to' => isset($input['assigned_to']) ? $input['assigned_to'] : $_SESSION['user_id'],
            'related_type' => isset($input['related_type']) ? $input['related_type'] : null,
            'related_id' => isset($input['related_id']) ? $input['related_id'] : null
        );

        $taskId = $this->taskModel->create($taskData);

        if ($taskId) {
            // Get the created task
            $task = $this->taskModel->getById($taskId, $tenantId);

            $this->jsonResponse(array(
                'success' => true,
                'task_id' => $taskId,
                'task' => $task,
                'message' => 'Task created successfully'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to create task'), 500);
        }
    }

    /**
     * Get tasks in date range
     */
    private function getTasksInDateRange($tenantId, $start, $end, $filters = array()) {
        $sql = "SELECT t.*,
                CONCAT(u.first_name, ' ', u.last_name) as assigned_to_name
                FROM tasks t
                LEFT JOIN users u ON t.assigned_to = u.id
                WHERE t.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        if ($start) {
            $sql .= " AND t.due_date >= :start_date";
            $params[':start_date'] = $start;
        }

        if ($end) {
            $sql .= " AND t.due_date <= :end_date";
            $params[':end_date'] = $end;
        }

        // Apply filters
        if (!empty($filters['assigned_to'])) {
            $sql .= " AND t.assigned_to = :assigned_to";
            $params[':assigned_to'] = $filters['assigned_to'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND t.status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['priority'])) {
            $sql .= " AND t.priority = :priority";
            $params[':priority'] = $filters['priority'];
        }

        $sql .= " ORDER BY t.due_date ASC";

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get tasks in date range error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get color for task based on priority and status
     */
    private function getTaskColor($priority, $status) {
        // If completed, use green
        if ($status === 'completed') {
            return '#27ae60';
        }

        // Color by priority
        switch ($priority) {
            case 'urgent':
                return '#e74c3c'; // Red
            case 'high':
                return '#f39c12'; // Orange
            case 'normal':
                return '#3498db'; // Blue
            case 'low':
                return '#95a5a6'; // Gray
            default:
                return '#3498db';
        }
    }
}
