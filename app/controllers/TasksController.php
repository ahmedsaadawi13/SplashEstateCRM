<?php
// FILE: /app/controllers/TasksController.php

/**
 * SplashEstate CRM - Tasks Controller
 */

class TasksController extends Controller {

    private $taskModel;
    private $userModel;

    public function __construct() {
        $this->taskModel = $this->model('Task');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;

        $filters = array();
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['priority'])) $filters['priority'] = $_GET['priority'];

        $tasks = $this->taskModel->getTasksWithInfo($tenantId, $page, $perPage, $filters);
        $totalTasks = $this->taskModel->count($tenantId, $filters);
        $totalPages = ceil($totalTasks / $perPage);

        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Tasks',
            'tasks' => $tasks,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'agents' => $agents,
            'filters' => $filters
        );

        $this->view('tasks/index', $data);
    }

    public function view($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $task = $this->taskModel->getById($id, $tenantId);

        if (!$task) {
            $this->setFlash('error', 'Task not found');
            $this->redirect('tasks/index');
        }

        $data = array(
            'title' => 'Task Details',
            'task' => $task
        );

        $this->view('tasks/view', $data);
    }

    public function create() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
            return;
        }

        $tenantId = $this->getTenantId();
        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Create Task',
            'agents' => $agents,
            'errors' => array(),
            'formData' => array()
        );

        $this->view('tasks/create', $data);
    }

    private function processCreate() {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('tasks/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'title' => $this->sanitize($_POST['title']),
            'description' => $this->sanitize($_POST['description']),
            'assigned_to' => $this->sanitize($_POST['assigned_to']),
            'due_date' => $this->sanitize($_POST['due_date']),
            'priority' => $this->sanitize($_POST['priority'])
        );

        $errors = validateRequired($formData, array('title'));

        if (!empty($errors)) {
            $agents = $this->userModel->getUsersByTenant($tenantId);
            $data = array('title' => 'Create Task', 'agents' => $agents, 'errors' => $errors, 'formData' => $formData);
            $this->view('tasks/create', $data);
            return;
        }

        $formData['tenant_id'] = $tenantId;
        $formData['created_by'] = $this->getUserId();
        $formData['status'] = 'pending';

        $taskId = $this->taskModel->createTask($formData);

        if ($taskId) {
            logActivity($this->getUserId(), $tenantId, 'created', 'task', $taskId);
            $this->setFlash('success', 'Task created successfully');
            $this->redirect('tasks/view/' . $taskId);
        } else {
            $this->setFlash('error', 'Failed to create task');
            $this->redirect('tasks/create');
        }
    }

    public function complete($id) {
        $this->requireLogin();

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('tasks/index');
        }

        $tenantId = $this->getTenantId();

        if ($this->taskModel->markCompleted($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'completed', 'task', $id);
            $this->setFlash('success', 'Task marked as completed');
        } else {
            $this->setFlash('error', 'Failed to update task');
        }

        $this->redirect('tasks/index');
    }

    public function delete($id) {
        $this->requireLogin();

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('tasks/index');
        }

        $tenantId = $this->getTenantId();

        if ($this->taskModel->delete($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'deleted', 'task', $id);
            $this->setFlash('success', 'Task deleted successfully');
        } else {
            $this->setFlash('error', 'Failed to delete task');
        }

        $this->redirect('tasks/index');
    }
}
