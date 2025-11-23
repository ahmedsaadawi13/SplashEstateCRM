<?php
// FILE: /tests/bootstrap.php

/**
 * SplashEstate CRM - Test Bootstrap
 * Initializes test environment
 */

// Define test mode
define('TEST_MODE', true);

// Set up test environment variables
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', 'splashestate_crm_test');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('APP_NAME')) define('APP_NAME', 'SplashEstate CRM Test');
if (!defined('APP_ENV')) define('APP_ENV', 'testing');
if (!defined('APP_DEBUG')) define('APP_DEBUG', true);
if (!defined('BASE_URL')) define('BASE_URL', 'http://localhost/SplashEstateCRM/public');
if (!defined('SESSION_LIFETIME')) define('SESSION_LIFETIME', 7200);
if (!defined('MAX_UPLOAD_SIZE')) define('MAX_UPLOAD_SIZE', 5242880);
if (!defined('ALLOWED_FILE_TYPES')) define('ALLOWED_FILE_TYPES', 'jpg,jpeg,png,pdf,doc,docx');
if (!defined('APP_TIMEZONE')) define('APP_TIMEZONE', 'UTC');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('UPLOAD_PATH', STORAGE_PATH . '/uploads');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Load core classes
require_once APP_PATH . '/core/Database.php';
require_once APP_PATH . '/core/Model.php';
require_once APP_PATH . '/helpers/functions.php';

echo "Test environment initialized.\n";
echo "Using database: " . DB_NAME . "\n";
echo "Note: Tests can run without database if DB doesn't exist.\n\n";
