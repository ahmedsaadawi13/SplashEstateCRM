<?php
// FILE: /app/controllers/ExportController.php

/**
 * SplashEstate CRM - Export Controller
 * Handles data export requests
 */

class ExportController extends Controller {

    private $exportService;

    public function __construct() {
        require_once APP_PATH . '/helpers/ExportService.php';
        $this->exportService = new ExportService();
    }

    /**
     * Export leads to CSV
     */
    public function leadsCSV() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $leadModel = $this->model('Lead');

        // Get filters from request
        $filters = array();
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        // Get all leads (no pagination for export)
        $leads = $leadModel->getLeadsWithUser($tenantId, 1, 10000, $filters);

        // Export to CSV
        $filename = 'leads_' . date('Y-m-d_His') . '.csv';
        $this->exportService->exportLeadsToCSV($leads, $filename);
    }

    /**
     * Export leads to PDF
     */
    public function leadsPDF() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $leadModel = $this->model('Lead');

        // Get filters from request
        $filters = array();
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];

        // Get all leads
        $leads = $leadModel->getLeadsWithUser($tenantId, 1, 10000, $filters);

        // Generate HTML
        $html = $this->exportService->generateLeadsReportHTML($leads);

        // Export to PDF
        $filename = 'leads_report_' . date('Y-m-d') . '.pdf';
        $this->exportService->exportToPDF($html, $filename);
    }

    /**
     * Export clients to CSV
     */
    public function clientsCSV() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $clientModel = $this->model('Client');

        // Get filters from request
        $filters = array();
        if (!empty($_GET['client_type'])) $filters['client_type'] = $_GET['client_type'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        // Get all clients
        $clients = $clientModel->getClientsWithInfo($tenantId, 1, 10000, $filters);

        // Export to CSV
        $filename = 'clients_' . date('Y-m-d_His') . '.csv';
        $this->exportService->exportClientsToCSV($clients, $filename);
    }

    /**
     * Export properties to CSV
     */
    public function propertiesCSV() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $propertyModel = $this->model('Property');

        // Get filters from request
        $filters = array();
        if (!empty($_GET['property_type'])) $filters['property_type'] = $_GET['property_type'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        // Get all properties
        $properties = $propertyModel->getPropertiesWithInfo($tenantId, 1, 10000, $filters);

        // Export to CSV
        $filename = 'properties_' . date('Y-m-d_His') . '.csv';
        $this->exportService->exportPropertiesToCSV($properties, $filename);
    }

    /**
     * Export properties to PDF
     */
    public function propertiesPDF() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $propertyModel = $this->model('Property');

        // Get filters
        $filters = array();
        if (!empty($_GET['property_type'])) $filters['property_type'] = $_GET['property_type'];

        // Get all properties
        $properties = $propertyModel->getPropertiesWithInfo($tenantId, 1, 10000, $filters);

        // Generate HTML
        $html = $this->exportService->generatePropertiesReportHTML($properties);

        // Export to PDF
        $filename = 'properties_report_' . date('Y-m-d') . '.pdf';
        $this->exportService->exportToPDF($html, $filename, 'landscape');
    }

    /**
     * Export deals to CSV
     */
    public function dealsCSV() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $dealModel = $this->model('Deal');

        // Get filters from request
        $filters = array();
        if (!empty($_GET['stage'])) $filters['stage'] = $_GET['stage'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        // Get all deals
        $deals = $dealModel->getDealsWithInfo($tenantId, 1, 10000, $filters);

        // Export to CSV
        $filename = 'deals_' . date('Y-m-d_His') . '.csv';
        $this->exportService->exportDealsToCSV($deals, $filename);
    }

    /**
     * Export tasks to CSV
     */
    public function tasksCSV() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $taskModel = $this->model('Task');

        // Get filters from request
        $filters = array();
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['assigned_to'])) $filters['assigned_to'] = $_GET['assigned_to'];

        // Get all tasks
        $tasks = $taskModel->getTasksWithInfo($tenantId, 1, 10000, $filters);

        // Prepare data
        $headers = array(
            'id' => 'ID',
            'title' => 'Title',
            'description' => 'Description',
            'assigned_to_name' => 'Assigned To',
            'due_date' => 'Due Date',
            'priority' => 'Priority',
            'status' => 'Status',
            'created_at' => 'Created Date'
        );

        $filename = 'tasks_' . date('Y-m-d_His') . '.csv';
        $this->exportService->exportToCSV($tasks, $headers, $filename);
    }
}
