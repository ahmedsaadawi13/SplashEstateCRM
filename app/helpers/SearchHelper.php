<?php
// FILE: /app/helpers/SearchHelper.php

/**
 * SplashEstate CRM - Search Helper
 * Builds complex search queries with multiple filters
 */

class SearchHelper {

    private $db;
    private $conditions = array();
    private $params = array();
    private $orderBy = '';
    private $limit = '';

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Add a simple WHERE condition
     * @param string $field Field name
     * @param mixed $value Field value
     * @param string $operator Comparison operator (=, !=, >, <, >=, <=, LIKE, IN)
     * @return SearchHelper For method chaining
     */
    public function where($field, $value, $operator = '=') {
        if ($value === null || $value === '') {
            return $this;
        }

        if ($operator === 'LIKE') {
            $this->conditions[] = "$field LIKE :param_" . count($this->params);
            $this->params[':param_' . count($this->params)] = '%' . $value . '%';
        } elseif ($operator === 'IN' && is_array($value)) {
            $placeholders = array();
            foreach ($value as $v) {
                $paramName = ':param_' . count($this->params);
                $placeholders[] = $paramName;
                $this->params[$paramName] = $v;
            }
            $this->conditions[] = "$field IN (" . implode(', ', $placeholders) . ")";
        } else {
            $this->conditions[] = "$field $operator :param_" . count($this->params);
            $this->params[':param_' . count($this->params)] = $value;
        }

        return $this;
    }

    /**
     * Add date range filter
     * @param string $field Date field name
     * @param string $startDate Start date (Y-m-d)
     * @param string $endDate End date (Y-m-d)
     * @return SearchHelper For method chaining
     */
    public function dateRange($field, $startDate, $endDate) {
        if (!empty($startDate)) {
            $this->conditions[] = "$field >= :param_" . count($this->params);
            $this->params[':param_' . count($this->params)] = $startDate . ' 00:00:00';
        }

        if (!empty($endDate)) {
            $this->conditions[] = "$field <= :param_" . count($this->params);
            $this->params[':param_' . count($this->params)] = $endDate . ' 23:59:59';
        }

        return $this;
    }

    /**
     * Add numeric range filter
     * @param string $field Numeric field name
     * @param float $min Minimum value
     * @param float $max Maximum value
     * @return SearchHelper For method chaining
     */
    public function numericRange($field, $min, $max) {
        if ($min !== null && $min !== '') {
            $this->conditions[] = "$field >= :param_" . count($this->params);
            $this->params[':param_' . count($this->params)] = $min;
        }

        if ($max !== null && $max !== '') {
            $this->conditions[] = "$field <= :param_" . count($this->params);
            $this->params[':param_' . count($this->params)] = $max;
        }

        return $this;
    }

    /**
     * Add full-text search across multiple fields
     * @param array $fields Array of field names
     * @param string $query Search query
     * @return SearchHelper For method chaining
     */
    public function search($fields, $query) {
        if (empty($query)) {
            return $this;
        }

        $searchConditions = array();
        foreach ($fields as $field) {
            $paramName = ':search_' . str_replace('.', '_', $field);
            $searchConditions[] = "$field LIKE $paramName";
            $this->params[$paramName] = '%' . $query . '%';
        }

        if (!empty($searchConditions)) {
            $this->conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
        }

        return $this;
    }

    /**
     * Add OR condition group
     * @param array $orConditions Array of conditions to be OR'd together
     * @return SearchHelper For method chaining
     */
    public function orWhere($orConditions) {
        if (empty($orConditions)) {
            return $this;
        }

        $orParts = array();
        foreach ($orConditions as $field => $value) {
            $paramName = ':param_' . count($this->params);
            $orParts[] = "$field = $paramName";
            $this->params[$paramName] = $value;
        }

        if (!empty($orParts)) {
            $this->conditions[] = '(' . implode(' OR ', $orParts) . ')';
        }

        return $this;
    }

    /**
     * Set ORDER BY clause
     * @param string $field Field to order by
     * @param string $direction ASC or DESC
     * @return SearchHelper For method chaining
     */
    public function orderBy($field, $direction = 'ASC') {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        $this->orderBy = "ORDER BY $field $direction";
        return $this;
    }

    /**
     * Set LIMIT and OFFSET
     * @param int $limit Number of records
     * @param int $offset Starting offset
     * @return SearchHelper For method chaining
     */
    public function limit($limit, $offset = 0) {
        $this->limit = "LIMIT $offset, $limit";
        return $this;
    }

    /**
     * Get WHERE clause
     * @return string WHERE clause (without WHERE keyword)
     */
    public function getWhereClause() {
        if (empty($this->conditions)) {
            return '';
        }
        return implode(' AND ', $this->conditions);
    }

    /**
     * Get all bound parameters
     * @return array Parameters for PDO binding
     */
    public function getParams() {
        return $this->params;
    }

    /**
     * Build complete SQL query
     * @param string $baseQuery Base SELECT query (e.g., "SELECT * FROM leads")
     * @param string $additionalWhere Additional WHERE conditions (will be AND'd)
     * @return string Complete SQL query
     */
    public function buildQuery($baseQuery, $additionalWhere = '') {
        $query = $baseQuery;

        $whereParts = array();
        if (!empty($additionalWhere)) {
            $whereParts[] = $additionalWhere;
        }
        if (!empty($this->conditions)) {
            $whereParts[] = $this->getWhereClause();
        }

        if (!empty($whereParts)) {
            $query .= ' WHERE ' . implode(' AND ', $whereParts);
        }

        if (!empty($this->orderBy)) {
            $query .= ' ' . $this->orderBy;
        }

        if (!empty($this->limit)) {
            $query .= ' ' . $this->limit;
        }

        return $query;
    }

    /**
     * Execute query and return results
     * @param string $baseQuery Base SELECT query
     * @param string $additionalWhere Additional WHERE conditions
     * @return array Query results
     */
    public function execute($baseQuery, $additionalWhere = '') {
        $query = $this->buildQuery($baseQuery, $additionalWhere);

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($this->params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            error_log("Search query error: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Execute COUNT query
     * @param string $table Table name
     * @param string $additionalWhere Additional WHERE conditions
     * @return int Total count
     */
    public function count($table, $additionalWhere = '') {
        $baseQuery = "SELECT COUNT(*) as total FROM $table";
        $query = $this->buildQuery($baseQuery, $additionalWhere);

        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute($this->params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)$result['total'];
        } catch(PDOException $e) {
            error_log("Count query error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Reset all filters
     * @return SearchHelper For method chaining
     */
    public function reset() {
        $this->conditions = array();
        $this->params = array();
        $this->orderBy = '';
        $this->limit = '';
        return $this;
    }
}
