<?php
// FILE: /app/helpers/functions.php

/**
 * SplashEstate CRM - Global Helper Functions
 * Common utility functions used throughout the application
 */

/**
 * Generate a random API key
 * @return string API key
 */
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

/**
 * Verify API key
 * @param string $apiKey API key to verify
 * @param int $tenantId Tenant ID (optional)
 * @return array|false Tenant data or false
 */
function verifyApiKey($apiKey, $tenantId = null) {
    $db = new Database();
    $conn = $db->connect();

    $sql = "SELECT t.* FROM tenants t WHERE t.api_key = :api_key AND t.status = 'active'";
    if ($tenantId !== null) {
        $sql .= " AND t.id = :tenant_id";
    }
    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':api_key', $apiKey);
    if ($tenantId !== null) {
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetch();
}

/**
 * Check subscription quota
 * @param int $tenantId Tenant ID
 * @param string $resource Resource type (leads, properties, agents)
 * @return bool True if within quota
 */
function checkQuota($tenantId, $resource) {
    $db = new Database();
    $conn = $db->connect();

    // Get active subscription with plan details
    $sql = "SELECT p.max_leads, p.max_properties, p.max_agents
            FROM tenant_subscriptions ts
            JOIN plans p ON ts.plan_id = p.id
            WHERE ts.tenant_id = :tenant_id
            AND ts.status = 'active'
            AND (ts.end_date IS NULL OR ts.end_date > NOW())
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->execute();
    $subscription = $stmt->fetch();

    if (!$subscription) {
        return false; // No active subscription
    }

    // Get current usage
    $usageSql = "SELECT COUNT(*) as count FROM {$resource} WHERE tenant_id = :tenant_id";
    $usageStmt = $conn->prepare($usageSql);
    $usageStmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $usageStmt->execute();
    $usage = $usageStmt->fetch();

    $currentCount = (int)$usage['count'];

    // Check against limit
    $limit = 0;
    switch ($resource) {
        case 'leads':
            $limit = (int)$subscription['max_leads'];
            break;
        case 'properties':
            $limit = (int)$subscription['max_properties'];
            break;
        case 'users':
            $limit = (int)$subscription['max_agents'];
            break;
    }

    return ($limit === -1) || ($currentCount < $limit);
}

/**
 * Log activity
 * @param int $userId User ID
 * @param int $tenantId Tenant ID
 * @param string $action Action performed
 * @param string $entity Entity type
 * @param int $entityId Entity ID
 */
function logActivity($userId, $tenantId, $action, $entity, $entityId) {
    $db = new Database();
    $conn = $db->connect();

    $sql = "INSERT INTO activity_logs (user_id, tenant_id, action, entity_type, entity_id, created_at)
            VALUES (:user_id, :tenant_id, :action, :entity_type, :entity_id, NOW())";

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->bindValue(':action', $action);
    $stmt->bindValue(':entity_type', $entity);
    $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Upload file
 * @param array $file File from $_FILES
 * @param int $tenantId Tenant ID
 * @return array Result with success status and file path or error
 */
function uploadFile($file, $tenantId) {
    $result = array('success' => false, 'message' => '', 'path' => '');

    // Check if file was uploaded
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $result['message'] = 'No file uploaded';
        return $result;
    }

    // Check file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        $result['message'] = 'File size exceeds maximum allowed size';
        return $result;
    }

    // Get file extension
    $fileName = $file['name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Check file type
    $allowedTypes = explode(',', ALLOWED_FILE_TYPES);
    if (!in_array($fileExt, $allowedTypes)) {
        $result['message'] = 'File type not allowed';
        return $result;
    }

    // Generate unique filename
    $uniqueName = uniqid() . '_' . time() . '.' . $fileExt;

    // Create tenant directory if not exists
    $tenantDir = UPLOAD_PATH . '/' . $tenantId;
    if (!file_exists($tenantDir)) {
        mkdir($tenantDir, 0755, true);
    }

    // Move file
    $destination = $tenantDir . '/' . $uniqueName;
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $result['success'] = true;
        $result['path'] = $tenantId . '/' . $uniqueName;
        $result['message'] = 'File uploaded successfully';
    } else {
        $result['message'] = 'Failed to move uploaded file';
    }

    return $result;
}

/**
 * Delete file
 * @param string $filePath File path relative to upload directory
 * @return bool Success status
 */
function deleteFile($filePath) {
    $fullPath = UPLOAD_PATH . '/' . $filePath;
    if (file_exists($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}

/**
 * Send email (placeholder for future implementation)
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool Success status
 */
function sendEmail($to, $subject, $message) {
    // TODO: Implement email sending with SMTP
    // For now, just return true
    return true;
}

/**
 * Format phone number
 * @param string $phone Phone number
 * @return string Formatted phone number
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 10) {
        return '(' . substr($phone, 0, 3) . ') ' . substr($phone, 3, 3) . '-' . substr($phone, 6);
    }
    return $phone;
}

/**
 * Generate random password
 * @param int $length Password length
 * @return string Random password
 */
function generatePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

/**
 * Validate required fields
 * @param array $data Data to validate
 * @param array $required Required fields
 * @return array Validation errors
 */
function validateRequired($data, $required) {
    $errors = array();
    foreach ($required as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            $errors[$field] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    return $errors;
}

/**
 * Get file icon based on extension
 * @param string $extension File extension
 * @return string Icon class
 */
function getFileIcon($extension) {
    $icons = array(
        'pdf' => 'file-pdf',
        'doc' => 'file-word',
        'docx' => 'file-word',
        'xls' => 'file-excel',
        'xlsx' => 'file-excel',
        'jpg' => 'file-image',
        'jpeg' => 'file-image',
        'png' => 'file-image',
        'gif' => 'file-image'
    );

    return isset($icons[$extension]) ? $icons[$extension] : 'file';
}

/**
 * Trigger webhook event
 * @param int $tenantId Tenant ID
 * @param string $eventType Event type (e.g., 'lead.created', 'property.updated')
 * @param array $data Event data
 * @return bool Success
 */
function triggerWebhook($tenantId, $eventType, $data) {
    try {
        require_once APP_PATH . '/helpers/WebhookService.php';
        $webhookService = new WebhookService();
        return $webhookService->trigger($tenantId, $eventType, $data);
    } catch (Exception $e) {
        error_log('Webhook trigger failed: ' . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has permission
 * @param int $userId User ID
 * @param string $permissionSlug Permission slug (e.g., 'leads.create')
 * @return bool Has permission
 */
function hasPermission($userId, $permissionSlug) {
    static $userPermissions = array();

    // Cache permissions per user
    if (!isset($userPermissions[$userId])) {
        require_once APP_PATH . '/models/Role.php';
        $roleModel = new Role();
        $userPermissions[$userId] = $roleModel->getUserPermissions($userId);
    }

    return in_array($permissionSlug, $userPermissions[$userId]);
}

/**
 * Check if current session user has permission
 * @param string $permissionSlug Permission slug
 * @return bool Has permission
 */
function can($permissionSlug) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    // Platform admins have all permissions
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'platform_admin') {
        return true;
    }

    return hasPermission($_SESSION['user_id'], $permissionSlug);
}

/**
 * Get user's roles
 * @param int $userId User ID
 * @return array Roles
 */
function getUserRoles($userId) {
    static $userRoles = array();

    if (!isset($userRoles[$userId])) {
        require_once APP_PATH . '/models/Role.php';
        $roleModel = new Role();
        $userRoles[$userId] = $roleModel->getUserRoles($userId);
    }

    return $userRoles[$userId];
}
