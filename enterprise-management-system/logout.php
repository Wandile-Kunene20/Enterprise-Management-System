
<?php
require_once 'includes/auth.php';

// Only allow logout via POST to help prevent CSRF via simple GET links.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$token = $_POST['csrf_token'] ?? '';
	if (!Security::validateCSRFToken($token)) {
		// Invalid CSRF token — redirect to dashboard or login with an error flag
		header('Location: login.php?error=invalid_csrf');
		exit();
	}

	Auth::logout();
} else {
	// If accessed via GET, redirect to dashboard (do not perform logout on GET)
	header('Location: dashboard.php');
	exit();
}
?>