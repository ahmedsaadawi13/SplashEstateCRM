<?php
// FILE: /app/controllers/HealthController.php

/**
 * SplashEstate CRM - Health Check Controller
 * Provides health check and system status endpoints for monitoring
 */

class HealthController extends Controller {

    /**
     * Basic health check endpoint
     * Returns 200 OK if application is running
     */
    public function index() {
        $this->jsonResponse(array(
            'status' => 'healthy',
            'timestamp' => time(),
            'version' => '1.0.0'
        ));
    }

    /**
     * Detailed health check with system diagnostics
     * Checks database, filesystem, and critical services
     */
    public function detailed() {
        $checks = array();
        $overallStatus = 'healthy';

        // Database check
        $dbCheck = $this->checkDatabase();
        $checks['database'] = $dbCheck;
        if (!$dbCheck['healthy']) $overallStatus = 'unhealthy';

        // Filesystem check
        $fsCheck = $this->checkFilesystem();
        $checks['filesystem'] = $fsCheck;
        if (!$fsCheck['healthy']) $overallStatus = 'degraded';

        // Configuration check
        $configCheck = $this->checkConfiguration();
        $checks['configuration'] = $configCheck;
        if (!$configCheck['healthy']) $overallStatus = 'degraded';

        // PHP environment check
        $phpCheck = $this->checkPHP();
        $checks['php'] = $phpCheck;

        // External services check
        $servicesCheck = $this->checkExternalServices();
        $checks['external_services'] = $servicesCheck;

        $response = array(
            'status' => $overallStatus,
            'timestamp' => time(),
            'version' => '1.0.0',
            'checks' => $checks,
            'system' => array(
                'php_version' => PHP_VERSION,
                'server_software' => isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : 'Unknown',
                'environment' => defined('APP_ENV') ? APP_ENV : 'Unknown'
            )
        );

        $httpCode = $overallStatus === 'healthy' ? 200 : ($overallStatus === 'degraded' ? 200 : 503);

        $this->jsonResponse($response, $httpCode);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase() {
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();

            // Test query
            $stmt = $conn->query('SELECT 1');
            $result = $stmt->fetch();

            if ($result) {
                return array(
                    'healthy' => true,
                    'message' => 'Database connection successful',
                    'details' => array(
                        'host' => defined('DB_HOST') ? DB_HOST : 'Unknown',
                        'database' => defined('DB_NAME') ? DB_NAME : 'Unknown'
                    )
                );
            } else {
                return array(
                    'healthy' => false,
                    'message' => 'Database query failed'
                );
            }
        } catch (Exception $e) {
            return array(
                'healthy' => false,
                'message' => 'Database connection failed',
                'error' => $e->getMessage()
            );
        }
    }

    /**
     * Check filesystem permissions and writability
     */
    private function checkFilesystem() {
        $checks = array();
        $healthy = true;

        // Check storage directory
        if (is_dir(STORAGE_PATH) && is_writable(STORAGE_PATH)) {
            $checks['storage'] = 'writable';
        } else {
            $checks['storage'] = 'not writable';
            $healthy = false;
        }

        // Check uploads directory
        if (is_dir(UPLOAD_PATH) && is_writable(UPLOAD_PATH)) {
            $checks['uploads'] = 'writable';
        } else {
            $checks['uploads'] = 'not writable';
            $healthy = false;
        }

        // Check logs directory
        $logsPath = STORAGE_PATH . '/logs';
        if (is_dir($logsPath) && is_writable($logsPath)) {
            $checks['logs'] = 'writable';
        } else {
            $checks['logs'] = 'not writable or missing';
        }

        // Check disk space
        $diskFree = @disk_free_space(ROOT_PATH);
        $diskTotal = @disk_total_space(ROOT_PATH);

        if ($diskFree && $diskTotal) {
            $diskUsedPercent = (($diskTotal - $diskFree) / $diskTotal) * 100;
            $checks['disk_space'] = array(
                'free' => $this->formatBytes($diskFree),
                'total' => $this->formatBytes($diskTotal),
                'used_percent' => round($diskUsedPercent, 2)
            );

            if ($diskUsedPercent > 90) {
                $healthy = false;
            }
        }

        return array(
            'healthy' => $healthy,
            'message' => $healthy ? 'Filesystem checks passed' : 'Some filesystem issues detected',
            'details' => $checks
        );
    }

