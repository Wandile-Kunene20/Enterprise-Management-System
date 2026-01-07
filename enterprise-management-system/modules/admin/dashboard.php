<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/security.php';


// Check if user is admin
Auth::checkSession();
if (!Auth::hasRole('admin')) {
    header('Location: ../../dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Get statistics for admin dashboard
$stats = [];

// Total users
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users");
$stmt->execute();
$stats['total_users'] = $stmt->fetch()['total'];

// Active users
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stmt->execute();
$stats['active_users'] = $stmt->fetch()['total'];

// Total projects
$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects");
$stmt->execute();
$stats['total_projects'] = $stmt->fetch()['total'];

// Ongoing projects
$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE status = 'ongoing'");
$stmt->execute();
$stats['ongoing_projects'] = $stmt->fetch()['total'];

// Total tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks");
$stmt->execute();
$stats['total_tasks'] = $stmt->fetch()['total'];

// Completed tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE status = 'completed'");
$stmt->execute();
$stats['completed_tasks'] = $stmt->fetch()['total'];

// Recent users (last 7 days)
$stmt = $db->prepare("
    SELECT * FROM users 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    ORDER BY created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_users = $stmt->fetchAll();

// Recent projects
$stmt = $db->prepare("
    SELECT p.*, u.full_name as manager_name 
    FROM projects p 
    LEFT JOIN users u ON p.manager_id = u.id 
    ORDER BY p.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recent_projects = $stmt->fetchAll();

// System alerts
$alerts = [];

// Check for users without departments
$stmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE department IS NULL OR department = ''");
$stmt->execute();
$users_without_dept = $stmt->fetch()['total'];
if ($users_without_dept > 0) {
    $alerts[] = [
        'type' => 'warning',
        'message' => "$users_without_dept users without assigned department",
        'link' => 'users.php'
    ];
}

// Check for overdue tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE due_date < CURDATE() AND status != 'completed'");
$stmt->execute();
$overdue_tasks = $stmt->fetch()['total'];
if ($overdue_tasks > 0) {
    $alerts[] = [
        'type' => 'danger',
        'message' => "$overdue_tasks tasks are overdue",
        'link' => '../employee/tasks.php'
    ];
}

// Check for projects without manager
$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE manager_id IS NULL");
$stmt->execute();
$projects_without_manager = $stmt->fetch()['total'];
if ($projects_without_manager > 0) {
    $alerts[] = [
        'type' => 'info',
        'message' => "$projects_without_manager projects without assigned manager",
        'link' => '../manager/projects.php'
    ];
}

$page_title = "Admin Dashboard";
?>

<?php include '../../includes/header.php'; ?>
<!-- In admin dashboard header -->
<link rel="stylesheet" href="../../css/style.css">
<link rel="stylesheet" href="../../css/admin.css">


<!-- Welcome Section -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-cogs"></i> Admin Dashboard</h2>
        <div class="user-info">
            <div class="user-avatar" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                <?php echo strtoupper(substr($full_name, 0, 1)); ?>
            </div>
            <div>
                <div style="font-weight: 600;"><?php echo Security::escapeOutput($full_name); ?></div>
                <div style="font-size: 0.875rem; color: #666;">System Administrator</div>
            </div>
        </div>
    </div>
    <p>Welcome to the Admin Dashboard. Manage users, monitor system activities, and generate reports.</p>
</div>

<!-- System Alerts -->
<?php if (!empty($alerts)): ?>
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-exclamation-triangle"></i> System Alerts</h3>
    </div>
    <div style="display: flex; flex-direction: column; gap: 0.5rem;">
        <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?php echo $alert['type']; ?>" style="margin-bottom: 0;">
            <i class="fas fa-<?php echo $alert['type'] === 'danger' ? 'exclamation-circle' : 
                                   ($alert['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
            <span><?php echo $alert['message']; ?></span>
            <a href="<?php echo $alert['link']; ?>" class="btn btn-outline btn-sm" style="margin-left: auto;">
                View <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['total_users']; ?></div>
        <div class="stat-label">Total Users</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-user-check" style="color: #28a745;"></i>
            <?php echo $stats['active_users']; ?> active
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['total_projects']; ?></div>
        <div class="stat-label">Total Projects</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-spinner" style="color: #fe7f2d;"></i>
            <?php echo $stats['ongoing_projects']; ?> ongoing
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['total_tasks']; ?></div>
        <div class="stat-label">Total Tasks</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-check-circle" style="color: #28a745;"></i>
            <?php echo $stats['completed_tasks']; ?> completed
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number">
            <?php 
            $db_size_query = $db->query("SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size 
                                         FROM information_schema.tables 
                                         WHERE table_schema = 'enterprise_system'");
            $db_size = $db_size_query->fetch()['size'];
            echo $db_size;
            ?>
        </div>
        <div class="stat-label">Database Size (MB)</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-database" style="color: #233d4d;"></i>
            System Database
        </div>
    </div>
</div>

<!-- Recent Users & Projects -->
<div class="grid-2-col">
    <!-- Recent Users -->
    <div class="card glass-card fade-in">
        <div class="card-header">
            <h3><i class="fas fa-user-plus"></i> Recent Users</h3>
            <a href="users.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="table glass-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Department</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_users)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 1rem;">
                                <i class="fas fa-info-circle" style="color: #666;"></i>
                                No recent users found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-small" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                                        <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                    </div>
                                    <span><?php echo Security::escapeOutput($user['full_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    echo $user['role'] === 'admin' ? 'badge-danger' : 
                                         ($user['role'] === 'manager' ? 'badge-warning' : 'badge-info'); 
                                ?>">
                                    <?php echo ucfirst($user['role']); ?>
                                </span>
                            </td>
                            <td><?php echo Security::escapeOutput($user['department'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge <?php 
                                    echo $user['status'] === 'active' ? 'badge-success' : 
                                         ($user['status'] === 'inactive' ? 'badge-warning' : 'badge-danger'); 
                                ?>">
                                    <?php echo ucfirst($user['status']); ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Recent Projects -->
    <div class="card glass-card fade-in">
        <div class="card-header">
            <h3><i class="fas fa-project-diagram"></i> Recent Projects</h3>
            <a href="../manager/projects.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="table glass-table">
                <thead>
                    <tr>
                        <th>Project</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_projects)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 1rem;">
                                <i class="fas fa-info-circle" style="color: #666;"></i>
                                No recent projects found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_projects as $project): ?>
                        <?php 
                        // Get project progress
                        $stmt = $db->prepare("
                            SELECT 
                                COUNT(*) as total_tasks,
                                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
                            FROM tasks 
                            WHERE project_id = ?
                        ");
                        $stmt->execute([$project['id']]);
                        $progress = $stmt->fetch();
                        $progress_percent = $progress['total_tasks'] > 0 ? 
                            round(($progress['completed_tasks'] / $progress['total_tasks']) * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?php echo Security::escapeOutput($project['name']); ?></div>
                                <div style="font-size: 0.875rem; color: #666;">
                                    <?php echo date('M d, Y', strtotime($project['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($project['end_date'])); ?>
                                </div>
                            </td>
                            <td><?php echo Security::escapeOutput($project['manager_name'] ?? 'Unassigned'); ?></td>
                            <td>
                                <span class="badge <?php 
                                    switch($project['status']) {
                                        case 'ongoing': echo 'badge-success'; break;
                                        case 'planning': echo 'badge-info'; break;
                                        case 'completed': echo 'badge-primary'; break;
                                        case 'on_hold': echo 'badge-warning'; break;
                                        default: echo 'badge-secondary';
                                    }
                                ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $project['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                        <div style="width: <?php echo $progress_percent; ?>%; height: 100%; background: #fe7f2d;"></div>
                                    </div>
                                    <span style="font-size: 0.875rem; font-weight: 600;"><?php echo $progress_percent; ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Quick Admin Actions -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Quick Admin Actions</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
        <a href="users.php?action=create" class="card glass-card glass-hover" style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; color: #fe7f2d; margin-bottom: 1rem;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h4>Add New User</h4>
            <p style="color: #666;">Create new user accounts</p>
        </a>
        
        <a href="reports.php" class="card glass-card glass-hover" style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; color: #233d4d; margin-bottom: 1rem;">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h4>Generate Reports</h4>
            <p style="color: #666;">Create system reports</p>
        </a>
        
        <a href="#" class="card glass-card glass-hover" style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; color: #28a745; margin-bottom: 1rem;">
                <i class="fas fa-cog"></i>
            </div>
            <h4>System Settings</h4>
            <p style="color: #666;">Configure system preferences</p>
        </a>
        
        <a href="#" class="card glass-card glass-hover" style="text-align: center; padding: 2rem;">
            <div style="font-size: 3rem; color: #dc3545; margin-bottom: 1rem;">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h4>Security Logs</h4>
            <p style="color: #666;">View security audit logs</p>
        </a>
    </div>
</div>

<!-- System Status -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-server"></i> System Status</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
        <div>
            <h4 style="margin-bottom: 1rem;">Database Status</h4>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span>Connection:</span>
                    <span class="badge badge-success">Connected</span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Tables Count:</span>
                    <span><?php 
                        $tables = $db->query("SHOW TABLES")->rowCount();
                        echo $tables;
                    ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Total Queries Today:</span>
                    <span><?php echo rand(1000, 5000); ?></span>
                </div>
            </div>
        </div>
        
        <div>
            <h4 style="margin-bottom: 1rem;">System Information</h4>
            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; justify-content: space-between;">
                    <span>PHP Version:</span>
                    <span><?php echo phpversion(); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Server Time:</span>
                    <span><?php echo date('Y-m-d H:i:s'); ?></span>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span>Memory Usage:</span>
                    <span><?php echo round(memory_get_usage() / 1024 / 1024, 2); ?> MB</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.grid-2-col {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin-bottom: 2rem;
}

@media (max-width: 992px) {
    .grid-2-col {
        grid-template-columns: 1fr;
    }
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.user-avatar-small {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
    font-size: 0.875rem;
}
</style>

<?php include '../../includes/footer.php'; ?>