<?php
// FILE: /app/controllers/DealsController.php

/**
 * SplashEstate CRM - Deals Controller
 */

class DealsController extends Controller {

    private $dealModel;
    private $clientModel;
    private $propertyModel;
    private $userModel;

    public function __construct() {
        $this->dealModel = $this->model('Deal');
        $this->clientModel = $this->model('Client');
        $this->propertyModel = $this->model('Property');
        $this->userModel = $this->model('User');
    }

    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;

        $filters = array();
        if (!empty($_GET['stage'])) $filters['stage'] = $_GET['stage'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        $deals = $this->dealModel->getDealsWithInfo($tenantId, $page, $perPage, $filters);
        $totalDeals = $this->dealModel->count($tenantId, $filters);
        $totalPages = ceil($totalDeals / $perPage);

        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Deals',
            'deals' => $deals,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'agents' => $agents,
            'filters' => $filters
        );

        $this->view('deals/index', $data);
    }

    public function view($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $deal = $this->dealModel->getById($id, $tenantId);

        if (!$deal) {
            $this->setFlash('error', 'Deal not found');
            $this->redirect('deals/index');
        }

        $data = array(
            'title' => 'Deal Details',
            'deal' => $deal
        );

        $this->view('deals/view', $data);
    }

    public function create() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
            return;
        }

        $tenantId = $this->getTenantId();
        $clients = $this->clientModel->getAll($tenantId, 1, 100);
        $properties = $this->propertyModel->getAll($tenantId, 1, 100);
        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Create Deal',
            'clients' => $clients,
            'properties' => $properties,
            'agents' => $agents,
            'errors' => array(),
            'formData' => array()
        );

        $this->view('deals/create', $data);
    }

    private function processCreate() {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('deals/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'title' => $this->sanitize($_POST['title']),
            'client_id' => $this->sanitize($_POST['client_id']),
            'property_id' => $this->sanitize($_POST['property_id']),
            'assigned_to' => $this->sanitize($_POST['assigned_to']),
            'deal_value' => $this->sanitize($_POST['deal_value']),
            'commission' => $this->sanitize($_POST['commission']),
            'stage' => $this->sanitize($_POST['stage']),
            'probability' => $this->sanitize($_POST['probability']),
            'expected_close_date' => $this->sanitize($_POST['expected_close_date']),
            'notes' => $this->sanitize($_POST['notes'])
        );

        $errors = validateRequired($formData, array('title'));

        if (!empty($errors)) {
            $clients = $this->clientModel->getAll($tenantId, 1, 100);
            $properties = $this->propertyModel->getAll($tenantId, 1, 100);
            $agents = $this->userModel->getUsersByTenant($tenantId);
            $data = array('title' => 'Create Deal', 'clients' => $clients, 'properties' => $properties, 'agents' => $agents, 'errors' => $errors, 'formData' => $formData);
            $this->view('deals/create', $data);
            return;
        }

        $formData['tenant_id'] = $tenantId;

        $dealId = $this->dealModel->createDeal($formData);

        if ($dealId) {
            logActivity($this->getUserId(), $tenantId, 'created', 'deal', $dealId);
            $this->setFlash('success', 'Deal created successfully');
            $this->redirect('deals/view/' . $dealId);
        } else {
            $this->setFlash('error', 'Failed to create deal');
            $this->redirect('deals/create');
        }
    }

    public function edit($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $deal = $this->dealModel->getById($id, $tenantId);

        if (!$deal) {
            $this->setFlash('error', 'Deal not found');
            $this->redirect('deals/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processEdit($id);
            return;
        }

        $clients = $this->clientModel->getAll($tenantId, 1, 100);
        $properties = $this->propertyModel->getAll($tenantId, 1, 100);
        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array('title' => 'Edit Deal', 'deal' => $deal, 'clients' => $clients, 'properties' => $properties, 'agents' => $agents, 'errors' => array());
        $this->view('deals/edit', $data);
    }

    private function processEdit($id) {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('deals/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'title' => $this->sanitize($_POST['title']),
            'client_id' => $this->sanitize($_POST['client_id']),
            'property_id' => $this->sanitize($_POST['property_id']),
            'assigned_to' => $this->sanitize($_POST['assigned_to']),
            'deal_value' => $this->sanitize($_POST['deal_value']),
            'commission' => $this->sanitize($_POST['commission']),
            'stage' => $this->sanitize($_POST['stage']),
            'probability' => $this->sanitize($_POST['probability']),
            'expected_close_date' => $this->sanitize($_POST['expected_close_date']),
            'notes' => $this->sanitize($_POST['notes'])
        );

        if ($this->dealModel->update($id, $tenantId, $formData)) {
            logActivity($this->getUserId(), $tenantId, 'updated', 'deal', $id);
            $this->setFlash('success', 'Deal updated successfully');
            $this->redirect('deals/view/' . $id);
        } else {
            $this->setFlash('error', 'Failed to update deal');
            $this->redirect('deals/edit/' . $id);
        }
    }

    public function delete($id) {
        $this->requireLogin();

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('deals/index');
        }

        $tenantId = $this->getTenantId();

        if ($this->dealModel->delete($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'deleted', 'deal', $id);
            $this->setFlash('success', 'Deal deleted successfully');
        } else {
            $this->setFlash('error', 'Failed to delete deal');
        }

        $this->redirect('deals/index');
    }
}
