<?php
// FILE: /app/controllers/PropertyGalleryController.php

/**
 * SplashEstate CRM - Property Gallery Controller
 * Manages property image gallery
 */

class PropertyGalleryController extends Controller {

    private $imageModel;
    private $propertyModel;

    public function __construct() {
        $this->imageModel = $this->model('PropertyImage');
        $this->propertyModel = $this->model('Property');
    }

    /**
     * Upload property images
     */
    public function upload($propertyId) {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();

        // Verify property belongs to tenant
        $property = $this->propertyModel->getById($propertyId, $tenantId);
        if (!$property) {
            $this->jsonResponse(array('error' => 'Property not found'), 404);
            return;
        }

        // Check if files were uploaded
        if (empty($_FILES['images'])) {
            $this->jsonResponse(array('error' => 'No images uploaded'), 400);
            return;
        }

        $uploadedImages = array();
        $errors = array();

        // Handle multiple file uploads
        $files = $this->reArrayFiles($_FILES['images']);

        foreach ($files as $file) {
            $result = $this->processImageUpload($file, $propertyId, $tenantId);

            if ($result['success']) {
                $uploadedImages[] = $result['image'];
            } else {
                $errors[] = $result['error'];
            }
        }

        $this->jsonResponse(array(
            'success' => true,
            'uploaded' => count($uploadedImages),
            'failed' => count($errors),
            'images' => $uploadedImages,
            'errors' => $errors
        ));
    }

    /**
     * Get property images
     */
    public function getImages($propertyId) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();

        // Verify property belongs to tenant
        $property = $this->propertyModel->getById($propertyId, $tenantId);
        if (!$property) {
            $this->jsonResponse(array('error' => 'Property not found'), 404);
            return;
        }

        $images = $this->imageModel->getPropertyImages($propertyId, $tenantId);

        $this->jsonResponse(array(
            'success' => true,
            'images' => $images
        ));
    }

    /**
     * Set primary image
     */
    public function setPrimary() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['image_id']) || empty($input['property_id'])) {
            $this->jsonResponse(array('error' => 'Missing parameters'), 400);
            return;
        }

        $success = $this->imageModel->setPrimaryImage(
            $input['image_id'],
            $input['property_id'],
            $tenantId
        );

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Primary image updated'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to set primary image'), 500);
        }
    }

    /**
     * Update image order
     */
    public function updateOrder() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['images'])) {
            $this->jsonResponse(array('error' => 'No images provided'), 400);
            return;
        }

        $success = true;
        foreach ($input['images'] as $index => $imageId) {
            $result = $this->imageModel->updateOrder($imageId, $index, $tenantId);
            if (!$result) {
                $success = false;
            }
        }

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Image order updated'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to update order'), 500);
        }
    }

    /**
     * Delete image
     */
    public function deleteImage($imageId) {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();

        $success = $this->imageModel->deleteImage($imageId, $tenantId);

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Image deleted successfully'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to delete image'), 500);
        }
    }

    /**
     * Update image caption
     */
    public function updateCaption() {
        $this->requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->jsonResponse(array('error' => 'Invalid request method'), 405);
            return;
        }

        $tenantId = $this->getTenantId();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['image_id']) || !isset($input['caption'])) {
            $this->jsonResponse(array('error' => 'Missing parameters'), 400);
            return;
        }

        $success = $this->imageModel->updateCaption(
            $input['image_id'],
            $input['caption'],
            $tenantId
        );

        if ($success) {
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'Caption updated'
            ));
        } else {
            $this->jsonResponse(array('error' => 'Failed to update caption'), 500);
        }
    }

    /**
     * Process single image upload
     */
    private function processImageUpload($file, $propertyId, $tenantId) {
        // Validate file
        $allowedTypes = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowedTypes)) {
            return array('success' => false, 'error' => 'Invalid file type: ' . $file['name']);
        }

        if ($file['size'] > $maxSize) {
            return array('success' => false, 'error' => 'File too large: ' . $file['name']);
        }

        // Create upload directory if it doesn't exist
        $uploadDir = 'storage/uploads/properties/' . $propertyId;
        $fullPath = ROOT_PATH . '/' . $uploadDir;

        if (!file_exists($fullPath)) {
            mkdir($fullPath, 0755, true);
        }

        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $fileName = uniqid('img_') . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . '/' . $fileName;
        $fullFilePath = ROOT_PATH . '/' . $filePath;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $fullFilePath)) {
            // Add to database
            $imageData = array(
                'tenant_id' => $tenantId,
                'property_id' => $propertyId,
                'file_name' => $file['name'],
                'file_path' => $filePath,
                'file_size' => $file['size'],
                'mime_type' => $file['type'],
                'is_primary' => 0,
                'display_order' => $this->imageModel->getImageCount($propertyId, $tenantId),
                'uploaded_by' => $_SESSION['user_id']
            );

            $imageId = $this->imageModel->addImage($imageData);

            if ($imageId) {
                return array(
                    'success' => true,
                    'image' => array(
                        'id' => $imageId,
                        'file_name' => $file['name'],
                        'file_path' => $filePath,
                        'url' => BASE_URL . '/' . $filePath
                    )
                );
            } else {
                // Clean up file if database insert failed
                unlink($fullFilePath);
                return array('success' => false, 'error' => 'Database error for: ' . $file['name']);
            }
        } else {
            return array('success' => false, 'error' => 'Failed to move file: ' . $file['name']);
        }
    }

    /**
     * Reorganize $_FILES array for multiple uploads
     */
    private function reArrayFiles($filePost) {
        $fileArray = array();
        $fileCount = count($filePost['name']);

        for ($i = 0; $i < $fileCount; $i++) {
            $fileArray[] = array(
                'name' => $filePost['name'][$i],
                'type' => $filePost['type'][$i],
                'tmp_name' => $filePost['tmp_name'][$i],
                'error' => $filePost['error'][$i],
                'size' => $filePost['size'][$i]
            );
        }

        return $fileArray;
    }
}
