<?php
// FILE: /app/models/User.php

/**
 * SplashEstate CRM - User Model
 * Handles user authentication and management
 */

class User extends Model {

    protected $table = 'users';

    /**
     * Find user by email
     * @param string $email User email
     * @return mixed User data or false
     */
    public function findByEmail($email) {
        $sql = "SELECT * FROM {$this->table} WHERE email = :email LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Authenticate user
     * @param string $email User email
     * @param string $password User password
     * @return mixed User data or false
     */
    public function authenticate($email, $password) {
        $user = $this->findByEmail($email);

        if ($user && password_verify($password, $user['password'])) {
            // Update last login
            $this->updateLastLogin($user['id']);
            return $user;
        }

        return false;
    }

    /**
     * Create new user
     * @param array $data User data
     * @return int|bool User ID or false
     */
    public function createUser($data) {
        // Hash password
        if (isset($data['password'])) {
            $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        $sql = "INSERT INTO {$this->table} (tenant_id, email, password, first_name, last_name, phone, role, status, created_at)
                VALUES (:tenant_id, :email, :password, :first_name, :last_name, :phone, :role, :status, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':email', $data['email']);
        $stmt->bindValue(':password', $data['password']);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':phone', isset($data['phone']) ? $data['phone'] : null);
        $stmt->bindValue(':role', isset($data['role']) ? $data['role'] : 'agent');
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'active');

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Update last login timestamp
     * @param int $userId User ID
     */
    private function updateLastLogin($userId) {
        $sql = "UPDATE {$this->table} SET last_login = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Update user password
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return bool Success status
     */
    public function updatePassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

        $sql = "UPDATE {$this->table} SET password = :password WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Get users by tenant
     * @param int $tenantId Tenant ID
     * @param string $role Filter by role (optional)
     * @return array Users
     */
    public function getUsersByTenant($tenantId, $role = null) {
        $sql = "SELECT id, email, first_name, last_name, phone, role, status, last_login, created_at
                FROM {$this->table}
                WHERE tenant_id = :tenant_id";

        if ($role !== null) {
            $sql .= " AND role = :role";
        }

        $sql .= " ORDER BY first_name ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);

        if ($role !== null) {
            $stmt->bindValue(':role', $role);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Update user profile
     * @param int $userId User ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updateProfile($userId, $data) {
        $sql = "UPDATE {$this->table}
                SET first_name = :first_name,
                    last_name = :last_name,
                    phone = :phone,
                    updated_at = NOW()
                WHERE id = :id";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', $data['last_name']);
        $stmt->bindValue(':phone', isset($data['phone']) ? $data['phone'] : null);
        $stmt->bindValue(':id', $userId, PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Check if email exists
     * @param string $email Email to check
     * @param int $excludeUserId User ID to exclude from check
     * @return bool
     */
    public function emailExists($email, $excludeUserId = null) {
        $sql = "SELECT COUNT(*) as count FROM {$this->table} WHERE email = :email";

        if ($excludeUserId !== null) {
            $sql .= " AND id != :exclude_id";
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':email', $email);

        if ($excludeUserId !== null) {
            $stmt->bindValue(':exclude_id', $excludeUserId, PDO::PARAM_INT);
        }

        $stmt->execute();
        $result = $stmt->fetch();

        return (int)$result['count'] > 0;
    }
}
