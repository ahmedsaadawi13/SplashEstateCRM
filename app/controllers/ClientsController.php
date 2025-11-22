<?php
// FILE: /app/controllers/ClientsController.php

/**
 * SplashEstate CRM - Clients Controller
 * Manages client operations
 */

class ClientsController extends Controller {

    private $clientModel;

    public function __construct() {
        $this->clientModel = $this->model('Client');
    }

    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;

        $filters = array();
        if (!empty($_GET['client_type'])) $filters['client_type'] = $_GET['client_type'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        $clients = $this->clientModel->getClientsWithInfo($tenantId, $page, $perPage, $filters);
        $totalClients = $this->clientModel->count($tenantId, $filters);
        $totalPages = ceil($totalClients / $perPage);

        $data = array(
            'title' => 'Clients',
            'clients' => $clients,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters
        );

        $this->view('clients/index', $data);
    }

    public function view($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $client = $this->clientModel->getById($id, $tenantId);

        if (!$client) {
            $this->setFlash('error', 'Client not found');
            $this->redirect('clients/index');
        }

        $activityModel = $this->model('Activity');
        $activities = $activityModel->getByRelated($tenantId, 'client', $id);

        $attachmentModel = $this->model('Attachment');
        $attachments = $attachmentModel->getByRelated($tenantId, 'client', $id);

        $data = array(
            'title' => 'Client Details',
            'client' => $client,
            'activities' => $activities,
            'attachments' => $attachments
        );

        $this->view('clients/view', $data);
    }

    public function create() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
            return;
        }

        $data = array(
            'title' => 'Create Client',
            'errors' => array(),
            'formData' => array()
        );

        $this->view('clients/create', $data);
    }

    private function processCreate() {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('clients/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'first_name' => $this->sanitize($_POST['first_name']),
            'last_name' => $this->sanitize($_POST['last_name']),
            'email' => $this->sanitize($_POST['email']),
            'phone' => $this->sanitize($_POST['phone']),
            'phone_secondary' => $this->sanitize($_POST['phone_secondary']),
            'address' => $this->sanitize($_POST['address']),
            'city' => $this->sanitize($_POST['city']),
            'state' => $this->sanitize($_POST['state']),
            'zip' => $this->sanitize($_POST['zip']),
            'client_type' => $this->sanitize($_POST['client_type']),
            'notes' => $this->sanitize($_POST['notes'])
        );

        $errors = validateRequired($formData, array('first_name', 'client_type'));

        if (!empty($formData['email']) && !$this->validateEmail($formData['email'])) {
            $errors['email'] = 'Invalid email format';
        }

        if (!empty($errors)) {
            $data = array('title' => 'Create Client', 'errors' => $errors, 'formData' => $formData);
            $this->view('clients/create', $data);
            return;
        }

        $formData['tenant_id'] = $tenantId;
        $formData['status'] = 'active';

        $clientId = $this->clientModel->createClient($formData);

        if ($clientId) {
            logActivity($this->getUserId(), $tenantId, 'created', 'client', $clientId);
            $this->setFlash('success', 'Client created successfully');
            $this->redirect('clients/view/' . $clientId);
        } else {
            $this->setFlash('error', 'Failed to create client');
            $this->redirect('clients/create');
        }
    }

    public function edit($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $client = $this->clientModel->getById($id, $tenantId);

        if (!$client) {
            $this->setFlash('error', 'Client not found');
            $this->redirect('clients/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processEdit($id);
            return;
        }

        $data = array('title' => 'Edit Client', 'client' => $client, 'errors' => array());
        $this->view('clients/edit', $data);
    }

    private function processEdit($id) {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('clients/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'first_name' => $this->sanitize($_POST['first_name']),
            'last_name' => $this->sanitize($_POST['last_name']),
            'email' => $this->sanitize($_POST['email']),
            'phone' => $this->sanitize($_POST['phone']),
            'phone_secondary' => $this->sanitize($_POST['phone_secondary']),
            'address' => $this->sanitize($_POST['address']),
            'city' => $this->sanitize($_POST['city']),
            'state' => $this->sanitize($_POST['state']),
            'zip' => $this->sanitize($_POST['zip']),
            'client_type' => $this->sanitize($_POST['client_type']),
            'status' => $this->sanitize($_POST['status']),
            'notes' => $this->sanitize($_POST['notes'])
        );

        $errors = validateRequired($formData, array('first_name', 'client_type'));

        if (!empty($errors)) {
            $client = $this->clientModel->getById($id, $tenantId);
            $data = array('title' => 'Edit Client', 'client' => $client, 'errors' => $errors);
            $this->view('clients/edit', $data);
            return;
        }

        if ($this->clientModel->update($id, $tenantId, $formData)) {
            logActivity($this->getUserId(), $tenantId, 'updated', 'client', $id);
            $this->setFlash('success', 'Client updated successfully');
            $this->redirect('clients/view/' . $id);
        } else {
            $this->setFlash('error', 'Failed to update client');
            $this->redirect('clients/edit/' . $id);
        }
    }

    public function delete($id) {
        $this->requireLogin();

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('clients/index');
        }

        $tenantId = $this->getTenantId();

        if ($this->clientModel->delete($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'deleted', 'client', $id);
            $this->setFlash('success', 'Client deleted successfully');
        } else {
            $this->setFlash('error', 'Failed to delete client');
        }

        $this->redirect('clients/index');
    }
}
