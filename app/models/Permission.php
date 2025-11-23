<?php
// FILE: /app/models/Permission.php

/**
 * SplashEstate CRM - Permission Model
 * Manages system permissions
 */

class Permission extends Model {

    protected $table = 'permissions';

    /**
     * Get all permissions
     * @return array Permissions
     */
    public function getAll() {
        $sql = "SELECT * FROM {$this->table} ORDER BY module, name";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get permissions by module
     * @param string $module Module name
     * @return array Permissions
     */
    public function getByModule($module) {
        $sql = "SELECT * FROM {$this->table} WHERE module = :module ORDER BY name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':module' => $module]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get permission by slug
     * @param string $slug Permission slug
     * @return array|false Permission data
     */
    public function getBySlug($slug) {
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get grouped permissions by module
     * @return array Permissions grouped by module
     */
    public function getGroupedByModule() {
        $permissions = $this->getAll();
        $grouped = array();

        foreach ($permissions as $permission) {
            $module = $permission['module'] ?: 'general';
            if (!isset($grouped[$module])) {
                $grouped[$module] = array();
            }
            $grouped[$module][] = $permission;
        }

        return $grouped;
    }

    /**
     * Create permission
     * @param array $data Permission data
     * @return int|false Permission ID
     */
    public function create($data) {
        $sql = "INSERT INTO {$this->table} (name, slug, description, module)
                VALUES (:name, :slug, :description, :module)";

        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':description' => isset($data['description']) ? $data['description'] : null,
            ':module' => isset($data['module']) ? $data['module'] : null
        ]);

        return $result ? $this->conn->lastInsertId() : false;
    }

    /**
     * Check if permission exists by slug
     * @param string $slug Permission slug
     * @return bool Exists
     */
    public function existsBySlug($slug) {
        $sql = "SELECT COUNT(*) FROM {$this->table} WHERE slug = :slug";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        return $stmt->fetchColumn() > 0;
    }
}
