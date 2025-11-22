<?php
// FILE: /app/core/Router.php

/**
 * SplashEstate CRM - Router Class
 * Handles URL routing and dispatches to appropriate controllers
 */

class Router {
    private $controller = 'DashboardController';
    private $method = 'index';
    private $params = array();

    /**
     * Constructor - Parse URL and route to controller
     */
    public function __construct() {
        $url = $this->parseUrl();

        // Check for API routes first
        if (isset($url[0]) && $url[0] === 'api') {
            $this->routeApi($url);
            return;
        }

        // Check if controller file exists
        if (isset($url[0]) && file_exists('../app/controllers/' . ucfirst($url[0]) . 'Controller.php')) {
            $this->controller = ucfirst($url[0]) . 'Controller';
            unset($url[0]);
        }

        // Require the controller
        require_once '../app/controllers/' . $this->controller . '.php';

        // Instantiate controller
        $this->controller = new $this->controller;

        // Check for method
        if (isset($url[1])) {
            if (method_exists($this->controller, $url[1])) {
                $this->method = $url[1];
                unset($url[1]);
            }
        }

        // Get params
        $this->params = $url ? array_values($url) : array();

        // Call method with params
        call_user_func_array(array($this->controller, $this->method), $this->params);
    }

    /**
     * Route API requests
     */
    private function routeApi($url) {
        // API routing
        if (isset($url[1]) && file_exists('../app/controllers/api/' . ucfirst($url[1]) . 'ApiController.php')) {
            require_once '../app/controllers/api/' . ucfirst($url[1]) . 'ApiController.php';
            $controllerName = ucfirst($url[1]) . 'ApiController';
            $controller = new $controllerName;

            $method = isset($url[2]) ? $url[2] : 'index';

            if (method_exists($controller, $method)) {
                $params = array_slice($url, 3);
                call_user_func_array(array($controller, $method), $params);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Endpoint not found']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'API resource not found']);
        }
        exit;
    }

    /**
     * Parse URL from request
     * @return array URL components
     */
    private function parseUrl() {
        if (isset($_GET['url'])) {
            return explode('/', filter_var(rtrim($_GET['url'], '/'), FILTER_SANITIZE_URL));
        }
        return array();
    }
}
