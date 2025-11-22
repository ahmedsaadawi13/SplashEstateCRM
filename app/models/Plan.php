<?php
// FILE: /app/models/Plan.php

/**
 * SplashEstate CRM - Plan Model
 * Handles subscription plans
 */

class Plan extends Model {

    protected $table = 'plans';

    /**
     * Get all active plans
     * @return array Plans
     */
    public function getActivePlans() {
        $sql = "SELECT * FROM {$this->table} WHERE status = 'active' ORDER BY price ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Get plan by ID
     * @param int $id Plan ID
     * @return mixed Plan data or false
     */
    public function getById($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Get plan by slug
     * @param string $slug Plan slug
     * @return mixed Plan data or false
     */
    public function getBySlug($slug) {
        $sql = "SELECT * FROM {$this->table} WHERE slug = :slug LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':slug', $slug);
        $stmt->execute();
        return $stmt->fetch();
    }

    /**
     * Create plan
     * @param array $data Plan data
     * @return int|bool Plan ID or false
     */
    public function createPlan($data) {
        $sql = "INSERT INTO {$this->table} (name, slug, description, price, billing_cycle, max_leads, max_properties, max_agents, features, status, created_at)
                VALUES (:name, :slug, :description, :price, :billing_cycle, :max_leads, :max_properties, :max_agents, :features, :status, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':name', $data['name']);
        $stmt->bindValue(':slug', $data['slug']);
        $stmt->bindValue(':description', isset($data['description']) ? $data['description'] : null);
        $stmt->bindValue(':price', $data['price']);
        $stmt->bindValue(':billing_cycle', isset($data['billing_cycle']) ? $data['billing_cycle'] : 'monthly');
        $stmt->bindValue(':max_leads', isset($data['max_leads']) ? $data['max_leads'] : -1, PDO::PARAM_INT);
        $stmt->bindValue(':max_properties', isset($data['max_properties']) ? $data['max_properties'] : -1, PDO::PARAM_INT);
        $stmt->bindValue(':max_agents', isset($data['max_agents']) ? $data['max_agents'] : -1, PDO::PARAM_INT);
        $stmt->bindValue(':features', isset($data['features']) ? $data['features'] : null);
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'active');

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }

    /**
     * Update plan
     * @param int $id Plan ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updatePlan($id, $data) {
        $fields = array();
        foreach (array_keys($data) as $field) {
            $fields[] = "{$field} = :{$field}";
        }
        $fieldList = implode(', ', $fields);

        $sql = "UPDATE {$this->table} SET {$fieldList}, updated_at = NOW() WHERE id = :id";
        $stmt = $this->conn->prepare($sql);

        foreach ($data as $key => $value) {
            $stmt->bindValue(':' . $key, $value);
        }
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);

        return $stmt->execute();
    }
}
