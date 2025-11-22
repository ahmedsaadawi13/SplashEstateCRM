<?php
// FILE: /public/index.php

/**
 * SplashEstate CRM - Application Entry Point
 * All requests are routed through this file
 */

// Start session
session_start();

// Load configuration
require_once '../config/config.php';

// Load core classes
require_once '../app/core/Database.php';
require_once '../app/core/Controller.php';
require_once '../app/core/Model.php';
require_once '../app/core/View.php';
require_once '../app/core/Router.php';

// Load helper functions
require_once '../app/helpers/functions.php';

// Initialize router
$router = new Router();
