<?php
// FILE: /app/models/Property.php

/**
 * SplashEstate CRM - Property Model
 * Handles property listings
 */

class Property extends Model {

    protected $table = 'properties';

    /**
     * Get properties with related info
     * @param int $tenantId Tenant ID
     * @param int $page Page number
     * @param int $perPage Records per page
     * @param array $filters Filters
     * @return array Properties
     */
    public function getPropertiesWithInfo($tenantId, $page = 1, $perPage = 20, $filters = array()) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT p.*,
                CONCAT(u.first_name, ' ', u.last_name) as agent_name,
                CONCAT(c.first_name, ' ', c.last_name) as owner_name
                FROM {$this->table} p
                LEFT JOIN users u ON p.listing_agent_id = u.id
                LEFT JOIN clients c ON p.owner_id = c.id
                WHERE p.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        // Add filters
        if (!empty($filters['property_type'])) {
            $sql .= " AND p.property_type = :property_type";
            $params[':property_type'] = $filters['property_type'];
        }

        if (!empty($filters['listing_type'])) {
            $sql .= " AND p.listing_type = :listing_type";
            $params[':listing_type'] = $filters['listing_type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND p.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Search
        if (!empty($filters['search'])) {
            $sql .= " AND (p.title LIKE :search OR p.address LIKE :search OR p.city LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', (int)$perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Create property
     * @param array $data Property data
     * @return int|bool Property ID or false
     */
    public function createProperty($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, owner_id, listing_agent_id, title, description, property_type, sub_type, listing_type, price,
                bedrooms, bathrooms, square_feet, lot_size, year_built, address, city, state, zip, latitude, longitude, status, created_at)
                VALUES (:tenant_id, :owner_id, :listing_agent_id, :title, :description, :property_type, :sub_type, :listing_type, :price,
                :bedrooms, :bathrooms, :square_feet, :lot_size, :year_built, :address, :city, :state, :zip, :latitude, :longitude, :status, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':owner_id', isset($data['owner_id']) ? $data['owner_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':listing_agent_id', isset($data['listing_agent_id']) ? $data['listing_agent_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':title', $data['title']);
        $stmt->bindValue(':description', isset($data['description']) ? $data['description'] : null);
        $stmt->bindValue(':property_type', $data['property_type']);
        $stmt->bindValue(':sub_type', isset($data['sub_type']) ? $data['sub_type'] : null);
        $stmt->bindValue(':listing_type', $data['listing_type']);
        $stmt->bindValue(':price', $data['price']);
        $stmt->bindValue(':bedrooms', isset($data['bedrooms']) ? $data['bedrooms'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':bathrooms', isset($data['bathrooms']) ? $data['bathrooms'] : null);
        $stmt->bindValue(':square_feet', isset($data['square_feet']) ? $data['square_feet'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':lot_size', isset($data['lot_size']) ? $data['lot_size'] : null);
        $stmt->bindValue(':year_built', isset($data['year_built']) ? $data['year_built'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':address', $data['address']);
        $stmt->bindValue(':city', $data['city']);
        $stmt->bindValue(':state', $data['state']);
        $stmt->bindValue(':zip', $data['zip']);
        $stmt->bindValue(':latitude', isset($data['latitude']) ? $data['latitude'] : null);
        $stmt->bindValue(':longitude', isset($data['longitude']) ? $data['longitude'] : null);
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'available');

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }
}
