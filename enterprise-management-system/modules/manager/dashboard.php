<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/security.php';



// Check if user is manager
Auth::checkSession();
if (!Auth::hasRole('manager')) {
    header('Location: ../../dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$manager_id = $_SESSION['user_id'];

// Get manager statistics
$stats = [];

// My projects
$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE manager_id = ?");
$stmt->execute([$manager_id]);
$stats['my_projects'] = $stmt->fetch()['total'];

// Ongoing projects
$stmt = $db->prepare("SELECT COUNT(*) as total FROM projects WHERE manager_id = ? AND status = 'ongoing'");
$stmt->execute([$manager_id]);
$stats['ongoing_projects'] = $stmt->fetch()['total'];

// Team members
$stmt = $db->prepare("
    SELECT COUNT(DISTINCT u.id) as total 
    FROM users u 
    JOIN tasks t ON u.id = t.assigned_to 
    JOIN projects p ON t.project_id = p.id 
    WHERE p.manager_id = ? AND u.role = 'employee'
");
$stmt->execute([$manager_id]);
$stats['team_members'] = $stmt->fetch()['total'];

// Pending tasks
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.id 
    WHERE p.manager_id = ? AND t.status = 'pending'
");
$stmt->execute([$manager_id]);
$stats['pending_tasks'] = $stmt->fetch()['total'];

// Overdue tasks
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.id 
    WHERE p.manager_id = ? AND t.due_date < CURDATE() AND t.status != 'completed'
");
$stmt->execute([$manager_id]);
$stats['overdue_tasks'] = $stmt->fetch()['total'];

// Completed tasks this month
$stmt = $db->prepare("
    SELECT COUNT(*) as total 
    FROM tasks t 
    JOIN projects p ON t.project_id = p.id 
    WHERE p.manager_id = ? AND t.status = 'completed' 
    AND MONTH(t.completed_at) = MONTH(CURDATE()) 
    AND YEAR(t.completed_at) = YEAR(CURDATE())
");
$stmt->execute([$manager_id]);
$stats['completed_this_month'] = $stmt->fetch()['total'];

// Get my projects
$stmt = $db->prepare("
    SELECT p.*, 
           COUNT(t.id) as total_tasks,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM projects p
    LEFT JOIN tasks t ON p.id = t.project_id
    WHERE p.manager_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
    LIMIT 5
");
$stmt->execute([$manager_id]);
$my_projects = $stmt->fetchAll();

// Get recent tasks
$stmt = $db->prepare("
    SELECT t.*, p.name as project_name, u.full_name as assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.assigned_to = u.id
    WHERE p.manager_id = ?
    ORDER BY t.created_at DESC
    LIMIT 10
");
$stmt->execute([$manager_id]);
$recent_tasks = $stmt->fetchAll();

// Get team members
$stmt = $db->prepare("
    SELECT DISTINCT u.*, 
           COUNT(t.id) as task_count,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM users u
    JOIN tasks t ON u.id = t.assigned_to
    JOIN projects p ON t.project_id = p.id
    WHERE p.manager_id = ? AND u.role = 'employee'
    GROUP BY u.id
    ORDER BY u.full_name
");
$stmt->execute([$manager_id]);
$team_members = $stmt->fetchAll();

$page_title = "Manager Dashboard";
?>

<?php include '../../includes/header.php'; ?>

<!-- Welcome Section -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <!-- In manager dashboard header -->
<link rel="stylesheet" href="../../css/style.css">
<link rel="stylesheet" href="../../css/manager.css">
    <div class="card-header">
        <h2><i class="fas fa-user-tie"></i> Manager Dashboard</h2>
        <div class="user-info">
            <div class="user-avatar" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                <?php echo strtoupper(substr($full_name, 0, 1)); ?>
            </div>
            <div>
                <div style="font-weight: 600;"><?php echo Security::escapeOutput($full_name); ?></div>
                <div style="font-size: 0.875rem; color: #666;">Project Manager</div>
            </div>
        </div>
    </div>
    <p>Welcome to the Manager Dashboard. Manage your projects, teams, and tasks efficiently.</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['my_projects']; ?></div>
        <div class="stat-label">Total Projects</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-spinner" style="color: #fe7f2d;"></i>
            <?php echo $stats['ongoing_projects']; ?> ongoing
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['team_members']; ?></div>
        <div class="stat-label">Team Members</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-users" style="color: #233d4d;"></i>
            In your team
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['pending_tasks']; ?></div>
        <div class="stat-label">Pending Tasks</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
            <?php echo $stats['overdue_tasks']; ?> overdue
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $stats['completed_this_month']; ?></div>
        <div class="stat-label">Completed This Month</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-check-circle" style="color: #28a745;"></i>
            Tasks completed
        </div>
    </div>
</div>

<!-- My Projects -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-project-diagram"></i> My Projects</h3>
        <a href="projects.php" class="btn btn-outline btn-sm">View All</a>
    </div>
    <div class="table-container">
        <table class="table glass-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Status</th>
                    <th>Timeline</th>
                    <th>Tasks</th>
                    <th>Progress</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($my_projects)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 2rem;">
                            <div style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <h4 style="color: #666; margin-bottom: 0.5rem;">No Projects Found</h4>
                            <p style="color: #999;">You haven't been assigned any projects yet.</p>
                            <a href="projects.php?action=create" class="btn btn-primary glass-button">
                                <i class="fas fa-plus"></i> Create New Project
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($my_projects as $project): ?>
                    <?php 
                    $progress = $project['total_tasks'] > 0 ? 
                        round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo Security::escapeOutput($project['name']); ?></div>
                            <div style="font-size: 0.875rem; color: #666;">
                                <?php echo Security::escapeOutput(substr($project['description'], 0, 50)); ?>...
                            </div>
                        </td>
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
                            <div style="font-size: 0.875rem;">
                                <div><i class="fas fa-play-circle" style="color: #28a745;"></i> <?php echo date('M d, Y', strtotime($project['start_date'])); ?></div>
                                <div><i class="fas fa-flag-checkered" style="color: #fe7f2d;"></i> <?php echo date('M d, Y', strtotime($project['end_date'])); ?></div>
                            </div>
                        </td>
                        <td>
                            <div style="text-align: center;">
                                <div style="font-weight: 600; font-size: 1.25rem;"><?php echo $project['total_tasks']; ?></div>
                                <div style="font-size: 0.75rem; color: #666;">total tasks</div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1;">
                                    <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo $progress; ?>%; height: 100%; background: #fe7f2d;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 600; min-width: 40px;"><?php echo $progress; ?>%</span>
                            </div>
                            <div style="font-size: 0.75rem; color: #666; text-align: center;">
                                <?php echo $project['completed_tasks']; ?> of <?php echo $project['total_tasks']; ?> completed
                            </div>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="projects.php?action=view&id=<?php echo $project['id']; ?>" 
                                   class="btn btn-outline btn-sm" title="View Project">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="projects.php?action=edit&id=<?php echo $project['id']; ?>" 
                                   class="btn btn-outline btn-sm" title="Edit Project">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Tasks & Team Members -->
<div class="grid-2-col">
    <!-- Recent Tasks -->
    <div class="card glass-card fade-in">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Recent Tasks</h3>
            <a href="../employee/tasks.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-container">
            <table class="table glass-table">
                <thead>
                    <tr>
                        <th>Task</th>
                        <th>Project</th>
                        <th>Assigned To</th>
                        <th>Status</th>
                        <th>Due Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_tasks)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 1rem;">
                                <i class="fas fa-info-circle" style="color: #666;"></i>
                                No recent tasks found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_tasks as $task): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['title']); ?></div>
                                <div style="font-size: 0.75rem; color: #666;">
                                    <?php echo Security::escapeOutput(substr($task['description'], 0, 30)); ?>...
                                </div>
                            </td>
                            <td><?php echo Security::escapeOutput($task['project_name']); ?></td>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-small" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                                        <?php echo strtoupper(substr($task['assigned_to_name'], 0, 1)); ?>
                                    </div>
                                    <span style="font-size: 0.875rem;"><?php echo Security::escapeOutput($task['assigned_to_name']); ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php 
                                    switch($task['status']) {
                                        case 'completed': echo 'badge-success'; break;
                                        case 'in_progress': echo 'badge-primary'; break;
                                        case 'pending': echo 'badge-warning'; break;
                                        case 'deferred': echo 'badge-danger'; break;
                                        default: echo 'badge-secondary';
                                    }
                                ?>">
                                    <?php echo ucwords(str_replace('_', ' ', $task['status'])); ?>
                                </span>
                            </td>
                            <td>
                                <div style="font-size: 0.875rem;">
                                    <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                    <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'completed'): ?>
                                        <div style="font-size: 0.75rem; color: #dc3545;">
                                            <i class="fas fa-exclamation-circle"></i> Overdue
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Team Members -->
    <div class="card glass-card fade-in">
        <div class="card-header">
            <h3><i class="fas fa-user-friends"></i> Team Members</h3>
            <a href="teams.php" class="btn btn-outline btn-sm">Manage Team</a>
        </div>
        <div class="table-container">
            <table class="table glass-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Tasks</th>
                        <th>Performance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($team_members)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 2rem;">
                                <div style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;">
                                    <i class="fas fa-users"></i>
                                </div>
                                <h4 style="color: #666; margin-bottom: 0.5rem;">No Team Members</h4>
                                <p style="color: #999;">You don't have any team members assigned yet.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($team_members as $member): ?>
                        <?php 
                        $performance = $member['task_count'] > 0 ? 
                            round(($member['completed_tasks'] / $member['task_count']) * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-small" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                                        <?php echo strtoupper(substr($member['full_name'], 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($member['full_name']); ?></div>
                                        <div style="font-size: 0.75rem; color: #666;">@<?php echo $member['username']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?php echo Security::escapeOutput($member['department'] ?? 'N/A'); ?></td>
                            <td><?php echo Security::escapeOutput($member['position'] ?? 'N/A'); ?></td>
                            <td>
                                <div style="text-align: center;">
                                    <div style="font-weight: 600;"><?php echo $member['task_count']; ?></div>
                                    <div style="font-size: 0.75rem; color: #666;">assigned</div>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <div style="flex: 1;">
                                        <div style="height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo $performance; ?>%; height: 100%; 
                                                        background: <?php echo $performance >= 80 ? '#28a745' : ($performance >= 60 ? '#ffc107' : '#dc3545'); ?>;"></div>
                                        </div>
                                    </div>
                                    <span style="font-weight: 600; font-size: 0.875rem; 
                                                color: <?php echo $performance >= 80 ? '#28a745' : ($performance >= 60 ? '#856404' : '#dc3545'); ?>;">
                                        <?php echo $performance; ?>%
                                    </span>
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

<!-- Quick Actions -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
        <a href="projects.php?action=create" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 2.5rem; color: #fe7f2d; margin-bottom: 1rem;">
                <i class="fas fa-plus-circle"></i>
            </div>
            <h4>New Project</h4>
            <p style="color: #666; font-size: 0.875rem;">Create a new project</p>
        </a>
        
        <a href="teams.php" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 2.5rem; color: #233d4d; margin-bottom: 1rem;">
                <i class="fas fa-user-plus"></i>
            </div>
            <h4>Assign Team</h4>
            <p style="color: #666; font-size: 0.875rem;">Assign team to projects</p>
        </a>
        
        <a href="../employee/tasks.php?action=create" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 2.5rem; color: #28a745; margin-bottom: 1rem;">
                <i class="fas fa-tasks"></i>
            </div>
            <h4>Create Task</h4>
            <p style="color: #666; font-size: 0.875rem;">Assign new tasks</p>
        </a>
        
        <a href="#" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
            <div style="font-size: 2.5rem; color: #17a2b8; margin-bottom: 1rem;">
                <i class="fas fa-chart-line"></i>
            </div>
            <h4>Reports</h4>
            <p style="color: #666; font-size: 0.875rem;">View performance reports</p>
        </a>
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

.card.glass-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
}
</style>

<?php include '../../includes/footer.php'; ?>