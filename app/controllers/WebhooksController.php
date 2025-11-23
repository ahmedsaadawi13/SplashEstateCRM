<?php
// FILE: /app/controllers/WebhooksController.php

/**
 * SplashEstate CRM - Webhooks Controller
 * Manages webhook configurations and logs
 */

class WebhooksController extends Controller {

    private $webhookModel;
    private $logModel;
    private $webhookService;

    public function __construct() {
        $this->webhookModel = $this->model('Webhook');
        $this->logModel = $this->model('WebhookLog');

        require_once APP_PATH . '/helpers/WebhookService.php';
        $this->webhookService = new WebhookService();
    }

    /**
     * List webhooks
     */
    public function index() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();
        $webhooks = $this->webhookModel->getAll($tenantId);

        // Get stats for each webhook
        foreach ($webhooks as &$webhook) {
            $webhook['stats'] = $this->webhookModel->getStats($webhook['id']);
        }

        $data = array(
            'title' => 'Webhooks',
            'webhooks' => $webhooks
        );

        $this->view('webhooks/index', $data);
    }

    /**
     * Create webhook
     */
    public function create() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
            return;
        }

        $data = array(
            'title' => 'Create Webhook',
            'availableEvents' => $this->webhookModel->getAvailableEvents(),
            'errors' => array(),
            'formData' => array()
        );

        $this->view('webhooks/create', $data);
    }

    /**
     * Process create form
     */
    private function processCreate() {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('webhooks/index');
            return;
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'name' => $this->sanitize($_POST['name']),
            'url' => $this->sanitize($_POST['url']),
            'events' => isset($_POST['events']) ? implode(',', $_POST['events']) : '',
            'secret' => !empty($_POST['secret']) ? $this->sanitize($_POST['secret']) : $this->webhookModel->generateSecret(),
            'status' => isset($_POST['status']) ? $this->sanitize($_POST['status']) : 'active',
            'retry_enabled' => isset($_POST['retry_enabled']) ? 1 : 0,
            'max_retries' => (int)$_POST['max_retries']
        );

        $errors = validateRequired($formData, array('name', 'url', 'events'));

        // Validate URL
        if (!filter_var($formData['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Please enter a valid URL';
        }

        if (!empty($errors)) {
            $data = array(
                'title' => 'Create Webhook',
                'availableEvents' => $this->webhookModel->getAvailableEvents(),
                'errors' => $errors,
                'formData' => $formData
            );
            $this->view('webhooks/create', $data);
            return;
        }

        $formData['tenant_id'] = $tenantId;
        $formData['created_by'] = $this->getUserId();

        $webhookId = $this->webhookModel->create($formData);

        if ($webhookId) {
            logActivity($this->getUserId(), $tenantId, 'created', 'webhook', $webhookId);
            $this->setFlash('success', 'Webhook created successfully');
            $this->redirect('webhooks/view/' . $webhookId);
        } else {
            $this->setFlash('error', 'Failed to create webhook');
            $this->redirect('webhooks/create');
        }
    }

    /**
     * View webhook
     */
    public function view($id) {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();
        $webhook = $this->webhookModel->getById($id, $tenantId);

        if (!$webhook) {
            $this->setFlash('error', 'Webhook not found');
            $this->redirect('webhooks/index');
            return;
        }

        // Get logs
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $logs = $this->logModel->getByWebhook($id, $perPage, $offset);
        $statusCounts = $this->logModel->countByStatus($id);

        $data = array(
            'title' => 'Webhook Details',
            'webhook' => $webhook,
            'logs' => $logs,
            'statusCounts' => $statusCounts,
            'currentPage' => $page
        );

        $this->view('webhooks/view', $data);
    }

    /**
     * Edit webhook
     */
    public function edit($id) {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();
        $webhook = $this->webhookModel->getById($id, $tenantId);

        if (!$webhook) {
            $this->setFlash('error', 'Webhook not found');
            $this->redirect('webhooks/index');
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processEdit($id);
            return;
        }

        $data = array(
            'title' => 'Edit Webhook',
            'webhook' => $webhook,
            'availableEvents' => $this->webhookModel->getAvailableEvents(),
            'errors' => array()
        );

        $this->view('webhooks/edit', $data);
    }

    /**
     * Process edit form
     */
    private function processEdit($id) {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('webhooks/index');
            return;
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'name' => $this->sanitize($_POST['name']),
            'url' => $this->sanitize($_POST['url']),
            'events' => isset($_POST['events']) ? implode(',', $_POST['events']) : '',
            'status' => $this->sanitize($_POST['status']),
            'retry_enabled' => isset($_POST['retry_enabled']) ? 1 : 0,
            'max_retries' => (int)$_POST['max_retries']
        );

        // Only update secret if provided
        if (!empty($_POST['secret'])) {
            $formData['secret'] = $this->sanitize($_POST['secret']);
        }

        $errors = validateRequired($formData, array('name', 'url', 'events'));

        if (!filter_var($formData['url'], FILTER_VALIDATE_URL)) {
            $errors['url'] = 'Please enter a valid URL';
        }

        if (!empty($errors)) {
            $webhook = $this->webhookModel->getById($id, $tenantId);
            $data = array(
                'title' => 'Edit Webhook',
                'webhook' => $webhook,
                'availableEvents' => $this->webhookModel->getAvailableEvents(),
                'errors' => $errors
            );
            $this->view('webhooks/edit', $data);
            return;
        }

        if ($this->webhookModel->update($id, $tenantId, $formData)) {
            logActivity($this->getUserId(), $tenantId, 'updated', 'webhook', $id);
            $this->setFlash('success', 'Webhook updated successfully');
            $this->redirect('webhooks/view/' . $id);
        } else {
            $this->setFlash('error', 'Failed to update webhook');
            $this->redirect('webhooks/edit/' . $id);
        }
    }

    /**
     * Delete webhook
     */
    public function delete($id) {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('webhooks/index');
            return;
        }

        $tenantId = $this->getTenantId();

        if ($this->webhookModel->delete($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'deleted', 'webhook', $id);
            $this->setFlash('success', 'Webhook deleted successfully');
        } else {
            $this->setFlash('error', 'Failed to delete webhook');
        }

        $this->redirect('webhooks/index');
    }

    /**
     * Test webhook endpoint
     */
    public function test($id) {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();
        $webhook = $this->webhookModel->getById($id, $tenantId);

        if (!$webhook) {
            $this->jsonResponse(array('error' => 'Webhook not found'), 404);
            return;
        }

        $result = $this->webhookService->testEndpoint($webhook['url'], $webhook['secret']);

        $this->jsonResponse($result);
    }

    /**
     * View webhook logs
     */
    public function logs() {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        $tenantId = $this->getTenantId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $logs = $this->logModel->getByTenant($tenantId, $perPage, $offset);

        $data = array(
            'title' => 'Webhook Logs',
            'logs' => $logs,
            'currentPage' => $page
        );

        $this->view('webhooks/logs', $data);
    }

    /**
     * Regenerate secret
     */
    public function regenerateSecret($id) {
        $this->requireLogin();
        $this->requireRole('tenant_admin');

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->jsonResponse(array('error' => 'Invalid request'), 400);
            return;
        }

        $tenantId = $this->getTenantId();
        $webhook = $this->webhookModel->getById($id, $tenantId);

        if (!$webhook) {
            $this->jsonResponse(array('error' => 'Webhook not found'), 404);
            return;
        }

        $newSecret = $this->webhookModel->generateSecret();

        if ($this->webhookModel->update($id, $tenantId, array('secret' => $newSecret))) {
            logActivity($this->getUserId(), $tenantId, 'regenerated_secret', 'webhook', $id);
            $this->jsonResponse(array(
                'success' => true,
                'secret' => $newSecret
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to regenerate secret'), 500);
        }
    }
}
