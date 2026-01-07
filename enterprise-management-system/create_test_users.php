<?php
// Create fresh test users
echo "<h2>👥 Create Fresh Test Users</h2>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=enterprise_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Delete existing demo users
    $pdo->exec("DELETE FROM users WHERE username IN ('admin', 'manager', 'employee')");
    
    // Create new users with password 'password'
    $password_hash = password_hash('password', PASSWORD_DEFAULT);
    
    $users = [
        ['admin', $password_hash, 'admin@company.com', 'System Administrator', 'admin', 'IT', 'System Admin'],
        ['manager', $password_hash, 'manager@company.com', 'Project Manager', 'manager', 'Operations', 'Manager'],
        ['employee', $password_hash, 'employee@company.com', 'John Doe', 'employee', 'Sales', 'Sales Executive']
    ];
    
    $success_count = 0;
    foreach ($users as $user) {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, email, full_name, role, department, position, hire_date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())
        ");
        
        if ($stmt->execute($user)) {
            $success_count++;
        }
    }
    
    echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px;'>";
    echo "✅ Created {$success_count} new test users!<br><br>";
    echo "<strong>Login with:</strong><br>";
    echo "• admin / password<br>";
    echo "• manager / password<br>";
    echo "• employee / password<br>";
    echo "</div>";
    
    echo "<a href='../login.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px;'>Test Login Now</a>";
    
} catch (PDOException $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 10px;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}
?>