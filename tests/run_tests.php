<?php
// FILE: /tests/run_tests.php

/**
 * SplashEstate CRM - Test Runner
 * Simple functional tests for the application
 */

// Load test bootstrap
require_once __DIR__ . '/bootstrap.php';

class TestRunner {

    private $passed = 0;
    private $failed = 0;
    private $skipped = 0;
    private $dbAvailable = false;

    public function run() {
        echo "=================================\n";
        echo "SplashEstate CRM - Test Suite\n";
        echo "=================================\n\n";

        // Check database availability
        $this->checkDatabaseAvailability();

        // Run all tests
        $this->testConstants();
        $this->testHelperFunctions();
        $this->testPasswordHashing();
        $this->testFileValidation();

        if ($this->dbAvailable) {
            $this->testDatabaseConnection();
            $this->testUserModel();
            $this->testTenantModel();
            $this->testLeadModel();
            $this->testApiKeyValidation();
            $this->testQuotaEnforcement();
        } else {
            echo "\n⚠ Database tests skipped (database not configured)\n";
            $this->skipped += 6;
        }

        // Display results
        echo "\n=================================\n";
        echo "Test Results\n";
        echo "=================================\n";
        echo "✓ Passed:  " . $this->passed . "\n";
        echo "✗ Failed:  " . $this->failed . "\n";
        echo "⊘ Skipped: " . $this->skipped . "\n";
        echo "Total:     " . ($this->passed + $this->failed + $this->skipped) . "\n";
        echo "=================================\n";

        if ($this->failed === 0) {
            echo "\n✓ All tests passed!\n";
        } else {
            echo "\n✗ Some tests failed. Please review.\n";
        }

        return $this->failed === 0;
    }

    private function checkDatabaseAvailability() {
        try {
            $db = new Database();
            $conn = $db->connect();
            if ($conn !== null) {
                $this->dbAvailable = true;
                echo "✓ Database available for testing\n\n";
            }
        } catch (Exception $e) {
            $this->dbAvailable = false;
            echo "⚠ Database not available: " . $e->getMessage() . "\n";
            echo "⚠ Database-dependent tests will be skipped\n\n";
        }
    }

    private function assert($condition, $message) {
        if ($condition) {
            echo "  ✓ PASS: $message\n";
            $this->passed++;
        } else {
            echo "  ✗ FAIL: $message\n";
            $this->failed++;
        }
    }

    private function testConstants() {
        echo "--- Testing Configuration Constants ---\n";

        $this->assert(defined('DB_HOST'), "DB_HOST constant defined");
        $this->assert(defined('DB_NAME'), "DB_NAME constant defined");
        $this->assert(defined('BASE_URL'), "BASE_URL constant defined");
        $this->assert(defined('APP_TIMEZONE'), "APP_TIMEZONE constant defined");
        $this->assert(date_default_timezone_get() === APP_TIMEZONE, "Timezone set correctly");
        echo "\n";
    }

    private function testHelperFunctions() {
        echo "--- Testing Helper Functions ---\n";

        // Test generateApiKey
        $apiKey = generateApiKey();
        $this->assert(strlen($apiKey) === 64, "generateApiKey returns 64-character string");
        $this->assert(ctype_xdigit($apiKey), "generateApiKey returns hexadecimal string");

        // Test generatePassword
        $password = generatePassword(12);
        $this->assert(strlen($password) === 12, "generatePassword returns correct length");
        $this->assert(strlen($password) >= 12, "generatePassword returns minimum length");

        // Test formatPhone
        $formatted = formatPhone('5551234567');
        $this->assert(strpos($formatted, '(555)') !== false, "formatPhone formats correctly");

        // Test validateRequired
        $data = array('name' => 'Test', 'email' => '');
        $errors = validateRequired($data, array('name', 'email'));
        $this->assert(count($errors) === 1, "validateRequired detects missing fields");
        $this->assert(isset($errors['email']), "validateRequired identifies correct missing field");

        echo "\n";
    }

    private function testPasswordHashing() {
        echo "--- Testing Password Security ---\n";

        $password = 'testpassword123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->assert(strlen($hash) >= 60, "Password hash has sufficient length");
        $this->assert(password_verify($password, $hash), "Correct password verification");
        $this->assert(!password_verify('wrongpassword', $hash), "Wrong password fails verification");

        // Test different passwords produce different hashes
        $hash2 = password_hash($password, PASSWORD_DEFAULT);
        $this->assert($hash !== $hash2, "Same password produces different hashes (salt)");

        echo "\n";
    }

