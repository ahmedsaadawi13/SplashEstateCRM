<?php
// FILE: /app/controllers/PropertiesController.php

/**
 * SplashEstate CRM - Properties Controller
 */

class PropertiesController extends Controller {

    private $propertyModel;
    private $userModel;
    private $clientModel;

    public function __construct() {
        $this->propertyModel = $this->model('Property');
        $this->userModel = $this->model('User');
        $this->clientModel = $this->model('Client');
    }

    public function index() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $perPage = 20;

        $filters = array();
        if (!empty($_GET['property_type'])) $filters['property_type'] = $_GET['property_type'];
        if (!empty($_GET['listing_type'])) $filters['listing_type'] = $_GET['listing_type'];
        if (!empty($_GET['status'])) $filters['status'] = $_GET['status'];
        if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];

        $properties = $this->propertyModel->getPropertiesWithInfo($tenantId, $page, $perPage, $filters);
        $totalProperties = $this->propertyModel->count($tenantId, $filters);
        $totalPages = ceil($totalProperties / $perPage);

        $data = array(
            'title' => 'Properties',
            'properties' => $properties,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'filters' => $filters
        );

        $this->view('properties/index', $data);
    }

    public function view($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $property = $this->propertyModel->getById($id, $tenantId);

        if (!$property) {
            $this->setFlash('error', 'Property not found');
            $this->redirect('properties/index');
        }

        $attachmentModel = $this->model('Attachment');
        $attachments = $attachmentModel->getByRelated($tenantId, 'property', $id);

        $data = array(
            'title' => 'Property Details',
            'property' => $property,
            'attachments' => $attachments
        );

        $this->view('properties/view', $data);
    }

    public function create() {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        if (!checkQuota($tenantId, 'properties')) {
            $this->setFlash('error', 'Property limit reached. Please upgrade your plan.');
            $this->redirect('properties/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processCreate();
            return;
        }

        $agents = $this->userModel->getUsersByTenant($tenantId);
        $clients = $this->clientModel->getAll($tenantId, 1, 100);

        $data = array(
            'title' => 'Create Property',
            'agents' => $agents,
            'clients' => $clients,
            'errors' => array(),
            'formData' => array()
        );

        $this->view('properties/create', $data);
    }

    private function processCreate() {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('properties/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'title' => $this->sanitize($_POST['title']),
            'description' => $this->sanitize($_POST['description']),
            'property_type' => $this->sanitize($_POST['property_type']),
            'sub_type' => $this->sanitize($_POST['sub_type']),
            'listing_type' => $this->sanitize($_POST['listing_type']),
            'price' => $this->sanitize($_POST['price']),
            'bedrooms' => $this->sanitize($_POST['bedrooms']),
            'bathrooms' => $this->sanitize($_POST['bathrooms']),
            'square_feet' => $this->sanitize($_POST['square_feet']),
            'lot_size' => $this->sanitize($_POST['lot_size']),
            'year_built' => $this->sanitize($_POST['year_built']),
            'address' => $this->sanitize($_POST['address']),
            'city' => $this->sanitize($_POST['city']),
            'state' => $this->sanitize($_POST['state']),
            'zip' => $this->sanitize($_POST['zip']),
            'owner_id' => $this->sanitize($_POST['owner_id']),
            'listing_agent_id' => $this->sanitize($_POST['listing_agent_id'])
        );

        $errors = validateRequired($formData, array('title', 'property_type', 'listing_type', 'price', 'address', 'city', 'state', 'zip'));

        if (!empty($errors)) {
            $agents = $this->userModel->getUsersByTenant($tenantId);
            $clients = $this->clientModel->getAll($tenantId, 1, 100);
            $data = array('title' => 'Create Property', 'agents' => $agents, 'clients' => $clients, 'errors' => $errors, 'formData' => $formData);
            $this->view('properties/create', $data);
            return;
        }

        $formData['tenant_id'] = $tenantId;
        $formData['status'] = 'available';

        // Geocode address to get latitude/longitude
        $this->geocodePropertyAddress($formData);

        $propertyId = $this->propertyModel->createProperty($formData);

        if ($propertyId) {
            logActivity($this->getUserId(), $tenantId, 'created', 'property', $propertyId);
            $this->setFlash('success', 'Property created successfully');
            $this->redirect('properties/view/' . $propertyId);
        } else {
            $this->setFlash('error', 'Failed to create property');
            $this->redirect('properties/create');
        }
    }

    public function edit($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $property = $this->propertyModel->getById($id, $tenantId);

        if (!$property) {
            $this->setFlash('error', 'Property not found');
            $this->redirect('properties/index');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processEdit($id);
            return;
        }

        $agents = $this->userModel->getUsersByTenant($tenantId);
        $clients = $this->clientModel->getAll($tenantId, 1, 100);

        $data = array('title' => 'Edit Property', 'property' => $property, 'agents' => $agents, 'clients' => $clients, 'errors' => array());
        $this->view('properties/edit', $data);
    }

    private function processEdit($id) {
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('properties/index');
        }

        $tenantId = $this->getTenantId();

        $formData = array(
            'title' => $this->sanitize($_POST['title']),
            'description' => $this->sanitize($_POST['description']),
            'property_type' => $this->sanitize($_POST['property_type']),
            'sub_type' => $this->sanitize($_POST['sub_type']),
            'listing_type' => $this->sanitize($_POST['listing_type']),
            'price' => $this->sanitize($_POST['price']),
            'bedrooms' => $this->sanitize($_POST['bedrooms']),
            'bathrooms' => $this->sanitize($_POST['bathrooms']),
            'square_feet' => $this->sanitize($_POST['square_feet']),
            'lot_size' => $this->sanitize($_POST['lot_size']),
            'year_built' => $this->sanitize($_POST['year_built']),
            'address' => $this->sanitize($_POST['address']),
            'city' => $this->sanitize($_POST['city']),
            'state' => $this->sanitize($_POST['state']),
            'zip' => $this->sanitize($_POST['zip']),
            'status' => $this->sanitize($_POST['status']),
            'owner_id' => $this->sanitize($_POST['owner_id']),
            'listing_agent_id' => $this->sanitize($_POST['listing_agent_id'])
        );

        // Geocode address to get updated latitude/longitude
        $this->geocodePropertyAddress($formData);

        if ($this->propertyModel->update($id, $tenantId, $formData)) {
            logActivity($this->getUserId(), $tenantId, 'updated', 'property', $id);
            $this->setFlash('success', 'Property updated successfully');
            $this->redirect('properties/view/' . $id);
        } else {
            $this->setFlash('error', 'Failed to update property');
            $this->redirect('properties/edit/' . $id);
        }
    }

    /**
     * Geocode property address to get latitude/longitude
     * @param array &$formData Property data (passed by reference)
     */
    private function geocodePropertyAddress(&$formData) {
        // Check if Google Maps is configured
        if (!defined('GOOGLE_MAPS_API_KEY') || empty(GOOGLE_MAPS_API_KEY)) {
            return;
        }

        // Build full address
        $fullAddress = trim(
            $formData['address'] . ', ' .
            $formData['city'] . ', ' .
            $formData['state'] . ' ' .
            $formData['zip']
        );

        try {
            require_once APP_PATH . '/helpers/GoogleMapsService.php';
            $mapsService = new GoogleMapsService();

            $result = $mapsService->geocodeAddress($fullAddress);

            if ($result && isset($result['latitude']) && isset($result['longitude'])) {
                $formData['latitude'] = $result['latitude'];
                $formData['longitude'] = $result['longitude'];
            }
        } catch (Exception $e) {
            error_log('Geocoding failed: ' . $e->getMessage());
            // Don't fail the property creation/update if geocoding fails
        }
    }

    public function delete($id) {
        $this->requireLogin();

        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->setFlash('error', 'Invalid request');
            $this->redirect('properties/index');
        }

        $tenantId = $this->getTenantId();

        if ($this->propertyModel->delete($id, $tenantId)) {
            logActivity($this->getUserId(), $tenantId, 'deleted', 'property', $id);
            $this->setFlash('success', 'Property deleted successfully');
        } else {
            $this->setFlash('error', 'Failed to delete property');
        }

        $this->redirect('properties/index');
    }
}
