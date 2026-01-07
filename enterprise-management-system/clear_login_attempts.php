<?php
session_start();

echo "<h2>🔓 Clear Login Attempts</h2>";

// Clear the login attempts from session
if (isset($_SESSION['login_attempts'])) {
    $count = count($_SESSION['login_attempts']);
    unset($_SESSION['login_attempts']);
    
    echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px;'>";
    echo "✅ Cleared {$count} login attempts from session!<br><br>";
    echo "You can now try logging in again.";
    echo "</div>";
} else {
    echo "<div style='background: #fff3cd; padding: 20px; border-radius: 10px;'>";
    echo "No login attempts found in session.<br>";
    echo "The issue might be elsewhere.";
    echo "</div>";
}

echo "<br><a href='../login.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Go to Login Page</a>";
?>