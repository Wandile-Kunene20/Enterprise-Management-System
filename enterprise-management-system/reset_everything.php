<?php
session_start();

echo "<h2>🔄 Reset Everything</h2>";

// 1. Destroy session completely
session_destroy();

// 2. Start fresh session
session_start();

// 3. Clear all session data
$_SESSION = [];

// 4. Clear login attempts specifically
unset($_SESSION['login_attempts']);

echo "<div style='background: #e8f5e9; padding: 20px; border-radius: 10px;'>";
echo "✅ Session completely reset!<br><br>";
echo "All login attempts have been cleared.<br>";
echo "You should now be able to log in normally.";
echo "</div>";

echo "<br><div style='display: flex; gap: 10px;'>";
echo "<a href='../login.php' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>Try Normal Login</a>";
echo "<a href='bypass_login.php' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Use Bypass Login</a>";
echo "</div>";
?>