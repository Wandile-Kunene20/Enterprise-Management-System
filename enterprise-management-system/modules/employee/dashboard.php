<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/security.php';

// Check if user is employee
Auth::checkSession();
if (!Auth::hasRole('employee')) {
    header('Location: ../../dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$employee_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// Get employee statistics
$stats = [];

// Total tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ?");
$stmt->execute([$employee_id]);
$stats['total_tasks'] = $stmt->fetch()['total'];

// Pending tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'pending'");
$stmt->execute([$employee_id]);
$stats['pending_tasks'] = $stmt->fetch()['total'];

// In progress tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
$stmt->execute([$employee_id]);
$stats['in_progress_tasks'] = $stmt->fetch()['total'];

// Completed tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'completed'");
$stmt->execute([$employee_id]);
$stats['completed_tasks'] = $stmt->fetch()['total'];

// Overdue tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status != 'completed'");
$stmt->execute([$employee_id]);
$stats['overdue_tasks'] = $stmt->fetch()['total'];

// Completion rate
$completion_rate = $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0;

// Get employee info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$employee_id]);
$employee_info = $stmt->fetch();

// Get recent tasks
$stmt = $db->prepare("
    SELECT t.*, p.name as project_name, u.full_name as assigned_by_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.assigned_by = u.id
    WHERE t.assigned_to = ?
    ORDER BY 
        CASE 
            WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1
            WHEN t.priority = 'urgent' THEN 2
            WHEN t.priority = 'high' THEN 3
            WHEN t.priority = 'medium' THEN 4
            ELSE 5
        END,
        t.due_date ASC
    LIMIT 5
");
$stmt->execute([$employee_id]);
$recent_tasks = $stmt->fetchAll();

// Get upcoming deadlines (tasks due in next 7 days)
$stmt = $db->prepare("
    SELECT t.*, p.name as project_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ? 
    AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND t.status != 'completed'
    ORDER BY t.due_date ASC
    LIMIT 5
");
$stmt->execute([$employee_id]);
$upcoming_deadlines = $stmt->fetchAll();

// Get today's attendance
$today = date('Y-m-d');
$stmt = $db->prepare("SELECT * FROM attendance WHERE user_id = ? AND date = ?");
$stmt->execute([$employee_id, $today]);
$today_attendance = $stmt->fetch();

// Get performance data for chart
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as tasks_assigned,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as tasks_completed
    FROM tasks 
    WHERE assigned_to = ? 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$stmt->execute([$employee_id]);
$performance_data = $stmt->fetchAll();

// Prepare chart data
$chart_labels = [];
$chart_assigned = [];
$chart_completed = [];

foreach ($performance_data as $data) {
    $chart_labels[] = date('M Y', strtotime($data['month'] . '-01'));
    $chart_assigned[] = $data['tasks_assigned'];
    $chart_completed[] = $data['tasks_completed'];
}

$page_title = "Employee Dashboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Enterprise Management System</title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/glass.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <!-- Welcome Section -->
    <div class="card glass-card fade-in" style="margin-top: 2rem;">
        <div class="card-header">
            <h2><i class="fas fa-user"></i> Employee Dashboard</h2>
            <div class="user-info">
                <div class="user-avatar" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                    <?php echo strtoupper(substr($full_name, 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo Security::escapeOutput($full_name); ?></div>
                    <div style="font-size: 0.875rem; color: #666;">
                        <?php echo Security::escapeOutput($employee_info['position'] ?? 'Employee'); ?> | 
                        <?php echo Security::escapeOutput($employee_info['department'] ?? 'N/A'); ?>
                    </div>
                </div>
            </div>
        </div>
        <p>Welcome to your dashboard. Stay productive and manage your tasks efficiently.</p>
        
        <!-- Attendance Status -->
        <div style="margin-top: 1rem; padding: 1rem; background: rgba(254, 127, 45, 0.1); border-radius: 10px; display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-weight: 600; color: #233d4d;">Today's Attendance</div>
                <div style="font-size: 0.875rem; color: #666;">
                    <?php echo date('l, F j, Y'); ?>
                </div>
            </div>
            <div>
                <?php if ($today_attendance): ?>
                    <span class="badge badge-success">
                        <i class="fas fa-check-circle"></i> 
                        Checked in at <?php echo date('h:i A', strtotime($today_attendance['check_in'])); ?>
                    </span>
                <?php else: ?>
                    <button class="btn btn-primary glass-button" onclick="markAttendance()">
                        <i class="fas fa-clock"></i> Check In Now
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="card glass-stat fade-in">
            <div class="stat-number"><?php echo $stats['total_tasks']; ?></div>
            <div class="stat-label">Total Tasks</div>
            <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
                <i class="fas fa-tasks" style="color: #233d4d;"></i>
                Assigned to you
            </div>
        </div>
        
        <div class="card glass-stat fade-in">
            <div class="stat-number"><?php echo $stats['pending_tasks']; ?></div>
            <div class="stat-label">Pending</div>
            <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
                <i class="fas fa-clock" style="color: #ffc107;"></i>
                Waiting to start
            </div>
        </div>
        
        <div class="card glass-stat fade-in">
            <div class="stat-number"><?php echo $stats['in_progress_tasks']; ?></div>
            <div class="stat-label">In Progress</div>
            <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
                <i class="fas fa-spinner" style="color: #17a2b8;"></i>
                Currently working
            </div>
        </div>
        
        <div class="card glass-stat fade-in">
            <div class="stat-number"><?php echo $completion_rate; ?>%</div>
            <div class="stat-label">Completion Rate</div>
            <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
                <i class="fas fa-chart-line" style="color: <?php echo $completion_rate >= 80 ? '#28a745' : ($completion_rate >= 60 ? '#ffc107' : '#dc3545'); ?>;"></i>
                Tasks completed
            </div>
        </div>
    </div>

    <!-- Performance Chart & Task Overview -->
    <div class="grid-2-col">
        <!-- Performance Chart -->
        <div class="card glass-card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Performance Overview</h3>
                <span style="color: #666; font-size: 0.875rem;">Last 6 months</span>
            </div>
            <div style="padding: 1.5rem;">
                <canvas id="performanceChart" height="250"></canvas>
            </div>
        </div>
        
        <!-- Task Overview -->
        <div class="card glass-card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-tasks"></i> Task Overview</h3>
                <a href="tasks.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <!-- Completed Tasks -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Completed</span>
                            <span style="font-weight: 600; color: #28a745;"><?php echo $stats['completed_tasks']; ?></span>
                        </div>
                        <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                            
                        <<div style="width: <?php echo $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0; ?>%; height: 100%; background: #28a745;"></div>
                        </div>
                    </div>
                    
                    <!-- In Progress Tasks -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>In Progress</span>
                            <span style="font-weight: 600; color: #17a2b8;"><?php echo $stats['in_progress_tasks']; ?></span>
                        </div>
                        <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                            <div style="width: <?php echo $stats['total_tasks'] > 0 ? round(($stats['in_progress_tasks'] / $stats['total_tasks']) * 100) : 0; ?>%; height: 100%; background: #17a2b8;"></div>
                        </div>
                    </div>
                    
                    <!-- Pending Tasks -->
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Pending</span>
                            <span style="font-weight: 600; color: #ffc107;"><?php echo $stats['pending_tasks']; ?></span>
                        </div>
                        <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                          
                        
                        <div style="width: <?php echo $stats['total_tasks'] > 0 ? round(($stats['pending_tasks'] / $stats['total_tasks']) * 100) : 0; ?>%; height: 100%; background: #ffc107;"></div>
                        </div>
                    </div>
                    
                    <!-- Overdue Tasks -->
                    <?php if ($stats['overdue_tasks'] > 0): ?>
                    <div>
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span>Overdue</span>
                            <span style="font-weight: 600; color: #dc3545;"><?php echo $stats['overdue_tasks']; ?></span>
                        </div>
                        <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                           <div style="width: <?php echo $stats['total_tasks'] > 0 ? round(($stats['overdue_tasks'] / $stats['total_tasks']) * 100) : 0; ?>%; height: 100%; background: #dc3545;"></div>

                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Quick Stats -->
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-top: 1.5rem;">
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: 600; color: #fe7f2d;"><?php echo $stats['total_tasks']; ?></div>
                        <div style="font-size: 0.875rem; color: #666;">Total Tasks</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: 600; color: #28a745;"><?php echo $completion_rate; ?>%</div>
                        <div style="font-size: 0.875rem; color: #666;">Completion Rate</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Tasks & Upcoming Deadlines -->
    <div class="grid-2-col">
        <!-- Recent Tasks -->
        <div class="card glass-card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Recent Tasks</h3>
                <a href="tasks.php" class="btn btn-outline btn-sm">View All</a>
            </div>
            <div class="table-container">
                <table class="table glass-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Project</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_tasks)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 2rem;">
                                    <div style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <h4 style="color: #666; margin-bottom: 0.5rem;">No Tasks Assigned</h4>
                                    <p style="color: #999;">You haven't been assigned any tasks yet.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_tasks as $task): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['title']); ?></div>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        <?php echo Security::escapeOutput(substr($task['description'], 0, 30)); ?>...
                                    </div>
                                </td>
                                <td><?php echo Security::escapeOutput($task['project_name']); ?></td>
                                <td>
                                    <span class="badge <?php 
                                        switch($task['priority']) {
                                            case 'urgent': echo 'badge-danger'; break;
                                            case 'high': echo 'badge-warning'; break;
                                            case 'medium': echo 'badge-info'; break;
                                            case 'low': echo 'badge-secondary'; break;
                                            default: echo 'badge-light';
                                        }
                                    ?>">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </span>
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
                                        <?php echo date('M d', strtotime($task['due_date'])); ?>
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
        
        <!-- Upcoming Deadlines -->
        <div class="card glass-card fade-in">
            <div class="card-header">
                <h3><i class="fas fa-calendar-alt"></i> Upcoming Deadlines</h3>
                <span style="color: #666; font-size: 0.875rem;">Next 7 days</span>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (empty($upcoming_deadlines)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4 style="color: #666; margin-bottom: 0.5rem;">No Upcoming Deadlines</h4>
                        <p style="color: #999;">You're all caught up!</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($upcoming_deadlines as $task): ?>
                        <?php 
                        $days_remaining = ceil((strtotime($task['due_date']) - time()) / (60 * 60 * 24));
                        $priority_color = '';
                        switch($task['priority']) {
                            case 'urgent': $priority_color = '#dc3545'; break;
                            case 'high': $priority_color = '#ffc107'; break;
                            case 'medium': $priority_color = '#17a2b8'; break;
                            case 'low': $priority_color = '#6c757d'; break;
                        }
                        ?>
                        <div class="card glass-card" style="padding: 1rem; border-left: 4px solid <?php echo $priority_color; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['title']); ?></div>
                                    <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                                        <i class="fas fa-project-diagram"></i>
                                        <?php echo Security::escapeOutput($task['project_name']); ?>
                                    </div>
                                </div>
                                <div style="text-align: right;">
                                    <div style="font-weight: 600; color: <?php echo $priority_color; ?>;">
                                        <?php echo ucfirst($task['priority']); ?>
                                    </div>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        <i class="fas fa-clock"></i>
                                        <?php echo $days_remaining > 0 ? $days_remaining . ' days' : 'Today'; ?>
                                    </div>
                                </div>
                            </div>
                            <div style="margin-top: 0.5rem;">
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
                                <a href="tasks.php?action=view&id=<?php echo $task['id']; ?>" 
                                   class="btn btn-outline btn-sm" style="float: right;">
                                    View <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card glass-card fade-in">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
            <a href="tasks.php" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2.5rem; color: #fe7f2d; margin-bottom: 1rem;">
                    <i class="fas fa-tasks"></i>
                </div>
                <h4>My Tasks</h4>
                <p style="color: #666; font-size: 0.875rem;">View all your tasks</p>
            </a>
            
            <a href="profile.php" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2.5rem; color: #233d4d; margin-bottom: 1rem;">
                    <i class="fas fa-user-edit"></i>
                </div>
                <h4>Update Profile</h4>
                <p style="color: #666; font-size: 0.875rem;">Edit your information</p>
            </a>
            
            <a href="#" class="card glass-card glass-hover" onclick="markAttendance(); return false;" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2.5rem; color: #28a745; margin-bottom: 1rem;">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h4>Mark Attendance</h4>
                <p style="color: #666; font-size: 0.875rem;">Check-in for today</p>
            </a>
            
            <a href="submit_report.php" class="card glass-card glass-hover" style="text-align: center; padding: 1.5rem;">
                <div style="font-size: 2.5rem; color: #17a2b8; margin-bottom: 1rem;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <h4>Submit Report</h4>
                <p style="color: #666; font-size: 0.875rem;">Submit daily/weekly report</p>
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

    .card.glass-hover:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
    }

    .text-center {
        text-align: center;
    }
    </style>

    <script>
    // Initialize Performance Chart
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('performanceChart').getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'Tasks Assigned',
                    data: <?php echo json_encode($chart_assigned); ?>,
                    backgroundColor: 'rgba(254, 127, 45, 0.1)',
                    borderColor: '#fe7f2d',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }, {
                    label: 'Tasks Completed',
                    data: <?php echo json_encode($chart_completed); ?>,
                    backgroundColor: 'rgba(35, 61, 77, 0.1)',
                    borderColor: '#233d4d',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Tasks'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Month'
                        }
                    }
                }
            }
        });
    });

    // Mark Attendance Function
    function markAttendance() {
        if (confirm('Are you sure you want to check in for today?')) {
            // In a real application, this would be an AJAX call
            fetch('../../includes/attendance.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'check_in',
                    user_id: <?php echo $employee_id; ?>
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Checked in successfully at ' + data.check_in_time);
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to check in. Please try again.');
            });
        }
    }

    // Simulate attendance check-in for demo
    function simulateAttendance() {
        alert('Attendance marked successfully! Checked in at ' + new Date().toLocaleTimeString());
        location.reload();
    }
    </script>

    <?php include '../../includes/footer.php'; ?>
</body>
</html>