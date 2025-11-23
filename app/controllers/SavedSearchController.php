<?php
// FILE: /app/controllers/SavedSearchController.php

/**
 * SplashEstate CRM - Saved Search Controller
 * Manages saved search filters
 */

class SavedSearchController extends Controller {

    /**
     * Get saved searches for current user
     */
    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $userId = $_SESSION['user_id'];
        $module = isset($_GET['module']) ? $_GET['module'] : null;

        $searchModel = $this->model('SavedSearch');
        $searches = $searchModel->getUserSearches($userId, $tenantId, $module);

        $this->jsonResponse(array(
            'success' => true,
            'searches' => $searches
        ));
    }

    /**
     * Create a new saved search
     */
    public function create() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $userId = $_SESSION['user_id'];

        // Get POST data
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name']) || empty($input['module']) || empty($input['filters'])) {
            $this->jsonResponse(array('error' => 'Missing required fields'), 400);
            return;
        }

        $searchModel = $this->model('SavedSearch');

        $data = array(
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'module' => $input['module'],
            'name' => $input['name'],
            'filters' => $input['filters'],
            'is_default' => isset($input['is_default']) ? $input['is_default'] : 0
        );

        $searchId = $searchModel->create($data);

        if ($searchId) {
            $this->jsonResponse(array(
                'success' => true,
                'search_id' => $searchId,
                'message' => 'Search saved successfully'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to save search'), 500);
        }
    }

    /**
     * Update a saved search
     */
    public function update($id) {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();

        // Get PUT/POST data
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['name']) || empty($input['filters'])) {
            $this->jsonResponse(array('error' => 'Missing required fields'), 400);
            return;
        }

        $searchModel = $this->model('SavedSearch');

        // Verify ownership
        $search = $searchModel->getById($id, $tenantId);
        if (!$search || $search['user_id'] != $_SESSION['user_id']) {
            $this->jsonResponse(array('error' => 'Unauthorized'), 403);
            return;
        }

        $data = array(
            'name' => $input['name'],
            'filters' => $input['filters'],
            'is_default' => isset($input['is_default']) ? $input['is_default'] : 0
        );

        $success = $searchModel->update($id, $tenantId, $data);

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Search updated successfully'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to update search'), 500);
        }
    }

    /**
     * Delete a saved search
     */
    public function delete($id) {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();

        $searchModel = $this->model('SavedSearch');

        // Verify ownership
        $search = $searchModel->getById($id, $tenantId);
        if (!$search || $search['user_id'] != $_SESSION['user_id']) {
            $this->jsonResponse(array('error' => 'Unauthorized'), 403);
            return;
        }

        $success = $searchModel->delete($id, $tenantId);

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Search deleted successfully'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to delete search'), 500);
        }
    }

    /**
     * Set a saved search as default
     */
    public function setDefault($id) {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $userId = $_SESSION['user_id'];

        $searchModel = $this->model('SavedSearch');

        // Verify ownership
        $search = $searchModel->getById($id, $tenantId);
        if (!$search || $search['user_id'] != $userId) {
            $this->jsonResponse(array('error' => 'Unauthorized'), 403);
            return;
        }

        $success = $searchModel->setAsDefault($id, $userId, $tenantId, $search['module']);

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Default search set successfully'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to set default search'), 500);
        }
    }

    /**
     * Load a saved search
     */
    public function load($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        $searchModel = $this->model('SavedSearch');
        $search = $searchModel->getById($id, $tenantId);

        if (!$search || $search['user_id'] != $_SESSION['user_id']) {
            $this->jsonResponse(array('error' => 'Search not found'), 404);
            return;
        }

        // Parse filters
        $filters = $searchModel->parseFilters($search['filters']);

        $this->jsonResponse(array(
            'success' => true,
            'search' => array(
                'id' => $search['id'],
                'name' => $search['name'],
                'module' => $search['module'],
                'filters' => $filters,
                'is_default' => $search['is_default']
            )
        ));
    }
}
