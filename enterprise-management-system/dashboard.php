<?php
require_once 'includes/auth.php';
require_once 'includes/database.php';
require_once 'includes/security.php';

// Check if user is logged in
Auth::checkSession();

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];
$full_name = $_SESSION['full_name'];

// Get user statistics based on role
$stats = [];

switch ($user_role) {
    case 'admin':
        // Admin statistics
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM projects");
        $stmt->execute();
        $stats['total_projects'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM departments");
        $stmt->execute();
        $stats['total_departments'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE status = 'pending'");
        $stmt->execute();
        $stats['pending_tasks'] = $stmt->fetch()['total'];
        break;
        
    case 'manager':
        // Manager statistics
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE manager_id = ?");
        $stmt->execute([$user_id]);
        $stats['my_projects'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.manager_id = ? AND t.status = 'pending'");
        $stmt->execute([$user_id]);
        $stats['pending_tasks'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(DISTINCT assigned_to) as total FROM tasks t JOIN projects p ON t.project_id = p.id WHERE p.manager_id = ?");
        $stmt->execute([$user_id]);
        $stats['team_members'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE manager_id = ? AND status = 'ongoing'");
        $stmt->execute([$user_id]);
        $stats['ongoing_projects'] = $stmt->fetch()['total'];
        break;
        
    case 'employee':
        // Employee statistics
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ?");
        $stmt->execute([$user_id]);
        $stats['total_tasks'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'pending'");
        $stmt->execute([$user_id]);
        $stats['pending_tasks'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
        $stmt->execute([$user_id]);
        $stats['in_progress_tasks'] = $stmt->fetch()['total'];
        
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'completed'");
        $stmt->execute([$user_id]);
        $stats['completed_tasks'] = $stmt->fetch()['total'];
        break;
}

// Get recent activities based on role
$activities = [];
switch ($user_role) {
    case 'admin':
        $stmt = $db->prepare("
            SELECT 'user' as type, u.username, u.full_name, CONCAT('New user registered: ', u.full_name) as description, u.created_at 
            FROM users u 
            ORDER BY u.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute();
        $activities = $stmt->fetchAll();
        break;
        
    case 'manager':
        $stmt = $db->prepare("
            SELECT 'task' as type, t.title, u.full_name, CONCAT('New task assigned to ', u.full_name) as description, t.created_at 
            FROM tasks t 
            JOIN users u ON t.assigned_to = u.id 
            JOIN projects p ON t.project_id = p.id 
            WHERE p.manager_id = ? 
            ORDER BY t.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $activities = $stmt->fetchAll();
        break;
        
    case 'employee':
        $stmt = $db->prepare("
            SELECT 'task' as type, t.title, u.full_name, 
                   CASE 
                     WHEN t.status = 'completed' THEN CONCAT('Task completed: ', t.title)
                     ELSE CONCAT('Task updated: ', t.title, ' (', t.status, ')')
                   END as description, 
                   COALESCE(t.completed_at, t.created_at) as activity_date
            FROM tasks t 
            JOIN users u ON t.assigned_by = u.id 
            WHERE t.assigned_to = ? 
            ORDER BY activity_date DESC 
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $activities = $stmt->fetchAll();
        break;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Enterprise Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/glass.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar glass-navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-brand">
                <i class="fas fa-building" style="color: #fe7f2d;"></i> Enterprise<span>Pro</span>
            </a>
            
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a></li>
                
                <?php if ($user_role === 'admin'): ?>
                    <li><a href="modules/admin/users.php" class="nav-link"><i class="fas fa-users"></i> Users</a></li>
                    <li><a href="modules/admin/reports.php" class="nav-link"><i class="fas fa-chart-bar"></i> Reports</a></li>
                <?php elseif ($user_role === 'manager'): ?>
                    <li><a href="modules/manager/projects.php" class="nav-link"><i class="fas fa-project-diagram"></i> Projects</a></li>
                    <li><a href="modules/manager/teams.php" class="nav-link"><i class="fas fa-user-friends"></i> Teams</a></li>
                <?php else: ?>
                    <li><a href="modules/employee/tasks.php" class="nav-link"><i class="fas fa-tasks"></i> Tasks</a></li>
                    <li><a href="modules/employee/profile.php" class="nav-link"><i class="fas fa-user-circle"></i> Profile</a></li>
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
                <a href="logout.php" class="btn btn-outline" style="padding: 8px 16px;">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="dashboard">
        <div class="container">
            <!-- Welcome Section -->
            <div class="card glass-card fade-in" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2>Welcome, <?php echo Security::escapeOutput($full_name); ?>!</h2>
                    <span class="badge <?php 
                        echo $user_role === 'admin' ? 'badge-danger' : 
                             ($user_role === 'manager' ? 'badge-warning' : 'badge-info'); 
                    ?>">
                        <?php echo ucfirst($user_role); ?>
                    </span>
                </div>
                <p>Welcome to the Enterprise Management System dashboard. Here's what's happening today.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <?php foreach ($stats as $key => $value): ?>
                    <div class="card glass-stat fade-in">
                        <div class="stat-number"><?php echo $value; ?></div>
                        <div class="stat-label">
                            <?php 
                            $labels = [
                                'total_users' => 'Total Users',
                                'total_projects' => 'Total Projects',
                                'total_departments' => 'Departments',
                                'pending_tasks' => 'Pending Tasks',
                                'my_projects' => 'My Projects',
                                'team_members' => 'Team Members',
                                'ongoing_projects' => 'Ongoing Projects',
                                'total_tasks' => 'Total Tasks',
                                'in_progress_tasks' => 'In Progress',
                                'completed_tasks' => 'Completed Tasks'
                            ];
                            echo $labels[$key] ?? ucwords(str_replace('_', ' ', $key));
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Recent Activities -->
            <div class="card glass-card fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Activities</h3>
                </div>
                <div class="table-container">
                    <table class="table glass-table">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($activities)): ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 2rem;">
                                        <i class="fas fa-info-circle" style="color: #666; margin-right: 0.5rem;"></i>
                                        No recent activities found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <span class="badge <?php 
                                                echo $activity['type'] === 'user' ? 'badge-info' : 'badge-success';
                                            ?>">
                                                <i class="fas fa-<?php echo $activity['type'] === 'user' ? 'user' : 'tasks'; ?>"></i>
                                                <?php echo ucfirst($activity['type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo Security::escapeOutput($activity['description']); ?></td>
                                        <td><?php echo date('M d, Y H:i', strtotime($activity['created_at'] ?? $activity['activity_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card glass-card fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; padding: 1rem;">
                    <?php if ($user_role === 'admin'): ?>
                        <a href="modules/admin/users.php?action=create" class="btn btn-primary glass-button">
                            <i class="fas fa-user-plus"></i> Add User
                        </a>
                        <a href="modules/admin/reports.php" class="btn btn-secondary glass-button">
                            <i class="fas fa-file-export"></i> Generate Report
                        </a>
                        <a href="#" class="btn btn-outline glass-button">
                            <i class="fas fa-cog"></i> System Settings
                        </a>
                    <?php elseif ($user_role === 'manager'): ?>
                        <a href="modules/manager/projects.php?action=create" class="btn btn-primary glass-button">
                            <i class="fas fa-plus-circle"></i> New Project
                        </a>
                        <a href="modules/manager/teams.php" class="btn btn-secondary glass-button">
                            <i class="fas fa-user-friends"></i> Manage Team
                        </a>
                        <a href="#" class="btn btn-outline glass-button">
                            <i class="fas fa-chart-line"></i> View Analytics
                        </a>
                    <?php else: ?>
                        <a href="modules/employee/tasks.php" class="btn btn-primary glass-button">
                            <i class="fas fa-tasks"></i> View Tasks
                        </a>
                        <a href="modules/employee/profile.php" class="btn btn-secondary glass-button">
                            <i class="fas fa-user-edit"></i> Edit Profile
                        </a>
                        <a href="#" class="btn btn-outline glass-button">
                            <i class="fas fa-calendar-check"></i> Mark Attendance
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>EnterprisePro</h3>
                    <p>A comprehensive enterprise management solution for modern businesses.</p>
                </div>
                <div class="footer-section">
                    <h3>Quick Links</h3>
                    <ul class="footer-links">
                        <li><a href="dashboard.php">Dashboard</a></li>
                        <?php if ($user_role === 'admin'): ?>
                            <li><a href="modules/admin/users.php">User Management</a></li>
                            <li><a href="modules/admin/reports.php">Reports</a></li>
                        <?php endif; ?>
                        <li><a href="#">Documentation</a></li>
                        <li><a href="#">Support</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <p><i class="fas fa-envelope"></i> support@enterprisepro.com</p>
                    <p><i class="fas fa-phone"></i> +1 (555) 123-4567</p>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> EnterprisePro Management System. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script src="js/main.js"></script>
</body>
</html>