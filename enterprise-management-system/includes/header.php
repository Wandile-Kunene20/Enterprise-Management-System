<?php
require_once 'auth.php';
require_once 'security.php';

// Check if user is logged in
Auth::checkSession();

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'Enterprise Management System'; ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/glass.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php if (strpos($_SERVER['REQUEST_URI'], 'modules/employee') !== false): ?>
        <link rel="stylesheet" href="<?php echo BASE_URL; ?>css/employee.css">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar glass-navbar">
        <div class="navbar-container">
            <a href="<?php echo BASE_URL; ?>dashboard.php" class="navbar-brand">
                <i class="fas fa-building" style="color: #fe7f2d;"></i> Enterprise<span>Pro</span>
            </a>
            
            <ul class="navbar-nav">
                <li><a href="<?php echo BASE_URL; ?>dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/admin/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'admin') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-cogs"></i> Admin Panel
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/admin/users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i> Users
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/admin/reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a></li>
                <?php elseif ($user_role === 'manager'): ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/manager/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'manager') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Manager Panel
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/manager/projects.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'active' : ''; ?>">
                        <i class="fas fa-project-diagram"></i> Projects
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/manager/teams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'teams.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-friends"></i> Teams
                    </a></li>
                <?php else: ?>
                    <li><a href="<?php echo BASE_URL; ?>modules/employee/dashboard.php" class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'employee') !== false ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i> Employee Panel
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/employee/tasks.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'tasks.php' ? 'active' : ''; ?>">
                        <i class="fas fa-tasks"></i> Tasks
                    </a></li>
                    <li><a href="<?php echo BASE_URL; ?>modules/employee/profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-circle"></i> Profile
                    </a></li>
                <?php endif; ?>
            </ul>
            
            <div class="user-menu">
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                    </div>
                    <div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($full_name); ?></div>
                        <div style="font-size: 0.875rem; color: #666;"><?php echo ucfirst($user_role); ?></div>
                    </div>
                </div>
                <form method="POST" action="<?php echo BASE_URL; ?>logout.php" style="display: inline; margin: 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo Security::generateCSRFToken(); ?>">
                    <button type="submit" class="btn btn-outline" style="padding: 8px 16px; border: none; background: transparent; cursor: pointer;">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                </form>
            </div>
        </div>
    </nav>

    <!-- Main Content Container -->
    <main class="dashboard">
        <div class="container">