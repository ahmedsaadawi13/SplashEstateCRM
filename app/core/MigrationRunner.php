<?php
// FILE: /app/core/MigrationRunner.php

/**
 * SplashEstate CRM - Migration Runner
 * Manages database migrations
 */

class MigrationRunner {

    private $db;
    private $conn;
    private $migrationsPath;
    private $migrationsTable = 'migrations';

    public function __construct() {
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
        $this->migrationsPath = ROOT_PATH . '/database/migrations';

        $this->ensureMigrationsTable();
    }

    /**
     * Ensure migrations tracking table exists
     */
    private function ensureMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `migration` varchar(255) NOT NULL,
            `batch` int(11) NOT NULL,
            `executed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `migration` (`migration`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->conn->exec($sql);
    }

    /**
     * Run pending migrations
     * @return array Results
     */
    public function migrate() {
        $pending = $this->getPendingMigrations();

        if (empty($pending)) {
            return array(
                'success' => true,
                'message' => 'No pending migrations',
                'migrations' => array()
            );
        }

        $batch = $this->getNextBatchNumber();
        $executed = array();
        $failed = array();

        foreach ($pending as $migrationFile) {
            $result = $this->runMigration($migrationFile, $batch);

            if ($result['success']) {
                $executed[] = $migrationFile;
            } else {
                $failed[] = array(
                    'migration' => $migrationFile,
                    'error' => $result['error']
                );
                break; // Stop on first failure
            }
        }

        return array(
            'success' => empty($failed),
            'executed' => $executed,
            'failed' => $failed,
            'message' => count($executed) . ' migration(s) executed successfully'
        );
    }

    /**
     * Rollback last batch of migrations
     * @return array Results
     */
    public function rollback() {
        $lastBatch = $this->getLastBatch();

        if (empty($lastBatch)) {
            return array(
                'success' => true,
                'message' => 'No migrations to rollback',
                'migrations' => array()
            );
        }

        $rolledBack = array();
        $failed = array();

        // Reverse order for rollback
        $lastBatch = array_reverse($lastBatch);

        foreach ($lastBatch as $migration) {
            $result = $this->rollbackMigration($migration['migration']);

            if ($result['success']) {
                $rolledBack[] = $migration['migration'];
            } else {
                $failed[] = array(
                    'migration' => $migration['migration'],
                    'error' => $result['error']
                );
                break;
            }
        }

        return array(
            'success' => empty($failed),
            'rolled_back' => $rolledBack,
            'failed' => $failed,
            'message' => count($rolledBack) . ' migration(s) rolled back'
        );
    }

    /**
     * Get migration status
     * @return array Status info
     */
    public function status() {
        $all = $this->getAllMigrationFiles();
        $executed = $this->getExecutedMigrations();

        $status = array();

        foreach ($all as $migration) {
            $isExecuted = in_array($migration, array_column($executed, 'migration'));
            $batch = null;

            if ($isExecuted) {
                foreach ($executed as $exec) {
                    if ($exec['migration'] === $migration) {
                        $batch = $exec['batch'];
                        break;
                    }
                }
            }

            $status[] = array(
                'migration' => $migration,
                'status' => $isExecuted ? 'executed' : 'pending',
                'batch' => $batch
            );
        }

        return $status;
    }

    /**
     * Run a single migration
     * @param string $migrationFile Migration filename
     * @param int $batch Batch number
     * @return array Result
     */
    private function runMigration($migrationFile, $batch) {
        $migrationClass = $this->getMigrationClass($migrationFile);
        $migrationPath = $this->migrationsPath . '/' . $migrationFile;

        if (!file_exists($migrationPath)) {
            return array(
                'success' => false,
                'error' => 'Migration file not found: ' . $migrationFile
            );
        }

        require_once $migrationPath;

        if (!class_exists($migrationClass)) {
            return array(
                'success' => false,
                'error' => 'Migration class not found: ' . $migrationClass
            );
        }

        try {
            $migration = new $migrationClass();
            $success = $migration->up();

            if ($success) {
                $this->recordMigration($migrationFile, $batch);
                return array('success' => true);
            } else {
                return array(
                    'success' => false,
                    'error' => 'Migration up() method returned false'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Rollback a single migration
     * @param string $migrationFile Migration filename
     * @return array Result
     */
    private function rollbackMigration($migrationFile) {
        $migrationClass = $this->getMigrationClass($migrationFile);
        $migrationPath = $this->migrationsPath . '/' . $migrationFile;

        require_once $migrationPath;

        try {
            $migration = new $migrationClass();
            $success = $migration->down();

            if ($success) {
                $this->removeMigration($migrationFile);
                return array('success' => true);
            } else {
                return array(
                    'success' => false,
                    'error' => 'Migration down() method returned false'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Get pending migrations
     * @return array Migration files
     */
    private function getPendingMigrations() {
        $all = $this->getAllMigrationFiles();
        $executed = array_column($this->getExecutedMigrations(), 'migration');

        return array_diff($all, $executed);
    }

    /**
     * Get all migration files
     * @return array Migration files
     */
    private function getAllMigrationFiles() {
        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        $files = scandir($this->migrationsPath);
        $migrations = array();

        foreach ($files as $file) {
            if (preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_.+\.php$/', $file)) {
                $migrations[] = $file;
            }
        }

        sort($migrations);
        return $migrations;
    }

    /**
     * Get executed migrations
     * @return array Executed migrations
     */
    private function getExecutedMigrations() {
        $sql = "SELECT migration, batch FROM {$this->migrationsTable} ORDER BY id ASC";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get last batch
     * @return array Last batch migrations
     */
    private function getLastBatch() {
        $sql = "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}";
        $stmt = $this->conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['max_batch']) {
            return array();
        }

        $batch = $result['max_batch'];

        $sql = "SELECT migration FROM {$this->migrationsTable} WHERE batch = :batch ORDER BY id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':batch' => $batch]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get next batch number
     * @return int Next batch number
     */
    private function getNextBatchNumber() {
        $sql = "SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}";
        $stmt = $this->conn->query($sql);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return ($result && $result['max_batch']) ? (int)$result['max_batch'] + 1 : 1;
    }

    /**
     * Record migration as executed
     * @param string $migration Migration filename
     * @param int $batch Batch number
     */
    private function recordMigration($migration, $batch) {
        $sql = "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (:migration, :batch)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':migration' => $migration,
            ':batch' => $batch
        ]);
    }

    /**
     * Remove migration record
     * @param string $migration Migration filename
     */
    private function removeMigration($migration) {
        $sql = "DELETE FROM {$this->migrationsTable} WHERE migration = :migration";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':migration' => $migration]);
    }

    /**
     * Get migration class name from filename
     * @param string $filename Migration filename
     * @return string Class name
     */
    private function getMigrationClass($filename) {
        // Extract class name from filename
        // Format: 2025_01_15_120000_create_users_table.php -> CreateUsersTable
        $parts = explode('_', str_replace('.php', '', $filename));
        array_splice($parts, 0, 4); // Remove date/time parts

        $className = '';
        foreach ($parts as $part) {
            $className .= ucfirst($part);
        }

        return $className;
    }

    /**
     * Create new migration file
     * @param string $name Migration name (snake_case)
     * @return string Migration filename
     */
    public function create($name) {
        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_{$name}.php";
        $className = $this->getMigrationClass($filename);

        $template = <<<PHP
<?php
// FILE: /database/migrations/{$filename}

/**
 * Migration: {$className}
 */

class {$className} extends Migration {

    /**
     * Run the migration
     */
    public function up() {
        // Add your migration logic here
        // Example:
        // \$this->createTable('table_name', '
        //     id INT AUTO_INCREMENT PRIMARY KEY,
        //     name VARCHAR(255) NOT NULL,
        //     created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        // ');

        return true;
    }

    /**
     * Reverse the migration
     */
    public function down() {
        // Add your rollback logic here
        // Example:
        // \$this->dropTable('table_name');

        return true;
    }
}
PHP;

        $filepath = $this->migrationsPath . '/' . $filename;

        if (!is_dir($this->migrationsPath)) {
            mkdir($this->migrationsPath, 0755, true);
        }

        file_put_contents($filepath, $template);

        return $filename;
    }
}
