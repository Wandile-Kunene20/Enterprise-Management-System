<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

echo "<h2>🔍 Comprehensive Login Diagnostic</h2>";

// Test 1: Check if demo users exist with correct passwords
echo "<h3>📋 Test 1: User Accounts & Passwords</h3>";

try {
    $db = Database::getInstance()->getConnection();
    
    $users = ['admin', 'manager', 'employee'];
    $found_valid_user = false;
    
    foreach ($users as $username) {
        $stmt = $db->prepare("SELECT id, username, password, role, full_name, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<div style='margin: 10px 0; padding: 10px; border: 1px solid #ddd; border-radius: 5px;'>";
            echo "<strong>User: {$user['username']} (ID: {$user['id']})</strong><br>";
            echo "Role: {$user['role']}, Status: {$user['status']}<br>";
            
            // Test passwords
            $test_passwords = [
                'Admin@123', 'Manager@123', 'Employee@123', 
                'password', 'Password123', 'Pass@123',
                'admin', 'manager', 'employee'
            ];
            
            $password_found = false;
            foreach ($test_passwords as $test_pw) {
                if (password_verify($test_pw, $user['password'])) {
                    echo "✅ <strong>Working password: '{$test_pw}'</strong><br>";
                    $password_found = true;
                    $found_valid_user = true;
                    
                    // Test Auth::login function
                    echo "<br>Testing Auth::login() with '{$username}' / '{$test_pw}': ";
                    $result = Auth::login($username, $test_pw);
                    if ($result['success']) {
                        echo "✅ SUCCESS<br>";
                    } else {
                        echo "❌ FAILED: {$result['message']}<br>";
                    }
                    break;
                }
            }
            
            if (!$password_found) {
                echo "❌ No matching password found<br>";
                echo "Current hash: " . substr($user['password'], 0, 30) . "...<br>";
                
                // Fix suggestion
                $new_hash = password_hash('password', PASSWORD_DEFAULT);
                echo "<div style='background: #ffebee; padding: 10px; margin: 5px 0;'>";
                echo "<strong>Fix:</strong> Run this SQL:<br>";
                echo "<code>UPDATE users SET password = '{$new_hash}' WHERE id = {$user['id']};</code>";
                echo "</div>";
            }
            
            echo "</div>";
        } else {
            echo "<div style='color: red;'>❌ User '{$username}' not found in database</div>";
        }
    }
    
    if ($found_valid_user) {
        echo "<div style='background: #e8f5e9; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "✅ At least one user has a working password!";
        echo "</div>";
    } else {
        echo "<div style='background: #ffebee; padding: 15px; margin: 15px 0; border-radius: 5px;'>";
        echo "❌ NO users have working passwords! Need to reset passwords.";
        echo "</div>";
    }
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage();
}

// Test 2: Check session configuration
echo "<h3>📋 Test 2: Session Configuration</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session status: " . session_status() . " (2 = active)<br>";
echo "Session save path: " . session_save_path() . "<br>";

// Test 3: Check Auth class methods
echo "<h3>📋 Test 3: Auth Class Functions</h3>";

if (method_exists('Auth', 'login')) {
    echo "✅ Auth::login() method exists<br>";
} else {
    echo "❌ Auth::login() method NOT found!<br>";
}

if (method_exists('Auth', 'isLoggedIn')) {
    echo "✅ Auth::isLoggedIn() method exists<br>";
} else {
    echo "❌ Auth::isLoggedIn() method NOT found!<br>";
}

// Test 4: Check login form
echo "<h3>📋 Test 4: Login Form Analysis</h3>";
echo "Login form action: <code>login.php</code><br>";

// Read login.php to check for issues
if (file_exists('login.php')) {
    echo "✅ login.php file exists<br>";
    
    $login_content = file_get_contents('login.php');
    if (strpos($login_content, 'Auth::login') !== false) {
        echo "✅ login.php calls Auth::login()<br>";
    } else {
        echo "❌ login.php does NOT call Auth::login()<br>";
    }
    
    if (strpos($login_content, 'csrf_token') !== false) {
        echo "✅ login.php includes CSRF protection<br>";
    } else {
        echo "⚠️ login.php may not have CSRF protection<br>";
    }
} else {
    echo "❌ login.php file NOT found!<br>";
}

// Test 5: Quick fix button
echo "<h3>🛠️ Quick Fix Options</h3>";
echo "<div style='display: flex; gap: 10px;'>";
echo "<a href='reset_all_passwords.php' style='padding: 10px 15px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Reset All Passwords to 'password'</a>";
echo "<a href='emergency_login.php' style='padding: 10px 15px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Use Emergency Login (No Password)</a>";
echo "<a href='create_test_users.php' style='padding: 10px 15px; background: #FF9800; color: white; text-decoration: none; border-radius: 5px;'>Create New Test Users</a>";
echo "</div>";
?>