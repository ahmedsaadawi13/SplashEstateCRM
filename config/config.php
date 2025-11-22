<?php
// FILE: /config/config.php

/**
 * SplashEstate CRM - Configuration File
 * Loads environment variables and defines constants
 */

// Load environment variables from .env file
if (file_exists(dirname(__DIR__) . '/.env')) {
    $lines = file(dirname(__DIR__) . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }

        // Parse key=value
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove quotes if present
            if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                $value = $matches[2];
            }

            // Set as constant if not already defined
            if (!defined($key)) {
                define($key, $value);
            }
        }
    }
}

// Database Configuration
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'splashestate_crm');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');

// Application Configuration
if (!defined('APP_NAME')) define('APP_NAME', 'SplashEstate CRM');
if (!defined('APP_ENV')) define('APP_ENV', 'development');
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/SplashEstateCRM/public');

// Session Configuration
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 7200);

// Upload Configuration
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 5242880); // 5MB
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx,xls,xlsx');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('PUBLIC_PATH', ROOT_PATH . '/public');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');

// Timezone
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'UTC');
date_default_timezone_set(APP_TIMEZONE);

// Error Reporting
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
