<?php
// FILE: /app/models/Client.php

/**
 * SplashEstate CRM - Client Model
 * Handles client management
 */

class Client extends Model {

    protected $table = 'clients';

    /**
     * Get clients with lead info
     * @param int $tenantId Tenant ID
     * @param int $page Page number
     * @param int $perPage Records per page
     * @param array $filters Filters
     * @return array Clients
     */
    public function getClientsWithInfo($tenantId, $page = 1, $perPage = 20, $filters = array()) {
        $offset = ($page - 1) * $perPage;

        $sql = "SELECT c.*, l.source as lead_source
                FROM {$this->table} c
                LEFT JOIN leads l ON c.lead_id = l.id
                WHERE c.tenant_id = :tenant_id";

        $params = array(':tenant_id' => $tenantId);

        // Add filters
        if (!empty($filters['client_type'])) {
            $sql .= " AND c.client_type = :client_type";
            $params[':client_type'] = $filters['client_type'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Search
        if (!empty($filters['search'])) {
            $sql .= " AND (c.first_name LIKE :search OR c.last_name LIKE :search OR c.email LIKE :search OR c.phone LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";

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
     * Create client
     * @param array $data Client data
     * @return int|bool Client ID or false
     */
    public function createClient($data) {
        $sql = "INSERT INTO {$this->table}
                (tenant_id, lead_id, first_name, last_name, email, phone, phone_secondary, address, city, state, zip, client_type, status, notes, created_at)
                VALUES (:tenant_id, :lead_id, :first_name, :last_name, :email, :phone, :phone_secondary, :address, :city, :state, :zip, :client_type, :status, :notes, NOW())";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
        $stmt->bindValue(':lead_id', isset($data['lead_id']) ? $data['lead_id'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':first_name', $data['first_name']);
        $stmt->bindValue(':last_name', isset($data['last_name']) ? $data['last_name'] : null);
        $stmt->bindValue(':email', isset($data['email']) ? $data['email'] : null);
        $stmt->bindValue(':phone', isset($data['phone']) ? $data['phone'] : null);
        $stmt->bindValue(':phone_secondary', isset($data['phone_secondary']) ? $data['phone_secondary'] : null);
        $stmt->bindValue(':address', isset($data['address']) ? $data['address'] : null);
        $stmt->bindValue(':city', isset($data['city']) ? $data['city'] : null);
        $stmt->bindValue(':state', isset($data['state']) ? $data['state'] : null);
        $stmt->bindValue(':zip', isset($data['zip']) ? $data['zip'] : null);
        $stmt->bindValue(':client_type', isset($data['client_type']) ? $data['client_type'] : 'buyer');
        $stmt->bindValue(':status', isset($data['status']) ? $data['status'] : 'active');
        $stmt->bindValue(':notes', isset($data['notes']) ? $data['notes'] : null);

        if ($stmt->execute()) {
            return $this->conn->lastInsertId();
        }

        return false;
    }
}
