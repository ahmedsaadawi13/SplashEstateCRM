<?php
// FILE: /app/core/Migration.php

/**
 * SplashEstate CRM - Migration Base Class
 * Base class for database migrations
 */

abstract class Migration {

    protected $db;
    protected $conn;

    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }

    /**
     * Run the migration (apply changes)
     * @return bool Success
     */
    abstract public function up();

    /**
     * Reverse the migration (rollback changes)
     * @return bool Success
     */
    abstract public function down();

    /**
     * Execute raw SQL
     * @param string $sql SQL query
     * @return bool Success
     */
    protected function execute($sql) {
        try {
            $this->conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            error_log('Migration SQL error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if table exists
     * @param string $tableName Table name
     * @return bool Exists
     */
    protected function tableExists($tableName) {
        $sql = "SHOW TABLES LIKE :table_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':table_name' => $tableName]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Check if column exists
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool Exists
     */
    protected function columnExists($tableName, $columnName) {
        $sql = "SHOW COLUMNS FROM `{$tableName}` LIKE :column_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':column_name' => $columnName]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Create table
     * @param string $tableName Table name
     * @param string $definition Table definition SQL
     * @return bool Success
     */
    protected function createTable($tableName, $definition) {
        if ($this->tableExists($tableName)) {
            error_log("Table {$tableName} already exists");
            return false;
        }

        $sql = "CREATE TABLE `{$tableName}` ({$definition}) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        return $this->execute($sql);
    }

    /**
     * Drop table
     * @param string $tableName Table name
     * @return bool Success
     */
    protected function dropTable($tableName) {
        if (!$this->tableExists($tableName)) {
            error_log("Table {$tableName} does not exist");
            return false;
        }

        $sql = "DROP TABLE `{$tableName}`";
        return $this->execute($sql);
    }

    /**
     * Add column to table
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @param string $definition Column definition
     * @return bool Success
     */
    protected function addColumn($tableName, $columnName, $definition) {
        if ($this->columnExists($tableName, $columnName)) {
            error_log("Column {$columnName} already exists in {$tableName}");
            return false;
        }

        $sql = "ALTER TABLE `{$tableName}` ADD COLUMN `{$columnName}` {$definition}";
        return $this->execute($sql);
    }

    /**
     * Drop column from table
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @return bool Success
     */
    protected function dropColumn($tableName, $columnName) {
        if (!$this->columnExists($tableName, $columnName)) {
            error_log("Column {$columnName} does not exist in {$tableName}");
            return false;
        }

        $sql = "ALTER TABLE `{$tableName}` DROP COLUMN `{$columnName}`";
        return $this->execute($sql);
    }

    /**
     * Modify column
     * @param string $tableName Table name
     * @param string $columnName Column name
     * @param string $definition New column definition
     * @return bool Success
     */
    protected function modifyColumn($tableName, $columnName, $definition) {
        if (!$this->columnExists($tableName, $columnName)) {
            error_log("Column {$columnName} does not exist in {$tableName}");
            return false;
        }

        $sql = "ALTER TABLE `{$tableName}` MODIFY COLUMN `{$columnName}` {$definition}";
        return $this->execute($sql);
    }

    /**
     * Add index
     * @param string $tableName Table name
     * @param string $indexName Index name
     * @param string|array $columns Column(s)
     * @param string $type Index type (INDEX, UNIQUE, FULLTEXT)
     * @return bool Success
     */
    protected function addIndex($tableName, $indexName, $columns, $type = 'INDEX') {
        if (is_array($columns)) {
            $columns = '`' . implode('`, `', $columns) . '`';
        } else {
            $columns = "`{$columns}`";
        }

        $sql = "ALTER TABLE `{$tableName}` ADD {$type} `{$indexName}` ({$columns})";
        return $this->execute($sql);
    }

    /**
     * Drop index
     * @param string $tableName Table name
     * @param string $indexName Index name
     * @return bool Success
     */
    protected function dropIndex($tableName, $indexName) {
        $sql = "ALTER TABLE `{$tableName}` DROP INDEX `{$indexName}`";
        return $this->execute($sql);
    }

    /**
     * Insert data
     * @param string $tableName Table name
     * @param array $data Data to insert
     * @return bool Success
     */
    protected function insert($tableName, $data) {
        $columns = array_keys($data);
        $values = array_values($data);

        $columnList = '`' . implode('`, `', $columns) . '`';
        $placeholders = ':' . implode(', :', $columns);

        $sql = "INSERT INTO `{$tableName}` ({$columnList}) VALUES ({$placeholders})";

        try {
            $stmt = $this->conn->prepare($sql);
            foreach ($data as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log('Migration insert error: ' . $e->getMessage());
            return false;
        }
    }
}
