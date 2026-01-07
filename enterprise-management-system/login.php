<?php
require_once 'includes/auth.php';
require_once 'includes/security.php';

// Redirect if already logged in
//if (Auth::isLoggedIn()) {
   // Auth::redirectToDashboard();
//}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = Security::sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    // Validate CSRF token
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = Auth::login($username, $password);
        
        if ($result['success']) {
            Auth::redirectToDashboard();
        } else {
            $error = $result['message'];
        }
    }
}

// Generate CSRF token for the form
$csrf_token = Security::generateCSRFToken();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Enterprise Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/glass.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box glass-card fade-in">
            <div class="login-header">
                <h1><i class="fas fa-building" style="color: #fe7f2d;"></i> EnterprisePro</h1>
                <p>Enterprise Management System</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?php echo Security::escapeOutput($error); ?></span>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <span><?php echo Security::escapeOutput($success); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i> Username
                    </label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-control glass-input" 
                           placeholder="Enter your username" 
                           required
                           autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <div style="position: relative;">
                        <input type="password" 
                               id="password" 
                               name="password" 
                               class="form-control glass-input" 
                               placeholder="Enter your password" 
                               required>
                        <button type="button" 
                                id="togglePassword" 
                                style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: #666; cursor: pointer;">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary glass-button" style="width: 100%;">
                        <i class="fas fa-sign-in-alt"></i> Sign In
                    </button>
                </div>
                
                <div class="form-group text-center">
                    <p style="color: #666; margin-bottom: 0.5rem;">Demo Accounts:</p>
                    <div style="display: flex; justify-content: center; gap: 1rem; flex-wrap: wrap;">
                        <button type="button" class="btn btn-outline" onclick="fillCredentials('admin', 'Admin@123')">
                            <i class="fas fa-user-shield"></i> Admin
                        </button>
                        <button type="button" class="btn btn-outline" onclick="fillCredentials('manager', 'Manager@123')">
                            <i class="fas fa-user-tie"></i> Manager
                        </button>
                        <button type="button" class="btn btn-outline" onclick="fillCredentials('employee', 'Employee@123')">
                            <i class="fas fa-user"></i> Employee
                        </button>
                    </div>
                </div>
                
                <div class="form-group text-center" style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid #eee;">
                    <p style="color: #666; font-size: 0.9rem;">
                        <i class="fas fa-info-circle"></i> 
                        All demo accounts use the same password pattern: Role@123
                    </p>
                </div>
            </form>
        </div>
    </div>
    
    <script src="js/main.js"></script>
    <script>
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
        }
        
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
        });
    </script>
</body>
</html>     