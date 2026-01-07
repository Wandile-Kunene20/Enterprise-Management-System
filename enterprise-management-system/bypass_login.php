<?php
// Bypass all security and login directly
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    
    // Get user from database
    require_once 'includes/database.php';
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Bypass all security checks
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['logged_in'] = true;
        $_SESSION['login_time'] = time();
        
        // Clear login attempts
        unset($_SESSION['login_attempts']);
        
        // Redirect based on role
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
        }
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Bypass Login Lock</title>
    <style>
        body { font-family: Arial; max-width: 400px; margin: 100px auto; padding: 20px; }
        .login-box { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        select, button { width: 100%; padding: 12px; margin: 10px 0; font-size: 16px; }
        button { background: linear-gradient(90deg, #fe7f2d, #233d4d); color: white; border: none; cursor: pointer; }
        button:hover { opacity: 0.9; }
        h2 { color: #233d4d; text-align: center; }
    </style>
</head>
<body>
    <div class="login-box">
        <h2>🔓 Bypass Login Lock</h2>
        <p style="color: #666; text-align: center;">This bypasses rate limiting and password checks</p>
        
        <form method="POST">
            <select name="username" required>
                <option value="">Select User</option>
                <option value="admin">👑 Admin User</option>
                <option value="manager">👔 Manager User</option>
                <option value="employee">👤 Employee User</option>
            </select>
            
            <button type="submit">
                🔓 Login Now (No Password Required)
            </button>
        </form>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <strong>Note:</strong> This will clear all login attempts and log you in directly.
        </div>
    </div>
</body>
</html>