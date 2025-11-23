<?php
// FILE: /app/controllers/LeadsController.php

/**
 * SplashEstate CRM - Leads Controller
 * Manages lead operations
 */

class LeadsController extends Controller {

    private $leadModel;
    private $userModel;

    /**
     * Constructor
     */
    public function __construct() {
        $this->leadModel = $this->model('Lead');
        $this->userModel = $this->model('User');
    }

    /**
     * List all leads
     */
    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;

        // Build advanced filters
        $filters = array();

        // Basic filters
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['source'])) $filters['source'] = $_GET['source'];
        if (!empty($_GET['interest_type'])) $filters['interest_type'] = $_GET['interest_type'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        // Budget range filters
        if (!empty($_GET['budget_min'])) $filters['budget_min'] = $_GET['budget_min'];
        if (!empty($_GET['budget_max'])) $filters['budget_max'] = $_GET['budget_max'];

        // Date range filters
        if (!empty($_GET['created_from'])) $filters['created_from'] = $_GET['created_from'];
        if (!empty($_GET['created_to'])) $filters['created_to'] = $_GET['created_to'];

        // Sorting
        if (!empty($_GET['sort'])) $filters['sort'] = $_GET['sort'];
        if (!empty($_GET['direction'])) $filters['direction'] = $_GET['direction'];

        // Check if we should use advanced search
        $useAdvancedSearch = !empty($filters['budget_min']) || !empty($filters['budget_max']) ||
                             !empty($filters['created_from']) || !empty($filters['created_to']) ||
                             !empty($filters['sort']);

        // Get leads using appropriate method
        if ($useAdvancedSearch) {
            $leads = $this->leadModel->advancedSearch($tenantId, $filters, $page, $perPage);
            $totalLeads = $this->leadModel->advancedSearchCount($tenantId, $filters);
        } else {
            $leads = $this->leadModel->getLeadsWithUser($tenantId, $page, $perPage, $filters);
            $totalLeads = $this->leadModel->count($tenantId, $filters);
        }

        $totalPages = ceil($totalLeads / $perPage);

        // Get agents for filter dropdown
        $agentsList = $this->userModel->getUsersByTenant($tenantId);
        $agents = array();
        foreach ($agentsList as $agent) {
            $agents[$agent['id']] = $agent['first_name'] . ' ' . $agent['last_name'];
        }

        // Get sources for filter
        $sources = $this->leadModel->getSources($tenantId);

        $data = array(
            'title' => 'Leads',
            'leads' => $leads,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalLeads' => $totalLeads,
            'agents' => $agents,
            'sources' => $sources,
            'filters' => $filters
        );

        $this->view('leads/index', $data);
    }

    /**
     * View single lead
     */
    public function view($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $lead = $this->leadModel->getLeadWithUser($id, $tenantId);

        if (!$lead) {
            $this->setFlash('error', 'Lead not found');
            $this->redirect('leads/index');
        }

        // Get activities
        $activityModel = $this->model('Activity');
        $activities = $activityModel->getByRelated($tenantId, 'lead', $id);

        // Get attachments
        $attachmentModel = $this->model('Attachment');
        $attachments = $attachmentModel->getByRelated($tenantId, 'lead', $id);

        $data = array(
            'title' => 'Lead Details',
            'lead' => $lead,
            'activities' => $activities,
            'attachments' => $attachments
        );

        $this->view('leads/view', $data);
    }

    /**
     * Create new lead
     */
    public function create() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        // Check quota
        if (!checkQuota($tenantId, 'leads')) {
            $this->setFlash('error', 'Lead limit reached. Please upgrade your plan.');
            $this->redirect('leads/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
            return;
        }

        // Get agents
        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Create Lead',
            'agents' => $agents,
            'errors' => array(),
            'formData' => array()
        );

        $this->view('leads/create', $data);
    }

    /**
     * Process create form
     */
    private function processCreate() {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('leads/index');
        }

        $tenantId = $this->getTenantId();

        // Sanitize input
        $formData = array(
            'first_name' => $this->sanitize($_POST['first_name']),
            'last_name' => $this->sanitize($_POST['last_name']),
            'email' => $this->sanitize($_POST['email']),
            'phone' => $this->sanitize($_POST['phone']),
            'source' => $this->sanitize($_POST['source']),
            'interest_type' => $this->sanitize($_POST['interest_type']),
            'budget_min' => $this->sanitize($_POST['budget_min']),
            'budget_max' => $this->sanitize($_POST['budget_max']),
            'assigned_to' => $this->sanitize($_POST['assigned_to']),
            'notes' => $this->sanitize($_POST['notes'])
        );

        // Validate
        $errors = validateRequired($formData, array('first_name'));

        if (!empty($formData['email']) && !$this->validateEmail($formData['email'])) {
            $errors['email'] = 'Invalid email format';
        }

        // If errors, show form
        if (!empty($errors)) {
            $agents = $this->userModel->getUsersByTenant($tenantId);
            $data = array(
                'title' => 'Create Lead',
                'agents' => $agents,
                'errors' => $errors,
                'formData' => $formData
            );
            $this->view('leads/create', $data);
            return;
        }

        // Create lead
        $formData['tenant_id'] = $tenantId;
        $formData['status'] = 'new';

        $leadId = $this->leadModel->createLead($formData);

        if ($leadId) {
            logActivity($this->getUserId(), $tenantId, 'created', 'lead', $leadId);
            $this->setFlash('success', 'Lead created successfully');
            $this->redirect('leads/view/' . $leadId);
        } else {
            $this->setFlash('error', 'Failed to create lead');
            $this->redirect('leads/create');
        }
    }

    /**
     * Edit lead
     */
    public function edit($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $lead = $this->leadModel->getById($id, $tenantId);

        if (!$lead) {
            $this->setFlash('error', 'Lead not found');
            $this->redirect('leads/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processEdit($id);
            return;
        }

        // Get agents
        $agents = $this->userModel->getUsersByTenant($tenantId);

        $data = array(
            'title' => 'Edit Lead',
            'lead' => $lead,
            'agents' => $agents,
            'errors' => array()
        );

        $this->view('leads/edit', $data);
    }

    /**
     * Process edit form
     */
    private function processEdit($id) {
        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('leads/index');
        }

        $tenantId = $this->getTenantId();

        // Sanitize input
        $formData = array(
            'first_name' => $this->sanitize($_POST['first_name']),
            'last_name' => $this->sanitize($_POST['last_name']),
            'email' => $this->sanitize($_POST['email']),
            'phone' => $this->sanitize($_POST['phone']),
            'source' => $this->sanitize($_POST['source']),
            'status' => $this->sanitize($_POST['status']),
            'interest_type' => $this->sanitize($_POST['interest_type']),
            'budget_min' => $this->sanitize($_POST['budget_min']),
            'budget_max' => $this->sanitize($_POST['budget_max']),
            'assigned_to' => $this->sanitize($_POST['assigned_to']),
            'notes' => $this->sanitize($_POST['notes'])
        );

        // Validate
        $errors = validateRequired($formData, array('first_name'));

        if (!empty($formData['email']) && !$this->validateEmail($formData['email'])) {
            $errors['email'] = 'Invalid email format';
        }

        // If errors, show form
        if (!empty($errors)) {
            $lead = $this->leadModel->getById($id, $tenantId);
            $agents = $this->userModel->getUsersByTenant($tenantId);
            $data = array(
                'title' => 'Edit Lead',
                'lead' => $lead,
                'agents' => $agents,
                'errors' => $errors
            );
            $this->view('leads/edit', $data);
            return;
        }

        // Update lead
        if ($this->leadModel->update($id, $tenantId, $formData)) {
            logActivity($this->getUserId(), $tenantId, 'updated', 'lead', $id);
            $this->setFlash('success', 'Lead updated successfully');
            $this->redirect('leads/view/' . $id);
        } else {
            $this->setFlash('error', 'Failed to update lead');
            $this->redirect('leads/edit/' . $id);
        }
    }

    /**
     * Delete lead
     */
    public function delete($id) {
        $this->requireLogin();

        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('leads/index');
        }

        $tenantId = $this->getTenantId();

        if ($this->leadModel->delete($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'deleted', 'lead', $id);
            $this->setFlash('success', 'Lead deleted successfully');
        } else {
            $this->setFlash('error', 'Failed to delete lead');
        }

        $this->redirect('leads/index');
    }

    /**
     * Convert lead to client
     */
    public function convert($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        $clientId = $this->leadModel->convertToClient($id, $tenantId);

        if ($clientId) {
            logActivity($this->getUserId(), $tenantId, 'converted', 'lead', $id);
            $this->setFlash('success', 'Lead converted to client successfully');
            $this->redirect('clients/view/' . $clientId);
        } else {
            $this->setFlash('error', 'Failed to convert lead');
            $this->redirect('leads/view/' . $id);
        }
    }
}
