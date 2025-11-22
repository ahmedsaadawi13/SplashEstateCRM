<?php
// FILE: /app/controllers/api/LeadsApiController.php

/**
 * SplashEstate CRM - Leads API Controller
 * REST API for managing leads via API
 */

class LeadsApiController {

    private $leadModel;

    /**
     * Constructor
     */
    public function __construct() {
        header('Content-Type: application/json');

        // Load models manually
        require_once '../app/core/Database.php';
        require_once '../app/core/Model.php';
        require_once '../app/models/Lead.php';

        $this->leadModel = new Lead();
    }

    /**
     * Create a new lead via API
     */
    public function create() {
        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Method not allowed'), 405);
            return;
        }

        // Verify API key
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            $this->jsonResponse(array('error' => 'API key is required'), 401);
            return;
        }

        $tenant = verifyApiKey($apiKey);
        if (!$tenant) {
            $this->jsonResponse(array('error' => 'Invalid API key'), 401);
            return;
        }

        // Check if tenant is active
        if ($tenant['status'] !== 'active') {
            $this->jsonResponse(array('error' => 'Tenant account is not active'), 403);
            return;
        }

        // Check quota
        if (!checkQuota($tenant['id'], 'leads')) {
            $this->jsonResponse(array('error' => 'Lead limit reached. Please upgrade your plan.'), 429);
            return;
        }

        // Get JSON input
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        if (!$data) {
            $this->jsonResponse(array('error' => 'Invalid JSON input'), 400);
            return;
        }

        // Validate required fields
        $requiredFields = array('first_name');
        $errors = array();

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        // Validate email if provided
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email format';
        }

        if (!empty($errors)) {
            $this->jsonResponse(array('error' => 'Validation failed', 'errors' => $errors), 422);
            return;
        }

        // Sanitize and prepare lead data
        $leadData = array(
            'tenant_id' => $tenant['id'],
            'first_name' => $this->sanitize($data['first_name']),
            'last_name' => isset($data['last_name']) ? $this->sanitize($data['last_name']) : null,
            'email' => isset($data['email']) ? $this->sanitize($data['email']) : null,
            'phone' => isset($data['phone']) ? $this->sanitize($data['phone']) : null,
            'source' => 'API',
            'status' => 'new',
            'interest_type' => isset($data['interest_type']) ? $this->sanitize($data['interest_type']) : null,
            'budget_min' => isset($data['budget_min']) ? $this->sanitize($data['budget_min']) : null,
            'budget_max' => isset($data['budget_max']) ? $this->sanitize($data['budget_max']) : null,
            'notes' => isset($data['notes']) ? $this->sanitize($data['notes']) : null,
            'assigned_to' => isset($data['assigned_to']) ? (int)$data['assigned_to'] : null
        );

        // Create lead
        $leadId = $this->leadModel->createLead($leadData);

        if ($leadId) {
            $lead = $this->leadModel->getById($leadId, $tenant['id']);

            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Lead created successfully',
                'data' => array(
                    'lead_id' => $leadId,
                    'lead' => $lead
                )
            ), 201);
        } else {
            $this->jsonResponse(array('error' => 'Failed to create lead'), 500);
        }
    }

    /**
     * List leads (optional - for retrieving leads via API)
     */
    public function index() {
        // Only accept GET requests
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            $this->jsonResponse(array('error' => 'Method not allowed'), 405);
            return;
        }

        // Verify API key
        $apiKey = $this->getApiKey();
        if (!$apiKey) {
            $this->jsonResponse(array('error' => 'API key is required'), 401);
            return;
        }

        $tenant = verifyApiKey($apiKey);
        if (!$tenant) {
            $this->jsonResponse(array('error' => 'Invalid API key'), 401);
            return;
        }

        // Get parameters
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 20;
        $perPage = min($perPage, 100); // Max 100 per page

        $filters = array();
        if (!empty($_GET['status'])) {
            $filters['status'] = $_GET['status'];
        }

        // Get leads
        $leads = $this->leadModel->getAll($tenant['id'], $page, $perPage, $filters);
        $total = $this->leadModel->count($tenant['id'], $filters);

        $this->jsonResponse(array(
            'success' => true,
            'data' => array(
                'leads' => $leads,
                'pagination' => array(
                    'page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'total_pages' => ceil($total / $perPage)
                )
            )
        ), 200);
    }

    /**
     * Get API key from headers
     * @return string|null
     */
    private function getApiKey() {
        $headers = apache_request_headers();

        // Check for X-API-KEY header
        if (isset($headers['X-API-KEY'])) {
            return $headers['X-API-KEY'];
        }

        // Also check lowercase version
        if (isset($headers['x-api-key'])) {
            return $headers['x-api-key'];
        }

        return null;
    }

    /**
     * Sanitize input
     * @param string $data Data to sanitize
     * @return string Sanitized data
     */
    private function sanitize($data) {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Return JSON response
     * @param array $data Data to encode
     * @param int $statusCode HTTP status code
     */
    private function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode($data);
        exit;
    }
}
