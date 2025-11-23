<?php
// FILE: /app/helpers/BulkOperationService.php

/**
 * SplashEstate CRM - Bulk Operation Service
 * Handles bulk operations on multiple records
 */

class BulkOperationService {

    private $db;
    private $emailService;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        require_once APP_PATH . '/helpers/EmailService.php';
        $this->emailService = new EmailService();
    }

    /**
     * Bulk delete records
     * @param string $table Table name
     * @param array $ids Array of IDs to delete
     * @param int $tenantId Tenant ID for security
     * @return array Result with success count
     */
    public function bulkDelete($table, $ids, $tenantId) {
        if (empty($ids) || !is_array($ids)) {
            return array('success' => false, 'error' => 'No IDs provided');
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($ids as $id) {
            $sql = "DELETE FROM $table WHERE id = :id AND tenant_id = :tenant_id";

            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(':id' => $id, ':tenant_id' => $tenantId));

                if ($stmt->rowCount() > 0) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            } catch(PDOException $e) {
                error_log("Bulk delete error: " . $e->getMessage());
                $failedCount++;
            }
        }

        return array(
            'success' => true,
            'deleted' => $successCount,
            'failed' => $failedCount,
            'total' => count($ids)
        );
    }

    /**
     * Bulk update field value
     * @param string $table Table name
     * @param array $ids Array of IDs
     * @param string $field Field to update
     * @param mixed $value New value
     * @param int $tenantId Tenant ID
     * @return array Result
     */
    public function bulkUpdate($table, $ids, $field, $value, $tenantId) {
        if (empty($ids) || !is_array($ids)) {
            return array('success' => false, 'error' => 'No IDs provided');
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($ids as $id) {
            $sql = "UPDATE $table SET $field = :value, updated_at = NOW()
                    WHERE id = :id AND tenant_id = :tenant_id";

            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(
                    ':value' => $value,
                    ':id' => $id,
                    ':tenant_id' => $tenantId
                ));

                if ($stmt->rowCount() > 0) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            } catch(PDOException $e) {
                error_log("Bulk update error: " . $e->getMessage());
                $failedCount++;
            }
        }

        return array(
            'success' => true,
            'updated' => $successCount,
            'failed' => $failedCount,
            'total' => count($ids)
        );
    }

    /**
     * Bulk assign to user
     * @param string $table Table name
     * @param array $ids Record IDs
     * @param int $userId User ID to assign to
     * @param int $tenantId Tenant ID
     * @return array Result
     */
    public function bulkAssign($table, $ids, $userId, $tenantId) {
        $field = ($table === 'deals') ? 'agent_id' : 'assigned_to';
        return $this->bulkUpdate($table, $ids, $field, $userId, $tenantId);
    }

    /**
     * Bulk email to contacts
     * @param array $recipients Array of email addresses or record IDs
     * @param string $subject Email subject
     * @param string $message Email message
     * @param string $entityType Entity type (leads, clients)
     * @param int $tenantId Tenant ID
     * @return array Result
     */
    public function bulkEmail($recipients, $subject, $message, $entityType = null, $tenantId = null) {
        if (empty($recipients) || !is_array($recipients)) {
            return array('success' => false, 'error' => 'No recipients provided');
        }

        $emails = array();

        // If recipients are IDs, fetch emails from database
        if ($entityType && $tenantId && is_numeric($recipients[0])) {
            $table = $entityType === 'leads' ? 'leads' : 'clients';
            $placeholders = implode(',', array_fill(0, count($recipients), '?'));

            $sql = "SELECT email FROM $table
                    WHERE id IN ($placeholders) AND tenant_id = ? AND email IS NOT NULL";

            try {
                $stmt = $this->db->prepare($sql);
                $params = array_merge($recipients, array($tenantId));
                $stmt->execute($params);
                $emails = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch(PDOException $e) {
                error_log("Fetch emails error: " . $e->getMessage());
                return array('success' => false, 'error' => 'Failed to fetch emails');
            }
        } else {
            $emails = $recipients;
        }

        $successCount = 0;
        $failedCount = 0;

        foreach ($emails as $email) {
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $sent = $this->emailService->sendEmail($email, $subject, $message);
                if ($sent) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
            } else {
                $failedCount++;
            }
        }

        return array(
            'success' => true,
            'sent' => $successCount,
            'failed' => $failedCount,
            'total' => count($emails)
        );
    }

    /**
     * Bulk convert leads to clients
     * @param array $leadIds Array of lead IDs
     * @param int $tenantId Tenant ID
     * @return array Result
     */
    public function bulkConvertLeads($leadIds, $tenantId) {
        if (empty($leadIds) || !is_array($leadIds)) {
            return array('success' => false, 'error' => 'No lead IDs provided');
        }

        require_once APP_PATH . '/models/Lead.php';
        require_once APP_PATH . '/models/Client.php';

        $leadModel = new Lead();
        $clientModel = new Client();

        $successCount = 0;
        $failedCount = 0;
        $convertedIds = array();

        foreach ($leadIds as $leadId) {
            $clientId = $leadModel->convertToClient($leadId, $tenantId);

            if ($clientId) {
                $successCount++;
                $convertedIds[] = $clientId;
            } else {
                $failedCount++;
            }
        }

        return array(
            'success' => true,
            'converted' => $successCount,
            'failed' => $failedCount,
            'total' => count($leadIds),
            'client_ids' => $convertedIds
        );
    }

    /**
     * Bulk add tags (if tagging system exists)
     * @param string $table Table name
     * @param array $ids Record IDs
     * @param array $tags Tags to add
     * @param int $tenantId Tenant ID
     * @return array Result
     */
    public function bulkAddTags($table, $ids, $tags, $tenantId) {
        // This is a placeholder for future tagging functionality
        // For now, we'll use a tags column (JSON or comma-separated)

        if (empty($ids) || !is_array($ids) || empty($tags)) {
            return array('success' => false, 'error' => 'Invalid parameters');
        }

        $successCount = 0;
        $failedCount = 0;

        $tagsString = is_array($tags) ? implode(',', $tags) : $tags;

        foreach ($ids as $id) {
            // Get existing tags
            $sql = "SELECT tags FROM $table WHERE id = :id AND tenant_id = :tenant_id";

            try {
                $stmt = $this->db->prepare($sql);
                $stmt->execute(array(':id' => $id, ':tenant_id' => $tenantId));
                $record = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($record) {
                    $existingTags = !empty($record['tags']) ? $record['tags'] : '';
                    $newTags = !empty($existingTags) ? $existingTags . ',' . $tagsString : $tagsString;

                    // Update with new tags
                    $updateSql = "UPDATE $table SET tags = :tags WHERE id = :id AND tenant_id = :tenant_id";
                    $updateStmt = $this->db->prepare($updateSql);
                    $updateStmt->execute(array(
                        ':tags' => $newTags,
                        ':id' => $id,
                        ':tenant_id' => $tenantId
                    ));

                    $successCount++;
                } else {
                    $failedCount++;
                }
            } catch(PDOException $e) {
                error_log("Bulk add tags error: " . $e->getMessage());
                $failedCount++;
            }
        }

        return array(
            'success' => true,
            'updated' => $successCount,
            'failed' => $failedCount,
            'total' => count($ids)
        );
    }

    /**
     * Bulk export to CSV
     * @param string $table Table name
     * @param array $ids Record IDs
     * @param int $tenantId Tenant ID
     * @return array Records data
     */
    public function bulkExportData($table, $ids, $tenantId) {
        if (empty($ids) || !is_array($ids)) {
            return array('success' => false, 'error' => 'No IDs provided');
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT * FROM $table WHERE id IN ($placeholders) AND tenant_id = ?";

        try {
            $stmt = $this->db->prepare($sql);
            $params = array_merge($ids, array($tenantId));
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array(
                'success' => true,
                'data' => $records,
                'count' => count($records)
            );
        } catch(PDOException $e) {
            error_log("Bulk export error: " . $e->getMessage());
            return array('success' => false, 'error' => 'Export failed');
        }
    }
}
