<?php
// FILE: /app/models/PropertyImage.php

/**
 * SplashEstate CRM - Property Image Model
 * Manages property images for gallery
 */

class PropertyImage extends Model {

    protected $table = 'property_images';

    /**
     * Get all images for a property
     * @param int $propertyId Property ID
     * @param int $tenantId Tenant ID
     * @return array Images ordered by display_order
     */
    public function getPropertyImages($propertyId, $tenantId) {
        $sql = "SELECT pi.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
                FROM {$this->table} pi
                LEFT JOIN users u ON pi.uploaded_by = u.id
                WHERE pi.property_id = :property_id AND pi.tenant_id = :tenant_id
                ORDER BY pi.is_primary DESC, pi.display_order ASC, pi.created_at ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':property_id' => $propertyId,
                ':tenant_id' => $tenantId
            ));
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get property images error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get primary image for a property
     * @param int $propertyId Property ID
     * @param int $tenantId Tenant ID
     * @return array|false Primary image data
     */
    public function getPrimaryImage($propertyId, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE property_id = :property_id AND tenant_id = :tenant_id AND is_primary = 1
                LIMIT 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':property_id' => $propertyId,
                ':tenant_id' => $tenantId
            ));
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get primary image error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Add property image
     * @param array $data Image data
     * @return int|false Image ID or false
     */
    public function addImage($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, property_id, file_name, file_path, file_size, mime_type, is_primary, display_order, caption, uploaded_by, created_at)
                VALUES
                (:tenant_id, :property_id, :file_name, :file_path, :file_size, :mime_type, :is_primary, :display_order, :caption, :uploaded_by, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':tenant_id' => $data['tenant_id'],
                ':property_id' => $data['property_id'],
                ':file_name' => $data['file_name'],
                ':file_path' => $data['file_path'],
                ':file_size' => isset($data['file_size']) ? $data['file_size'] : null,
                ':mime_type' => isset($data['mime_type']) ? $data['mime_type'] : null,
                ':is_primary' => isset($data['is_primary']) ? $data['is_primary'] : 0,
                ':display_order' => isset($data['display_order']) ? $data['display_order'] : 0,
                ':caption' => isset($data['caption']) ? $data['caption'] : null,
                ':uploaded_by' => isset($data['uploaded_by']) ? $data['uploaded_by'] : null
            ));

            return $this->db->lastInsertId();
        } catch(PDOException $e) {
            error_log("Add property image error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set primary image
     * @param int $imageId Image ID
     * @param int $propertyId Property ID
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function setPrimaryImage($imageId, $propertyId, $tenantId) {
        try {
            // First, unset all primary images for this property
            $sql = "UPDATE {$this->table}
                    SET is_primary = 0
                    WHERE property_id = :property_id AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':property_id' => $propertyId,
                ':tenant_id' => $tenantId
            ));

            // Then set this image as primary
            $sql = "UPDATE {$this->table}
                    SET is_primary = 1
                    WHERE id = :id AND property_id = :property_id AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array(
                ':id' => $imageId,
                ':property_id' => $propertyId,
                ':tenant_id' => $tenantId
            ));
        } catch(PDOException $e) {
            error_log("Set primary image error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update display order
     * @param int $imageId Image ID
     * @param int $order New order
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function updateOrder($imageId, $order, $tenantId) {
        $sql = "UPDATE {$this->table}
                SET display_order = :order
                WHERE id = :id AND tenant_id = :tenant_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array(
                ':order' => $order,
                ':id' => $imageId,
                ':tenant_id' => $tenantId
            ));
        } catch(PDOException $e) {
            error_log("Update image order error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete image
     * @param int $imageId Image ID
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function deleteImage($imageId, $tenantId) {
        // First get the image to delete the file
        $image = $this->getById($imageId, $tenantId);

        if ($image) {
            // Delete file from filesystem
            $filePath = ROOT_PATH . '/' . $image['file_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete from database
            $sql = "DELETE FROM {$this->table}
                    WHERE id = :id AND tenant_id = :tenant_id";

            try {
                $stmt = $this->db->prepare($sql);
                return $stmt->execute(array(
                    ':id' => $imageId,
                    ':tenant_id' => $tenantId
                ));
            } catch(PDOException $e) {
                error_log("Delete image error: " . $e->getMessage());
                return false;
            }
        }

        return false;
    }

    /**
     * Update image caption
     * @param int $imageId Image ID
     * @param string $caption Caption text
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function updateCaption($imageId, $caption, $tenantId) {
        $sql = "UPDATE {$this->table}
                SET caption = :caption
                WHERE id = :id AND tenant_id = :tenant_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array(
                ':caption' => $caption,
                ':id' => $imageId,
                ':tenant_id' => $tenantId
            ));
        } catch(PDOException $e) {
            error_log("Update caption error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get image count for property
     * @param int $propertyId Property ID
     * @param int $tenantId Tenant ID
     * @return int Image count
     */
    public function getImageCount($propertyId, $tenantId) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}
                WHERE property_id = :property_id AND tenant_id = :tenant_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':property_id' => $propertyId,
                ':tenant_id' => $tenantId
            ));
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch(PDOException $e) {
            error_log("Get image count error: " . $e->getMessage());
            return 0;
        }
    }
}
