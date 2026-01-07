<?php
// Force reset ALL passwords to 'password'
$new_password = 'password';
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

echo "<h2>🔄 Reset ALL User Passwords</h2>";

try {
    $pdo = new PDO('mysql:host=localhost;dbname=enterprise_system', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Update ALL users
    $stmt = $pdo->prepare("UPDATE users SET password = ?");
    $stmt->execute([$hashed_password]);
    
    $affected = $stmt->rowCount();
    
    echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "✅ Successfully updated {$affected} user(s)!<br><br>";
    echo "<strong>🔑 New Login Credentials:</strong><br>";
    echo "• Username: <strong>admin</strong> / Password: <strong>{$new_password}</strong><br>";
    echo "• Username: <strong>manager</strong> / Password: <strong>{$new_password}</strong><br>";
    echo "• Username: <strong>employee</strong> / Password: <strong>{$new_password}</strong><br>";
    echo "</div>";
    
    echo "<a href='../login.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
    
} catch (PDOException $e) {
    echo "<div style='background: #ffebee; padding: 20px; border-radius: 10px;'>";
    echo "❌ Error: " . $e->getMessage();
    echo "</div>";
}
?>