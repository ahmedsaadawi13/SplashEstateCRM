<?php
// FILE: /app/controllers/AuthController.php

/**
 * SplashEstate CRM - Authentication Controller
 * Handles user login, registration, and logout
 */

class AuthController extends Controller {

    private $userModel;

    /**
     * Constructor
     */
    public function __construct() {
        $this->userModel = $this->model('User');
    }

    /**
     * Display login form
     */
    public function login() {
        // If already logged in, redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('dashboard/index');
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processLogin();
            return;
        }

        // Display login form
        $data = array(
            'title' => 'Login',
            'email' => '',
            'errors' => array()
        );

        $this->view('auth/login', $data);
    }

    /**
     * Process login form
     */
    private function processLogin() {
        // Sanitize input
        $email = $this->sanitize($_POST['email']);
        $password = $_POST['password']; // Don't sanitize password

        // Validate
        $errors = array();

        if (empty($email)) {
            $errors['email'] = 'Email is required';
        } elseif (!$this->validateEmail($email)) {
            $errors['email'] = 'Invalid email format';
        }

        if (empty($password)) {
            $errors['password'] = 'Password is required';
        }

        // If validation fails, show form with errors
        if (!empty($errors)) {
            $data = array(
                'title' => 'Login',
                'email' => $email,
                'errors' => $errors
            );
            $this->view('auth/login', $data);
            return;
        }

        // Authenticate user
        $user = $this->userModel->authenticate($email, $password);

        if ($user) {
            // Check if user is active
            if ($user['status'] !== 'active') {
                $errors['general'] = 'Your account is inactive. Please contact support.';
                $data = array(
                    'title' => 'Login',
                    'email' => $email,
                    'errors' => $errors
                );
                $this->view('auth/login', $data);
                return;
            }

            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['tenant_id'] = $user['tenant_id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['user_role'] = $user['role'];

            // Redirect to dashboard
            $this->redirect('dashboard/index');
        } else {
            $errors['general'] = 'Invalid email or password';
            $data = array(
                'title' => 'Login',
                'email' => $email,
                'errors' => $errors
            );
            $this->view('auth/login', $data);
        }
    }

    /**
     * Logout user
     */
    public function logout() {
        // Destroy session
        session_unset();
        session_destroy();

        // Redirect to login
        $this->redirect('auth/login');
    }

    /**
     * Display registration form (for tenant signup)
     */
    public function register() {
        // If already logged in, redirect to dashboard
        if ($this->isLoggedIn()) {
            $this->redirect('dashboard/index');
        }

        // Handle form submission
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->processRegistration();
            return;
        }

        // Display registration form
        $data = array(
            'title' => 'Register',
            'errors' => array(),
            'formData' => array()
        );

        $this->view('auth/register', $data);
    }

    /**
     * Process registration form
     */
    private function processRegistration() {
        // Sanitize input
        $formData = array(
            'company_name' => $this->sanitize($_POST['company_name']),
            'email' => $this->sanitize($_POST['email']),
            'first_name' => $this->sanitize($_POST['first_name']),
            'last_name' => $this->sanitize($_POST['last_name']),
            'phone' => $this->sanitize($_POST['phone']),
            'password' => $_POST['password'],
            'confirm_password' => $_POST['confirm_password']
        );

        // Validate
        $errors = validateRequired($formData, array('company_name', 'email', 'first_name', 'last_name', 'password', 'confirm_password'));

        // Email validation
        if (empty($errors['email']) && !$this->validateEmail($formData['email'])) {
            $errors['email'] = 'Invalid email format';
        }

        // Check if email already exists
        if (empty($errors['email']) && $this->userModel->emailExists($formData['email'])) {
            $errors['email'] = 'Email already registered';
        }

        // Password validation
        if (empty($errors['password']) && strlen($formData['password']) < 6) {
            $errors['password'] = 'Password must be at least 6 characters';
        }

        if ($formData['password'] !== $formData['confirm_password']) {
            $errors['confirm_password'] = 'Passwords do not match';
        }

        // If validation fails, show form with errors
        if (!empty($errors)) {
            $data = array(
                'title' => 'Register',
                'errors' => $errors,
                'formData' => $formData
            );
            $this->view('auth/register', $data);
            return;
        }

        // Create tenant
        $tenantModel = $this->model('Tenant');
        $tenantData = array(
            'name' => $formData['company_name'],
            'slug' => $this->generateSlug($formData['company_name']),
            'email' => $formData['email'],
            'phone' => $formData['phone'],
            'api_key' => generateApiKey(),
            'status' => 'active'
        );

        $tenantId = $tenantModel->create($tenantData);

        if ($tenantId) {
            // Create admin user for tenant
            $userData = array(
                'tenant_id' => $tenantId,
                'email' => $formData['email'],
                'password' => $formData['password'],
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'phone' => $formData['phone'],
                'role' => 'tenant_admin',
                'status' => 'active'
            );

            $userId = $this->userModel->createUser($userData);

            if ($userId) {
                // Assign free trial plan
                $subscriptionModel = $this->model('Subscription');
                $subscriptionModel->createTrialSubscription($tenantId);

                // Log in the user
                $_SESSION['user_id'] = $userId;
                $_SESSION['tenant_id'] = $tenantId;
                $_SESSION['user_email'] = $formData['email'];
                $_SESSION['user_name'] = $formData['first_name'] . ' ' . $formData['last_name'];
                $_SESSION['user_role'] = 'tenant_admin';

                $this->setFlash('success', 'Registration successful! Welcome to SplashEstate CRM.');
                $this->redirect('dashboard/index');
            }
        }

        // If we get here, something went wrong
        $errors['general'] = 'Registration failed. Please try again.';
        $data = array(
            'title' => 'Register',
            'errors' => $errors,
            'formData' => $formData
        );
        $this->view('auth/register', $data);
    }

    /**
     * Generate slug from string
     * @param string $string Input string
     * @return string Slug
     */
    private function generateSlug($string) {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $string), '-'));
        // Make unique by appending random number
        $slug .= '-' . substr(md5(uniqid()), 0, 6);
        return $slug;
    }

    /**
     * Forgot password form
     */
    public function forgot() {
        $data = array(
            'title' => 'Forgot Password',
            'message' => ''
        );

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $this->sanitize($_POST['email']);

            if ($this->validateEmail($email)) {
                // TODO: Implement password reset email
                $data['message'] = 'If an account exists with this email, you will receive password reset instructions.';
            } else {
                $data['message'] = 'Please enter a valid email address.';
            }
        }

        $this->view('auth/forgot', $data);
    }
}
