<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'enterprise_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application configuration
define('APP_NAME', 'Enterprise Management System');
define('APP_VERSION', '1.0.0');
define('BASE_URL', 'http://localhost/enterprise-management-system/');

// Security configuration
define('SESSION_TIMEOUT', 1800); // 30 minutes in seconds
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes in seconds

// File upload configuration
define('MAX_FILE_SIZE', 5242880); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/png', 'image/gif']);
define('UPLOAD_PATH', 'uploads/');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>