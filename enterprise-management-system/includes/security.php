<?php
class Security {
    
    // Prevent SQL Injection
    public static function sanitizeInput($input, $type = 'string') {
        if ($input === null) {
            return null;
        }
        
        switch ($type) {
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'string':
            default:
                // Remove tags and encode special characters
                $input = strip_tags($input);
                $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                return trim($input);
        }
    }
    
    // Prevent XSS attacks
    public static function escapeOutput($data) {
        if (is_array($data)) {
            return array_map([self::class, 'escapeOutput'], $data);
        }
        return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Validate and secure file uploads
    public static function validateFile($file) {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload error: " . $file['error'];
            return [false, $errors];
        }
        
        // Check file size
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = "File size exceeds maximum allowed size.";
        }
        
        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, ALLOWED_TYPES)) {
            $errors[] = "Invalid file type. Allowed types: " . implode(', ', ALLOWED_TYPES);
        }
        
        // Check for malicious content
        if (self::containsMaliciousContent($file['tmp_name'])) {
            $errors[] = "File contains potentially malicious content.";
        }
        
        if (count($errors) > 0) {
            return [false, $errors];
        }
        
        return [true, null];
    }
    
    private static function containsMaliciousContent($filepath) {
        $content = file_get_contents($filepath);
        
        // Check for PHP tags
        if (strpos($content, '<?php') !== false) {
            return true;
        }
        
        // Check for JavaScript
        if (preg_match('/<script\b[^>]*>/i', $content)) {
            return true;
        }
        
        // Check for shell commands
        $dangerous_patterns = [
            '/system\s*\(/i',
            '/exec\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/eval\s*\(/i',
            '/base64_decode\s*\(/i'
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                return true;
            }
        }
        
        return false;
    }
    
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    // Validate CSRF token
    public static function validateCSRFToken($token) {
        if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            return false;
        }
        return true;
    }
    
    // Rate limiting for login attempts
    public static function checkLoginAttempts($username) {
         return true;
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        $current_time = time();
        $attempts = $_SESSION['login_attempts'];
        
        // Remove old attempts
        foreach ($attempts as $key => $attempt) {
            if ($attempt['time'] < $current_time - LOCKOUT_TIME) {
                unset($attempts[$key]);
            }
        }
        
        // Count attempts for this username
        $user_attempts = 0;
        foreach ($attempts as $attempt) {
            if ($attempt['username'] === $username) {
                $user_attempts++;
            }
        }
        
        return $user_attempts < MAX_LOGIN_ATTEMPTS;
    }
    
    public static function recordLoginAttempt($username, $success) {
        if (!isset($_SESSION['login_attempts'])) {
            $_SESSION['login_attempts'] = [];
        }
        
        $_SESSION['login_attempts'][] = [
            'username' => $username,
            'time' => time(),
            'success' => $success
        ];
    }
}
?>