    /**
     * Check critical configuration
     */
    private function checkConfiguration() {
        $issues = array();
        $healthy = true;

        // Check .env file
        if (!file_exists(ROOT_PATH . '/.env')) {
            $issues[] = '.env file missing';
            $healthy = false;
        }

        // Check database configuration
        if (!defined('DB_HOST') || empty(DB_HOST)) {
            $issues[] = 'DB_HOST not configured';
            $healthy = false;
        }

        if (!defined('DB_NAME') || empty(DB_NAME)) {
            $issues[] = 'DB_NAME not configured';
            $healthy = false;
        }

        // Check BASE_URL
        if (!defined('BASE_URL') || empty(BASE_URL)) {
            $issues[] = 'BASE_URL not configured';
        }

        return array(
            'healthy' => $healthy,
            'message' => $healthy ? 'Configuration valid' : 'Configuration issues found',
            'issues' => $issues
        );
    }

    /**
     * Check PHP environment and extensions
     */
    private function checkPHP() {
        $requiredExtensions = array('pdo', 'pdo_mysql', 'mbstring', 'curl', 'json');
        $loadedExtensions = get_loaded_extensions();
        $missingExtensions = array();

        foreach ($requiredExtensions as $ext) {
            if (!in_array($ext, $loadedExtensions)) {
                $missingExtensions[] = $ext;
            }
        }

        return array(
            'healthy' => empty($missingExtensions),
            'message' => empty($missingExtensions) ? 'All required extensions loaded' : 'Missing PHP extensions',
            'details' => array(
                'version' => PHP_VERSION,
                'required_extensions' => $requiredExtensions,
                'missing_extensions' => $missingExtensions,
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize')
            )
        );
    }

    /**
     * Check external service connectivity
     */
    private function checkExternalServices() {
        $services = array();

        // Check Stripe
        if (defined('STRIPE_SECRET_KEY') && !empty(STRIPE_SECRET_KEY)) {
            $services['stripe'] = array(
                'configured' => true,
                'status' => 'configured'
            );
        } else {
            $services['stripe'] = array(
                'configured' => false,
                'status' => 'not configured'
            );
        }

        // Check Google Maps
        if (defined('GOOGLE_MAPS_API_KEY') && !empty(GOOGLE_MAPS_API_KEY)) {
            $services['google_maps'] = array(
                'configured' => true,
                'status' => 'configured'
            );
        } else {
            $services['google_maps'] = array(
                'configured' => false,
                'status' => 'not configured'
            );
        }

        // Check SMTP
        if (defined('MAIL_HOST') && !empty(MAIL_HOST) && defined('MAIL_USERNAME') && !empty(MAIL_USERNAME)) {
            $services['smtp'] = array(
                'configured' => true,
                'status' => 'configured',
                'host' => MAIL_HOST,
                'port' => defined('MAIL_PORT') ? MAIL_PORT : 'Unknown'
            );
        } else {
            $services['smtp'] = array(
                'configured' => false,
                'status' => 'not configured'
            );
        }

        return array(
            'healthy' => true,
            'message' => 'External services check completed',
            'details' => $services
        );
    }

    /**
     * Format bytes to human-readable size
     */
    private function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Readiness check
     * Returns whether the application is ready to serve traffic
     */
    public function ready() {
        $ready = true;
        $checks = array();

        // Database must be accessible
        $dbCheck = $this->checkDatabase();
        $checks['database'] = $dbCheck['healthy'];
        if (!$dbCheck['healthy']) $ready = false;

        // Critical paths must be writable
        $fsCheck = $this->checkFilesystem();
        $checks['filesystem'] = $fsCheck['healthy'];
        if (!$fsCheck['healthy']) $ready = false;

        $response = array(
            'ready' => $ready,
            'timestamp' => time(),
            'checks' => $checks
        );

        $this->jsonResponse($response, $ready ? 200 : 503);
    }

    /**
     * Liveness check
     * Simple check to see if the application process is alive
     */
    public function alive() {
        $this->jsonResponse(array(
            'alive' => true,
            'timestamp' => time()
        ));
    }
}
