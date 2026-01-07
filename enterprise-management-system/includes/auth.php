<?php
require_once 'database.php';
require_once 'security.php';

session_start();

class Auth {
    
    public static function login($username, $password) {
        // Check login attempts
        if (!Security::checkLoginAttempts($username)) {
            return ['success' => false, 'message' => 'Too many login attempts. Please try again later.'];
        }
        
        $db = Database::getInstance()->getConnection();
        
        // Use prepared statement to prevent SQL injection
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([Security::sanitizeInput($username)]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login time
            $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->execute([$user['id']]);
            
            // Record successful login attempt
            Security::recordLoginAttempt($username, true);
            
            return ['success' => true, 'role' => $user['role']];
        } else {
            // Record failed login attempt
            Security::recordLoginAttempt($username, false);
            return ['success' => false, 'message' => 'Invalid username or password.'];
        }
    }
    
    public static function logout() {
        // Unset all session variables
        $_SESSION = [];
        
        // Destroy the session
        session_destroy();
        
        // Redirect to login page
        header('Location: login.php');
        exit();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    public static function checkSession() {
        if (!self::isLoggedIn()) {
            header('Location: login.php');
            exit();
        }
        
        // Check session timeout
        if (isset($_SESSION['login_time']) && (time() - $_SESSION['login_time']) > SESSION_TIMEOUT) {
            self::logout();
        }
        
        // Update session time
        $_SESSION['login_time'] = time();
    }
    
    public static function hasRole($allowed_roles) {
        if (!self::isLoggedIn()) {
            return false;
        }
        
        if (is_array($allowed_roles)) {
            return in_array($_SESSION['role'], $allowed_roles);
        }
        
        return $_SESSION['role'] === $allowed_roles;
    }
    
    public static function redirectToDashboard() {
        if (self::isLoggedIn()) {
            switch ($_SESSION['role']) {
                case 'admin':
                    header('Location: modules/admin/dashboard.php');
                    break;
                case 'manager':
                    header('Location: modules/manager/dashboard.php');
                    break;
                case 'employee':
                    header('Location: modules/employee/dashboard.php');
                    break;
                default:
                    header('Location: dashboard.php');
            }
            exit();
        }
    }
    
    public static function changePassword($user_id, $current_password, $new_password) {
        $db = Database::getInstance()->getConnection();
        
        // Get current password hash
        $stmt = $db->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }
        
        // Update password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $updateStmt->execute([$new_password_hash, $user_id]);
        
        return ['success' => true, 'message' => 'Password changed successfully.'];
    }
}
?>