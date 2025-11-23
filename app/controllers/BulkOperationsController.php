<?php
// FILE: /app/controllers/BulkOperationsController.php

/**
 * SplashEstate CRM - Bulk Operations Controller
 * Handles bulk actions on multiple records
 */

class BulkOperationsController extends Controller {

    private $bulkService;

    public function __construct() {
        require_once APP_PATH . '/helpers/BulkOperationService.php';
        $this->bulkService = new BulkOperationService();
    }

    /**
     * Bulk delete records
     */
    public function delete() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['ids']) || empty($input['entity_type'])) {
            $this->jsonResponse(array('error' => 'Missing required parameters'), 400);
            return;
        }

        $table = $this->getTableName($input['entity_type']);
        if (!$table) {
            $this->jsonResponse(array('error' => 'Invalid entity type'), 400);
            return;
        }

        $result = $this->bulkService->bulkDelete($table, $input['ids'], $tenantId);

        if ($result['success']) {
            $this->jsonResponse($result);
        } else {
            $this->jsonResponse($result, 500);
        }
    }

    /**
     * Bulk update status
     */
    public function updateStatus() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['ids']) || empty($input['entity_type']) || !isset($input['status'])) {
            $this->jsonResponse(array('error' => 'Missing required parameters'), 400);
            return;
        }

        $table = $this->getTableName($input['entity_type']);
        if (!$table) {
            $this->jsonResponse(array('error' => 'Invalid entity type'), 400);
            return;
        }

        $result = $this->bulkService->bulkUpdate($table, $input['ids'], 'status', $input['status'], $tenantId);

        if ($result['success']) {
            // Log activity
            foreach ($input['ids'] as $id) {
                logActivity($tenantId, $_SESSION['user_id'], 'bulk_status_update', $input['entity_type'], $id);
            }

            $this->jsonResponse($result);
        } else {
            $this->jsonResponse($result, 500);
        }
    }

    /**
     * Bulk assign to agent
     */
    public function assign() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['ids']) || empty($input['entity_type']) || empty($input['user_id'])) {
            $this->jsonResponse(array('error' => 'Missing required parameters'), 400);
            return;
        }

        $table = $this->getTableName($input['entity_type']);
        if (!$table) {
            $this->jsonResponse(array('error' => 'Invalid entity type'), 400);
            return;
        }

        // Verify user belongs to tenant
        $userModel = $this->model('User');
        $user = $userModel->getById($input['user_id'], $tenantId);

        if (!$user) {
            $this->jsonResponse(array('error' => 'Invalid user'), 403);
            return;
        }

        $result = $this->bulkService->bulkAssign($table, $input['ids'], $input['user_id'], $tenantId);

        if ($result['success']) {
            $this->jsonResponse($result);
        } else {
            $this->jsonResponse($result, 500);
        }
    }

    /**
     * Bulk email
     */
    public function email() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['ids']) || empty($input['subject']) || empty($input['message'])) {
            $this->jsonResponse(array('error' => 'Missing required parameters'), 400);
            return;
        }

        $entityType = !empty($input['entity_type']) ? $input['entity_type'] : 'leads';

        $result = $this->bulkService->bulkEmail(
            $input['ids'],
            $input['subject'],
            $input['message'],
            $entityType,
            $tenantId
        );

        if ($result['success']) {
            $this->jsonResponse($result);
        } else {
            $this->jsonResponse($result, 500);
        }
    }

    /**
     * Bulk convert leads to clients
     */
    public function convertLeads() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['ids'])) {
            $this->jsonResponse(array('error' => 'No lead IDs provided'), 400);
            return;
        }

        $result = $this->bulkService->bulkConvertLeads($input['ids'], $tenantId);

        if ($result['success']) {
            $this->jsonResponse($result);
        } else {
            $this->jsonResponse($result, 500);
        }
    }

    /**
     * Bulk export selected records
     */
    public function export() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        if (empty($_POST['ids']) || empty($_POST['entity_type'])) {
            $this->setFlash('error', 'Missing required parameters');
            $this->redirect('dashboard/index');
            return;
        }

        $ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', $_POST['ids']);
        $table = $this->getTableName($_POST['entity_type']);

        if (!$table) {
            $this->setFlash('error', 'Invalid entity type');
            $this->redirect('dashboard/index');
            return;
        }

        $result = $this->bulkService->bulkExportData($table, $ids, $tenantId);

        if ($result['success']) {
            require_once APP_PATH . '/helpers/ExportService.php';
            $exportService = new ExportService();

            // Determine headers based on entity type
            $headers = $this->getExportHeaders($_POST['entity_type']);

            $filename = $_POST['entity_type'] . '_bulk_export_' . date('Y-m-d_His') . '.csv';
            $exportService->exportToCSV($result['data'], $headers, $filename);
        } else {
            $this->setFlash('error', 'Export failed');
            $this->redirect('dashboard/index');
        }
    }

    /**
     * Helper: Get table name from entity type
     */
    private function getTableName($entityType) {
        $mapping = array(
            'leads' => 'leads',
            'clients' => 'clients',
            'properties' => 'properties',
            'deals' => 'deals',
            'tasks' => 'tasks'
        );

        return isset($mapping[$entityType]) ? $mapping[$entityType] : null;
    }

    /**
     * Helper: Get export headers for entity type
     */
    private function getExportHeaders($entityType) {
        switch ($entityType) {
            case 'leads':
                return array(
                    'id' => 'ID',
                    'first_name' => 'First Name',
                    'last_name' => 'Last Name',
                    'email' => 'Email',
                    'phone' => 'Phone',
                    'status' => 'Status',
                    'source' => 'Source',
                    'created_at' => 'Created'
                );

            case 'clients':
                return array(
                    'id' => 'ID',
                    'first_name' => 'First Name',
                    'last_name' => 'Last Name',
                    'email' => 'Email',
                    'phone' => 'Phone',
                    'client_type' => 'Type',
                    'status' => 'Status',
                    'created_at' => 'Created'
                );

            case 'properties':
                return array(
                    'id' => 'ID',
                    'title' => 'Title',
                    'property_type' => 'Type',
                    'price' => 'Price',
                    'address' => 'Address',
                    'city' => 'City',
                    'status' => 'Status',
                    'created_at' => 'Created'
                );

            case 'deals':
                return array(
                    'id' => 'ID',
                    'title' => 'Title',
                    'deal_value' => 'Value',
                    'stage' => 'Stage',
                    'probability' => 'Probability',
                    'created_at' => 'Created'
                );

            case 'tasks':
                return array(
                    'id' => 'ID',
                    'title' => 'Title',
                    'status' => 'Status',
                    'priority' => 'Priority',
                    'due_date' => 'Due Date',
                    'created_at' => 'Created'
                );

            default:
                return array('id' => 'ID');
        }
    }
}
