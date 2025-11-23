<?php
// FILE: /app/models/Role.php

/**
 * SplashEstate CRM - Role Model
 * Manages user roles and permissions
 */

class Role extends Model {

    protected $table = 'roles';

    /**
     * Get all roles for a tenant (or system roles)
     * @param int|null $tenantId Tenant ID (null for system roles)
     * @return array Roles
     */
    public function getAll($tenantId = null) {
        if ($tenantId === null) {
            // Get system roles only
            $sql = "SELECT * FROM {$this->table} WHERE tenant_id IS NULL ORDER BY name";
            $stmt = $this->conn->query($sql);
        } else {
            // Get tenant-specific and system roles
            $sql = "SELECT * FROM {$this->table}
                    WHERE tenant_id IS NULL OR tenant_id = :tenant_id
                    ORDER BY is_system DESC, name";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':tenant_id' => $tenantId]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get role by ID
     * @param int $id Role ID
     * @param int|null $tenantId Tenant ID for access control
     * @return array|false Role data
     */
    public function getById($id, $tenantId = null) {
        if ($tenantId === null) {
            $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);
        } else {
            $sql = "SELECT * FROM {$this->table}
                    WHERE id = :id AND (tenant_id IS NULL OR tenant_id = :tenant_id)
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get role by slug
     * @param string $slug Role slug
     * @param int|null $tenantId Tenant ID
     * @return array|false Role data
     */
    public function getBySlug($slug, $tenantId = null) {
        if ($tenantId === null) {
            $sql = "SELECT * FROM {$this->table} WHERE slug = :slug AND tenant_id IS NULL LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':slug' => $slug]);
        } else {
            $sql = "SELECT * FROM {$this->table}
                    WHERE slug = :slug AND (tenant_id IS NULL OR tenant_id = :tenant_id)
                    LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':slug' => $slug, ':tenant_id' => $tenantId]);
        }

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get permissions for a role
     * @param int $roleId Role ID
     * @return array Permissions
     */
    public function getPermissions($roleId) {
        $sql = "SELECT p.*
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                WHERE rp.role_id = :role_id
                ORDER BY p.module, p.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':role_id' => $roleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get permission slugs for a role
     * @param int $roleId Role ID
     * @return array Permission slugs
     */
    public function getPermissionSlugs($roleId) {
        $permissions = $this->getPermissions($roleId);
        return array_column($permissions, 'slug');
    }

    /**
     * Check if role has permission
     * @param int $roleId Role ID
     * @param string $permissionSlug Permission slug
     * @return bool Has permission
     */
    public function hasPermission($roleId, $permissionSlug) {
        $sql = "SELECT COUNT(*) FROM role_permissions rp
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = :role_id AND p.slug = :slug";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':role_id' => $roleId,
            ':slug' => $permissionSlug
        ]);

        return $stmt->fetchColumn() > 0;
    }

    /**
     * Create role
     * @param array $data Role data
     * @return int|false Role ID
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (tenant_id, name, slug, description, is_system)
                VALUES (:tenant_id, :name, :slug, :description, :is_system)";

        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([
            ':tenant_id' => isset($data['tenant_id']) ? $data['tenant_id'] : null,
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => isset($data['description']) ? $data['description'] : null,
            ':is_system' => isset($data['is_system']) ? $data['is_system'] : 0
        ]);

        return $result ? $this->conn->lastInsertId() : false;
    }

    /**
     * Update role
     * @param int $id Role ID
     * @param array $data Update data
     * @return bool Success
     */
    public function update($id, $data) {
        // Cannot update system roles
        $role = $this->getById($id);
        if (!$role || $role['is_system']) {
            return false;
        }

        $fields = array();
        $params = array(':id' => $id);

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        if (isset($data['slug'])) {
            $fields[] = 'slug = :slug';
            $params[':slug'] = $data['slug'];
        }
        if (isset($data['description'])) {
            $fields[] = 'description = :description';
            $params[':description'] = $data['description'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Delete role
     * @param int $id Role ID
     * @return bool Success
     */
    public function delete($id) {
        // Cannot delete system roles
        $role = $this->getById($id);
        if (!$role || $role['is_system']) {
            return false;
        }

        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Assign permissions to role
     * @param int $roleId Role ID
     * @param array $permissionIds Array of permission IDs
     * @return bool Success
     */
    public function assignPermissions($roleId, $permissionIds) {
        // Cannot modify system roles
        $role = $this->getById($roleId);
        if (!$role || $role['is_system']) {
            return false;
        }

        // Remove existing permissions
        $this->removeAllPermissions($roleId);

        // Add new permissions
        if (empty($permissionIds)) {
            return true;
        }

        $sql = "INSERT INTO role_permissions (role_id, permission_id) VALUES (:role_id, :permission_id)";
        $stmt = $this->conn->prepare($sql);

        foreach ($permissionIds as $permissionId) {
            $stmt->execute([
                ':role_id' => $roleId,
                ':permission_id' => $permissionId
            ]);
        }

        return true;
    }

    /**
     * Remove all permissions from role
     * @param int $roleId Role ID
     * @return bool Success
     */
    public function removeAllPermissions($roleId) {
        $sql = "DELETE FROM role_permissions WHERE role_id = :role_id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':role_id' => $roleId]);
    }

    /**
     * Get users with this role
     * @param int $roleId Role ID
     * @return array Users
     */
    public function getUsers($roleId) {
        $sql = "SELECT u.*
                FROM users u
                INNER JOIN user_roles ur ON u.id = ur.user_id
                WHERE ur.role_id = :role_id
                ORDER BY u.first_name, u.last_name";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':role_id' => $roleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign role to user
     * @param int $userId User ID
     * @param int $roleId Role ID
     * @return bool Success
     */
    public function assignToUser($userId, $roleId) {
        $sql = "INSERT INTO user_roles (user_id, role_id)
                VALUES (:user_id, :role_id)
                ON DUPLICATE KEY UPDATE role_id = :role_id";

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId
        ]);
    }

    /**
     * Remove role from user
     * @param int $userId User ID
     * @param int $roleId Role ID
     * @return bool Success
     */
    public function removeFromUser($userId, $roleId) {
        $sql = "DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $roleId
        ]);
    }

    /**
     * Get user's roles
     * @param int $userId User ID
     * @return array Roles
     */
    public function getUserRoles($userId) {
        $sql = "SELECT r.*
                FROM roles r
                INNER JOIN user_roles ur ON r.id = ur.role_id
                WHERE ur.user_id = :user_id
                ORDER BY r.name";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all permissions for a user (from all their roles)
     * @param int $userId User ID
     * @return array Permission slugs
     */
    public function getUserPermissions($userId) {
        $sql = "SELECT DISTINCT p.slug
                FROM permissions p
                INNER JOIN role_permissions rp ON p.id = rp.permission_id
                INNER JOIN user_roles ur ON rp.role_id = ur.role_id
                WHERE ur.user_id = :user_id";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':user_id' => $userId]);

        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'slug');
    }
}
