<?php
// FILE: /app/core/Controller.php

/**
 * SplashEstate CRM - Base Controller Class
 * All controllers extend this class
 */

class Controller {

    /**
     * Load a model
     * @param string $model Model name
     * @return object Model instance
     */
    protected function model($model) {
        require_once '../app/models/' . $model . '.php';
        return new $model();
    }

    /**
     * Load a view
     * @param string $view View name
     * @param array $data Data to pass to view
     */
    protected function view($view, $data = array()) {
        // Extract data array to variables
        extract($data);

        // Check if view file exists
        if (file_exists('../app/views/' . $view . '.php')) {
            require_once '../app/views/' . $view . '.php';
        } else {
            die('View does not exist: ' . $view);
        }
    }

    /**
     * Redirect to another page
     * @param string $url URL to redirect to
     */
    protected function redirect($url) {
        header('Location: ' . BASE_URL . '/' . $url);
        exit;
    }

    /**
     * Check if user is logged in
     * @return bool
     */
    protected function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    /**
     * Require login - redirect to login if not authenticated
     */
    protected function requireLogin() {
        if (!$this->isLoggedIn()) {
            $this->redirect('auth/login');
        }
    }

    /**
     * Get current user ID
     * @return int|null
     */
    protected function getUserId() {
        return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    }

    /**
     * Get current tenant ID
     * @return int|null
     */
    protected function getTenantId() {
        return isset($_SESSION['tenant_id']) ? $_SESSION['tenant_id'] : null;
    }

    /**
     * Get current user role
     * @return string|null
     */
    protected function getUserRole() {
        return isset($_SESSION['user_role']) ? $_SESSION['user_role'] : null;
    }

    /**
     * Check if user has specific role
     * @param string|array $roles Role(s) to check
     * @return bool
     */
    protected function hasRole($roles) {
        if (!is_array($roles)) {
            $roles = array($roles);
        }
        return in_array($this->getUserRole(), $roles);
    }

    /**
     * Require specific role - redirect if not authorized
     * @param string|array $roles Required role(s)
     */
    protected function requireRole($roles) {
        $this->requireLogin();
        if (!$this->hasRole($roles)) {
            $this->redirect('dashboard/index');
        }
    }

    /**
     * Set flash message
     * @param string $type Type of message (success, error, warning, info)
     * @param string $message Message text
     */
    protected function setFlash($type, $message) {
        $_SESSION['flash'] = array(
            'type' => $type,
            'message' => $message
        );
    }

    /**
     * Get and clear flash message
     * @return array|null Flash message data
     */
    protected function getFlash() {
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }

    /**
     * Generate CSRF token
     * @return string CSRF token
     */
    protected function generateCsrfToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     * @param string $token Token to verify
     * @return bool
     */
    protected function verifyCsrfToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Sanitize input data
     * @param mixed $data Data to sanitize
     * @return mixed Sanitized data
     */
    protected function sanitize($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->sanitize($value);
            }
        } else {
            $data = htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
        }
        return $data;
    }

    /**
     * Validate email
     * @param string $email Email to validate
     * @return bool
     */
    protected function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Return JSON response
     * @param array $data Data to encode
     * @param int $statusCode HTTP status code
     */
    protected function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