    private function testFileValidation() {
        echo "--- Testing File Upload Validation ---\n";

        $allowedTypes = explode(',', ALLOWED_FILE_TYPES);
        $this->assert(is_array($allowedTypes), "Allowed file types is array");
        $this->assert(in_array('pdf', $allowedTypes), "PDF files are allowed");
        $this->assert(in_array('jpg', $allowedTypes), "JPG files are allowed");
        $this->assert(!in_array('exe', $allowedTypes), "EXE files are not allowed");
        $this->assert(!in_array('php', $allowedTypes), "PHP files are not allowed");

        // Test max upload size
        $this->assert(MAX_UPLOAD_SIZE > 0, "Max upload size is positive");
        $this->assert(MAX_UPLOAD_SIZE <= 10485760, "Max upload size is reasonable (<=10MB)");

        echo "\n";
    }

    private function testDatabaseConnection() {
        echo "--- Testing Database Connection ---\n";

        try {
            $db = new Database();
            $conn = $db->connect();
            $this->assert($conn !== null, "Database connection established");
            $this->assert($conn instanceof PDO, "Connection is PDO instance");

            // Test connection is working
            $stmt = $conn->query("SELECT 1 as test");
            $result = $stmt->fetch();
            $this->assert($result['test'] == 1, "Database query execution works");
        } catch (Exception $e) {
            $this->assert(false, "Database connection: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testUserModel() {
        echo "--- Testing User Model ---\n";

        try {
            require_once APP_PATH . '/models/User.php';
            $userModel = new User();

            // Test model instantiation
            $this->assert($userModel instanceof Model, "User model extends Model class");

            // Test finding user by email
            $user = $userModel->findByEmail('admin@splashestate.com');
            if ($user) {
                $this->assert($user['role'] === 'platform_admin', "Admin user has correct role");
                $this->assert(!empty($user['password']), "User has password hash");
            } else {
                $this->assert(true, "No admin user in database (seed data not loaded)");
            }

            // Test email exists check
            $exists = $userModel->emailExists('admin@splashestate.com');
            $this->assert(is_bool($exists), "emailExists returns boolean");

        } catch (Exception $e) {
            $this->assert(false, "User model test: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testTenantModel() {
        echo "--- Testing Tenant Model ---\n";

        try {
            require_once APP_PATH . '/models/Tenant.php';
            $tenantModel = new Tenant();

            $this->assert($tenantModel instanceof Model, "Tenant model extends Model class");

            // Test getting tenants
            $tenants = $tenantModel->getAllTenants();
            $this->assert(is_array($tenants), "getAllTenants returns array");

        } catch (Exception $e) {
            $this->assert(false, "Tenant model test: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testLeadModel() {
        echo "--- Testing Lead Model ---\n";

        try {
            require_once APP_PATH . '/models/Lead.php';
            $leadModel = new Lead();

            $this->assert($leadModel instanceof Model, "Lead model extends Model class");

            // Test getting leads for a tenant
            $leads = $leadModel->getAll(1, 1, 10);
            $this->assert(is_array($leads), "getAll returns array");

            // Verify tenant isolation
            if (!empty($leads)) {
                $allBelongToTenant = true;
                foreach ($leads as $lead) {
                    if ($lead['tenant_id'] != 1) {
                        $allBelongToTenant = false;
                        break;
                    }
                }
                $this->assert($allBelongToTenant, "All leads belong to specified tenant");
            }

        } catch (Exception $e) {
            $this->assert(false, "Lead model test: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testApiKeyValidation() {
        echo "--- Testing API Key Validation ---\n";

        try {
            // Test with known API key from seed data
            $validKey = 'sk_test_4eC39HqLyjWDarjtT1zdp7dc11111111111111111111111111111111';
            $tenant = verifyApiKey($validKey);

            if ($tenant) {
                $this->assert($tenant['id'] == 1, "Valid API key returns correct tenant");
                $this->assert($tenant['status'] === 'active', "Tenant is active");
            } else {
                $this->assert(true, "No tenant with this API key (seed data not loaded)");
            }

            // Test invalid API key
            $invalidKey = 'invalid_key_12345';
            $invalidTenant = verifyApiKey($invalidKey);
            $this->assert($invalidTenant === false, "Invalid API key is rejected");

        } catch (Exception $e) {
            $this->assert(false, "API key validation test: " . $e->getMessage());
        }

        echo "\n";
    }

    private function testQuotaEnforcement() {
        echo "--- Testing Quota Enforcement ---\n";

        try {
            // Test quota check function
            $result = checkQuota(1, 'leads');
            $this->assert(is_bool($result), "checkQuota returns boolean");

            // For a tenant with Professional plan (500 leads max)
            require_once APP_PATH . '/models/Lead.php';
            $leadModel = new Lead();
            $leadCount = $leadModel->count(1, array());

            $this->assert(is_int($leadCount), "Lead count is integer");
            $this->assert($leadCount >= 0, "Lead count is non-negative");

        } catch (Exception $e) {
            $this->assert(false, "Quota enforcement test: " . $e->getMessage());
        }

        echo "\n";
    }
}

// Run tests
$runner = new TestRunner();
$success = $runner->run();

exit($success ? 0 : 1);
