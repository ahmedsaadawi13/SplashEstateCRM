<?php
// FILE: /app/models/SavedSearch.php

/**
 * SplashEstate CRM - Saved Search Model
 * Manages saved search filters for users
 */

class SavedSearch extends Model {

    protected $table = 'saved_searches';

    /**
     * Get all saved searches for a user
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @param string $module Module name (leads, clients, properties, deals, tasks)
     * @return array Saved searches
     */
    public function getUserSearches($userId, $tenantId, $module = null) {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND tenant_id = :tenant_id";

        $params = array(':user_id' => $userId, ':tenant_id' => $tenantId);

        if ($module) {
            $sql .= " AND module = :module";
            $params[':module'] = $module;
        }

        $sql .= " ORDER BY is_default DESC, name ASC";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get user searches error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Get a saved search by ID
     * @param int $id Search ID
     * @param int $tenantId Tenant ID
     * @return array|false Saved search data
     */
    public function getById($id, $tenantId) {
        $sql = "SELECT * FROM {$this->table}
                WHERE id = :id AND tenant_id = :tenant_id";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(':id' => $id, ':tenant_id' => $tenantId));
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get saved search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new saved search
     * @param array $data Search data
     * @return int|false Inserted ID or false on failure
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, user_id, module, name, filters, is_default, created_at)
                VALUES
                (:tenant_id, :user_id, :module, :name, :filters, :is_default, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':tenant_id' => $data['tenant_id'],
                ':user_id' => $data['user_id'],
                ':module' => $data['module'],
                ':name' => $data['name'],
                ':filters' => json_encode($data['filters']),
                ':is_default' => isset($data['is_default']) ? (int)$data['is_default'] : 0
            ));

            return $this->db->lastInsertId();
        } catch(PDOException $e) {
            error_log("Create saved search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update a saved search
     * @param int $id Search ID
     * @param int $tenantId Tenant ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function update($id, $tenantId, $data) {
        $sql = "UPDATE {$this->table}
                SET name = :name, filters = :filters, is_default = :is_default
                WHERE id = :id AND tenant_id = :tenant_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array(
                ':id' => $id,
                ':tenant_id' => $tenantId,
                ':name' => $data['name'],
                ':filters' => json_encode($data['filters']),
                ':is_default' => isset($data['is_default']) ? (int)$data['is_default'] : 0
            ));
        } catch(PDOException $e) {
            error_log("Update saved search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete a saved search
     * @param int $id Search ID
     * @param int $tenantId Tenant ID
     * @return bool Success status
     */
    public function delete($id, $tenantId) {
        $sql = "DELETE FROM {$this->table}
                WHERE id = :id AND tenant_id = :tenant_id";

        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array(':id' => $id, ':tenant_id' => $tenantId));
        } catch(PDOException $e) {
            error_log("Delete saved search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set a search as default
     * @param int $id Search ID
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @param string $module Module name
     * @return bool Success status
     */
    public function setAsDefault($id, $userId, $tenantId, $module) {
        try {
            // First, unset all defaults for this user/module
            $sql = "UPDATE {$this->table}
                    SET is_default = 0
                    WHERE user_id = :user_id AND tenant_id = :tenant_id AND module = :module";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':user_id' => $userId,
                ':tenant_id' => $tenantId,
                ':module' => $module
            ));

            // Then set this one as default
            $sql = "UPDATE {$this->table}
                    SET is_default = 1
                    WHERE id = :id AND tenant_id = :tenant_id";

            $stmt = $this->db->prepare($sql);
            return $stmt->execute(array(':id' => $id, ':tenant_id' => $tenantId));
        } catch(PDOException $e) {
            error_log("Set default search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get default search for a module
     * @param int $userId User ID
     * @param int $tenantId Tenant ID
     * @param string $module Module name
     * @return array|false Default search data
     */
    public function getDefault($userId, $tenantId, $module) {
        $sql = "SELECT * FROM {$this->table}
                WHERE user_id = :user_id AND tenant_id = :tenant_id
                AND module = :module AND is_default = 1";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array(
                ':user_id' => $userId,
                ':tenant_id' => $tenantId,
                ':module' => $module
            ));
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Get default search error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Parse saved filters for use in queries
     * @param string $filtersJson JSON encoded filters
     * @return array Parsed filters array
     */
    public function parseFilters($filtersJson) {
        $filters = json_decode($filtersJson, true);
        return is_array($filters) ? $filters : array();
    }
}
