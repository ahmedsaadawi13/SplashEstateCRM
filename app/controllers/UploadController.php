<?php
// FILE: /app/controllers/UploadController.php

/**
 * SplashEstate CRM - Upload Controller
 * Handles file uploads with validation
 */

class UploadController extends Controller {

    private $attachmentModel;

    public function __construct() {
        $this->attachmentModel = $this->model('Attachment');
    }

    /**
     * Upload file
     */
    public function upload() {
        $this->requireLogin();

        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->jsonResponse(array('success' => false, 'message' => 'Invalid request'), 403);
            return;
        }

        $tenantId = $this->getTenantId();
        $userId = $this->getUserId();

        // Validate related entity
        if (!isset($_POST['related_to_type']) || !isset($_POST['related_to_id'])) {
            $this->jsonResponse(array('success' => false, 'message' => 'Related entity is required'), 400);
            return;
        }

        $relatedType = $this->sanitize($_POST['related_to_type']);
        $relatedId = (int)$_POST['related_to_id'];

        // Validate file
        if (!isset($_FILES['file'])) {
            $this->jsonResponse(array('success' => false, 'message' => 'No file uploaded'), 400);
            return;
        }

        $file = $_FILES['file'];

        // Upload file
        $result = uploadFile($file, $tenantId);

        if (!$result['success']) {
            $this->jsonResponse(array('success' => false, 'message' => $result['message']), 400);
            return;
        }

        // Save attachment record
        $attachmentData = array(
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'related_to_type' => $relatedType,
            'related_to_id' => $relatedId,
            'file_name' => $file['name'],
            'file_path' => $result['path'],
            'file_size' => $file['size'],
            'file_type' => pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        $attachmentId = $this->attachmentModel->createAttachment($attachmentData);

        if ($attachmentId) {
            logActivity($userId, $tenantId, 'uploaded_file', $relatedType, $relatedId);
            $this->jsonResponse(array(
                'success' => true,
                'message' => 'File uploaded successfully',
                'attachment_id' => $attachmentId
            ), 200);
        } else {
            // Delete uploaded file if database insert failed
            deleteFile($result['path']);
            $this->jsonResponse(array('success' => false, 'message' => 'Failed to save attachment'), 500);
        }
    }

    /**
     * Delete attachment
     */
    public function delete($id) {
        $this->requireLogin();

        // Verify CSRF token
        if (!isset($_POST['csrf_token']) || !$this->verifyCsrfToken($_POST['csrf_token'])) {
            $this->jsonResponse(array('success' => false, 'message' => 'Invalid request'), 403);
            return;
        }

        $tenantId = $this->getTenantId();
        $userId = $this->getUserId();

        if ($this->attachmentModel->deleteAttachment($id, $tenantId)) {
            logActivity($userId, $tenantId, 'deleted_file', 'attachment', $id);
            $this->jsonResponse(array('success' => true, 'message' => 'File deleted successfully'), 200);
        } else {
            $this->jsonResponse(array('success' => false, 'message' => 'Failed to delete file'), 500);
        }
    }

    /**
     * Download attachment
     */
    public function download($id) {
        $this->requireLogin();

        $tenantId = $this->getTenantId();
        $attachment = $this->attachmentModel->getById($id, $tenantId);

        if (!$attachment) {
            die('File not found');
        }

        $filePath = UPLOAD_PATH . '/' . $attachment['file_path'];

        if (!file_exists($filePath)) {
            die('File not found on server');
        }

        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $attachment['file_name'] . '"');
        header('Content-Length: ' . filesize($filePath));

        readfile($filePath);
        exit;
    }
}
