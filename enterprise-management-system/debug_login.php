<?php
session_start();
require_once 'includes/database.php';

echo "<h2>Login Debug Information</h2>";

// Test database connection
try {
    $db = Database::getInstance()->getConnection();
    echo "✅ Database connection successful<br>";
    
    // Check if users table exists
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Users table exists<br>";
    } else {
        echo "❌ Users table NOT found<br>";
    }
    
    // Check demo users
    echo "<h3>Checking Demo Users:</h3>";
    $users = ['admin', 'manager', 'employee'];
    
    foreach ($users as $username) {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "✅ User '{$username}' found (ID: {$user['id']}, Role: {$user['role']})<br>";
            
            // Test password verification
            $test_passwords = ['Admin@123', 'Manager@123', 'Employee@123', 'password'];
            $found = false;
            
            foreach ($test_passwords as $test_pw) {
                if (password_verify($test_pw, $user['password'])) {
                    echo "&nbsp;&nbsp;✅ Password works: '<strong>{$test_pw}</strong>'<br>";
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                echo "&nbsp;&nbsp;❌ None of the test passwords work<br>";
                echo "&nbsp;&nbsp;Current password hash: " . substr($user['password'], 0, 30) . "...<br>";
                
                // Generate new password hash
                $new_hash = password_hash('password', PASSWORD_DEFAULT);
                echo "&nbsp;&nbsp;To reset: UPDATE users SET password = '{$new_hash}' WHERE id = {$user['id']};<br>";
            }
        } else {
            echo "❌ User '{$username}' NOT found in database<br>";
        }
        echo "<br>";
    }
    
    // Show all users
    echo "<h3>All Users in Database:</h3>";
    $stmt = $db->query("SELECT id, username, role, email FROM users");
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Email</th></tr>";
    while($row = $stmt->fetch()) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>{$row['username']}</td>";
        echo "<td>{$row['role']}</td>";
        echo "<td>{$row['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "<br>";
}
?>