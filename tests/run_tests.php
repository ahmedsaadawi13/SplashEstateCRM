<?php
// FILE: /tests/run_tests.php

/**
 * SplashEstate CRM - Test Runner
 * Simple functional tests for the application
 */

// Load configuration
require_once '../config/config.php';
require_once '../app/core/Database.php';
require_once '../app/core/Model.php';
require_once '../app/helpers/functions.php';

class TestRunner {

    private $passed = 0;
    private $failed = 0;
    private $tests = array();

    public function run() {
        echo "=================================\n";
        echo "SplashEstate CRM - Test Suite\n";
        echo "=================================\n\n";

        // Run all tests
        $this->testDatabaseConnection();
        $this->testUserAuthentication();
        $this->testTenantIsolation();
        $this->testLeadCreation();
        $this->testQuotaEnforcement();
        $this->testApiKeyValidation();
        $this->testFileUpload();
        $this->testPasswordHashing();

        // Display results
        echo "\n=================================\n";
        echo "Test Results\n";
        echo "=================================\n";
        echo "Passed: " . $this->passed . "\n";
        echo "Failed: " . $this->failed . "\n";
        echo "Total:  " . ($this->passed + $this->failed) . "\n";
        echo "=================================\n";

        return $this->failed === 0;
    }

    private function assert($condition, $message) {
        if ($condition) {
            echo "✓ PASS: $message\n";
            $this->passed++;
        } else {
            echo "✗ FAIL: $message\n";
            $this->failed++;
        }
    }

    private function testDatabaseConnection() {
        echo "\n--- Testing Database Connection ---\n";

        try {
            $db = new Database();
            $conn = $db->connect();
            $this->assert($conn !== null, "Database connection established");
            $this->assert($conn instanceof PDO, "Connection is PDO instance");
        } catch (Exception $e) {
            $this->assert(false, "Database connection failed: " . $e->getMessage());
        }
    }

    private function testUserAuthentication() {
        echo "\n--- Testing User Authentication ---\n";

        require_once '../app/models/User.php';
        $userModel = new User();

        // Test finding user by email
        $user = $userModel->findByEmail('admin@splashestate.com');
        $this->assert($user !== false, "Find user by email");
        $this->assert($user['role'] === 'platform_admin', "User has correct role");

        // Test password verification
        $authenticated = $userModel->authenticate('admin@splashestate.com', 'admin123');
        $this->assert($authenticated !== false, "User authentication succeeds with correct password");

        $notAuthenticated = $userModel->authenticate('admin@splashestate.com', 'wrongpassword');
        $this->assert($notAuthenticated === false, "User authentication fails with wrong password");
    }

    private function testTenantIsolation() {
        echo "\n--- Testing Tenant Isolation ---\n";

        require_once '../app/models/Lead.php';
        $leadModel = new Lead();

        // Get leads for tenant 1
        $tenant1Leads = $leadModel->getAll(1, 1, 100);

        // Verify all leads belong to tenant 1
        $allBelongToTenant1 = true;
        foreach ($tenant1Leads as $lead) {
            if ($lead['tenant_id'] != 1) {
                $allBelongToTenant1 = false;
                break;
            }
        }
        $this->assert($allBelongToTenant1, "Tenant 1 only retrieves its own leads");

        // Get leads for tenant 2
        $tenant2Leads = $leadModel->getAll(2, 1, 100);

        // Verify counts are different (assuming seed data)
        $this->assert(count($tenant1Leads) !== count($tenant2Leads), "Different tenants have different lead counts");
    }

    private function testLeadCreation() {
        echo "\n--- Testing Lead Creation ---\n";

        require_once '../app/models/Lead.php';
        $leadModel = new Lead();

        $testLeadData = array(
            'tenant_id' => 1,
            'first_name' => 'Test',
            'last_name' => 'Lead',
            'email' => 'test@example.com',
            'phone' => '5551234567',
            'source' => 'Test',
            'status' => 'new'
        );

        $leadId = $leadModel->createLead($testLeadData);
        $this->assert($leadId !== false, "Lead creation succeeds");
        $this->assert(is_numeric($leadId) && $leadId > 0, "Lead ID is valid");

        // Verify lead was created
        $lead = $leadModel->getById($leadId, 1);
        $this->assert($lead !== false, "Created lead can be retrieved");
        $this->assert($lead['first_name'] === 'Test', "Lead data is correct");

        // Clean up
        $leadModel->delete($leadId, 1);
    }

    private function testQuotaEnforcement() {
        echo "\n--- Testing Quota Enforcement ---\n";

        // Test quota check function
        $withinQuota = checkQuota(1, 'leads');
        $this->assert(is_bool($withinQuota), "Quota check returns boolean");

        // For tenant 1 with Professional plan (500 leads max)
        require_once '../app/models/Lead.php';
        $leadModel = new Lead();
        $leadCount = $leadModel->count(1, array());

        $this->assert($leadCount < 500, "Tenant 1 is within lead quota");
    }

    private function testApiKeyValidation() {
        echo "\n--- Testing API Key Validation ---\n";

        // Test valid API key
        $validKey = 'sk_test_4eC39HqLyjWDarjtT1zdp7dc11111111111111111111111111111111';
        $tenant = verifyApiKey($validKey);
        $this->assert($tenant !== false, "Valid API key is accepted");
        $this->assert($tenant['id'] == 1, "API key returns correct tenant");

        // Test invalid API key
        $invalidKey = 'invalid_key';
        $invalidTenant = verifyApiKey($invalidKey);
        $this->assert($invalidTenant === false, "Invalid API key is rejected");
    }

    private function testFileUpload() {
        echo "\n--- Testing File Upload Validation ---\n";

        // Test generateApiKey function
        $apiKey = generateApiKey();
        $this->assert(strlen($apiKey) === 64, "Generated API key has correct length");
        $this->assert(ctype_xdigit($apiKey), "Generated API key is hexadecimal");

        // Test file validation (simulated)
        $allowedTypes = explode(',', ALLOWED_FILE_TYPES);
        $this->assert(in_array('pdf', $allowedTypes), "PDF files are allowed");
        $this->assert(in_array('jpg', $allowedTypes), "JPG files are allowed");
        $this->assert(!in_array('exe', $allowedTypes), "EXE files are not allowed");
    }

    private function testPasswordHashing() {
        echo "\n--- Testing Password Security ---\n";

        $password = 'testpassword123';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $this->assert(strlen($hash) >= 60, "Password hash has sufficient length");
        $this->assert(password_verify($password, $hash), "Password verification works");
        $this->assert(!password_verify('wrongpassword', $hash), "Wrong password fails verification");
    }
}

// Run tests
$runner = new TestRunner();
$success = $runner->run();

exit($success ? 0 : 1);
