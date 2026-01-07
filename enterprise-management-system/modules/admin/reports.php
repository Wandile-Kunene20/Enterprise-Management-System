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

// Handle report generation
$report_type = Security::sanitizeInput($_GET['type'] ?? '');
$start_date = Security::sanitizeInput($_GET['start_date'] ?? date('Y-m-01'));
$end_date = Security::sanitizeInput($_GET['end_date'] ?? date('Y-m-t'));
$department = Security::sanitizeInput($_GET['department'] ?? 'all');

// Get departments for filter
$stmt = $db->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate report data based on type
$report_data = [];
$report_title = '';
$chart_data = [];

switch ($report_type) {
    case 'user_activity':
        $report_title = 'User Activity Report';
        
        // Get user login activity
        $stmt = $db->prepare("
            SELECT 
                u.username,
                u.full_name,
                u.department,
                u.last_login,
                COUNT(DISTINCT DATE(a.date)) as attendance_days,
                COUNT(t.id) as task_count,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id AND a.date BETWEEN ? AND ?
            LEFT JOIN tasks t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
            WHERE u.status = 'active'
            GROUP BY u.id
            ORDER BY u.last_login DESC
        ");
        $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
        $report_data = $stmt->fetchAll();
        
        // Prepare chart data
        $chart_data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Tasks Assigned',
                    'data' => [],
                    'backgroundColor' => '#fe7f2d'
                ],
                [
                    'label' => 'Tasks Completed',
                    'data' => [],
                    'backgroundColor' => '#233d4d'
                ]
            ]
        ];
        
        foreach ($report_data as $user) {
            $chart_data['labels'][] = $user['full_name'];
            $chart_data['datasets'][0]['data'][] = $user['task_count'];
            $chart_data['datasets'][1]['data'][] = $user['completed_tasks'];
        }
        break;
        
    case 'project_progress':
        $report_title = 'Project Progress Report';
        
        // Get project progress
        $stmt = $db->prepare("
            SELECT 
                p.name as project_name,
                p.status,
                p.start_date,
                p.end_date,
                p.budget,
                u.full_name as manager_name,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate
            FROM projects p
            LEFT JOIN users u ON p.manager_id = u.id
            LEFT JOIN tasks t ON p.id = t.project_id
            WHERE p.start_date BETWEEN ? AND ?
            GROUP BY p.id
            ORDER BY p.start_date DESC
        ");
        $stmt->execute([$start_date, $end_date]);
        $report_data = $stmt->fetchAll();
        
        // Prepare chart data
        $chart_data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Completion Rate %',
                    'data' => [],
                    'backgroundColor' => '#fe7f2d',
                    'borderColor' => '#fe7f2d',
                    'fill' => false,
                    'tension' => 0.1
                ]
            ]
        ];
        
        foreach ($report_data as $project) {
            $chart_data['labels'][] = $project['project_name'];
            $chart_data['datasets'][0]['data'][] = round($project['completion_rate']);
        }
        break;
        
    case 'department_performance':
        $report_title = 'Department Performance Report';
        
        // Get department performance
        $where_clause = $department !== 'all' ? " AND u.department = ?" : "";
        $params = $department !== 'all' ? [$start_date, $end_date, $department] : [$start_date, $end_date];
        
        $stmt = $db->prepare("
            SELECT 
                u.department,
                COUNT(DISTINCT u.id) as employee_count,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate,
                COUNT(DISTINCT p.id) as project_count
            FROM users u
            LEFT JOIN tasks t ON u.id = t.assigned_to AND t.created_at BETWEEN ? AND ?
            LEFT JOIN projects p ON u.department = p.department_id AND p.start_date BETWEEN ? AND ?
            WHERE u.department IS NOT NULL $where_clause
            GROUP BY u.department
            ORDER BY completion_rate DESC
        ");
        
        if ($department !== 'all') {
            $stmt->execute([$start_date, $end_date, $department, $start_date, $end_date, $department]);
        } else {
            $stmt->execute([$start_date, $end_date, $start_date, $end_date]);
        }
        
        $report_data = $stmt->fetchAll();
        
        // Prepare chart data
        $chart_data = [
            'labels' => [],
            'datasets' => [
                [
                    'label' => 'Completion Rate %',
                    'data' => [],
                    'backgroundColor' => []
                ]
            ]
        ];
        
        $colors = ['#fe7f2d', '#233d4d', '#28a745', '#dc3545', '#ffc107', '#17a2b8'];
        $color_index = 0;
        
        foreach ($report_data as $dept) {
            $chart_data['labels'][] = $dept['department'];
            $chart_data['datasets'][0]['data'][] = round($dept['completion_rate']);
            $chart_data['datasets'][0]['backgroundColor'][] = $colors[$color_index % count($colors)];
            $color_index++;
        }
        break;
        
    case 'financial':
        $report_title = 'Financial Report';
        
        // Get financial data
        $stmt = $db->prepare("
            SELECT 
                p.name as project_name,
                p.budget,
                p.actual_cost,
                p.budget - COALESCE(p.actual_cost, 0) as remaining_budget,
                COUNT(t.id) as total_tasks,
                SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                AVG(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) * 100 as completion_rate
            FROM projects p
            LEFT JOIN tasks t ON p.id = t.project_id
            WHERE p.budget IS NOT NULL
            GROUP BY p.id
            ORDER BY p.budget DESC
        ");
        $stmt->execute();
        $report_data = $stmt->fetchAll();
        
        // Calculate totals
        $total_budget = 0;
        $total_actual = 0;
        foreach ($report_data as $project) {
            $total_budget += $project['budget'];
            $total_actual += $project['actual_cost'] ?? 0;
        }
        $total_remaining = $total_budget - $total_actual;
        
        // Prepare chart data
        $chart_data = [
            'labels' => ['Budget', 'Actual Cost', 'Remaining'],
            'datasets' => [
                [
                    'label' => 'Financial Summary',
                    'data' => [$total_budget, $total_actual, $total_remaining],
                    'backgroundColor' => ['#fe7f2d', '#233d4d', '#28a745']
                ]
            ]
        ];
        break;
        
    default:
        $report_title = 'Select Report Type';
        break;
}

$page_title = "Reports - Admin";
?>

<?php include '../../includes/header.php'; ?>

<!-- Report Header -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-chart-bar"></i> Reports & Analytics</h2>
        <button class="btn btn-primary glass-button" onclick="exportReport()">
            <i class="fas fa-download"></i> Export Report
        </button>
    </div>
    <p>Generate and analyze system reports for better decision making.</p>
</div>

<!-- Report Filters -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-filter"></i> Report Filters</h3>
    </div>
    <form method="GET" action="" id="reportForm">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
            <div class="form-group">
                <label for="report_type" class="form-label">Report Type</label>
                <select id="report_type" name="type" class="form-control glass-input" onchange="updateForm()">
                    <option value="">Select Report Type</option>
                    <option value="user_activity" <?php echo $report_type == 'user_activity' ? 'selected' : ''; ?>>User Activity</option>
                    <option value="project_progress" <?php echo $report_type == 'project_progress' ? 'selected' : ''; ?>>Project Progress</option>
                    <option value="department_performance" <?php echo $report_type == 'department_performance' ? 'selected' : ''; ?>>Department Performance</option>
                    <option value="financial" <?php echo $report_type == 'financial' ? 'selected' : ''; ?>>Financial Report</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control glass-input" 
                       value="<?php echo $start_date; ?>">
            </div>
            
            <div class="form-group">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control glass-input" 
                       value="<?php echo $end_date; ?>">
            </div>
            
            <div class="form-group" id="departmentGroup" style="<?php echo $report_type == 'department_performance' ? '' : 'display: none;'; ?>">
                <label for="department" class="form-label">Department</label>
                <select id="department" name="department" class="form-control glass-input">
                    <option value="all">All Departments</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?php echo Security::escapeOutput($dept); ?>" 
                                <?php echo $department == $dept ? 'selected' : ''; ?>>
                            <?php echo Security::escapeOutput($dept); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="padding: 0 1.5rem 1.5rem; display: flex; gap: 1rem;">
            <button type="submit" class="btn btn-primary glass-button">
                <i class="fas fa-chart-line"></i> Generate Report
            </button>
            <button type="button" class="btn btn-outline" onclick="resetFilters()">
                <i class="fas fa-redo"></i> Reset Filters
            </button>
        </div>
    </form>
</div>

<?php if ($report_type && !empty($report_data)): ?>
<!-- Report Results -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> <?php echo $report_title; ?></h3>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <span style="font-size: 0.875rem; color: #666;">
                <i class="fas fa-calendar"></i>
                <?php echo date('M d, Y', strtotime($start_date)); ?> - 
                <?php echo date('M d, Y', strtotime($end_date)); ?>
            </span>
            <?php if ($department !== 'all' && $department !== ''): ?>
                <span class="badge badge-info"><?php echo $department; ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Chart Section -->
    <div style="padding: 2rem;">
        <canvas id="reportChart" height="100"></canvas>
    </div>
    
    <!-- Data Table -->
    <div class="table-container">
        <table class="table glass-table">
            <thead>
                <tr>
                    <?php if ($report_type == 'user_activity'): ?>
                        <th>User</th>
                        <th>Department</th>
                        <th>Last Login</th>
                        <th>Attendance Days</th>
                        <th>Tasks Assigned</th>
                        <th>Tasks Completed</th>
                        <th>Completion Rate</th>
                    <?php elseif ($report_type == 'project_progress'): ?>
                        <th>Project</th>
                        <th>Manager</th>
                        <th>Status</th>
                        <th>Timeline</th>
                        <th>Total Tasks</th>
                        <th>Completed</th>
                        <th>Progress</th>
                    <?php elseif ($report_type == 'department_performance'): ?>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Projects</th>
                        <th>Tasks Assigned</th>
                        <th>Tasks Completed</th>
                        <th>Completion Rate</th>
                    <?php elseif ($report_type == 'financial'): ?>
                        <th>Project</th>
                        <th>Budget</th>
                        <th>Actual Cost</th>
                        <th>Remaining</th>
                        <th>Total Tasks</th>
                        <th>Completed</th>
                        <th>Progress</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($report_data as $row): ?>
                <tr>
                    <?php if ($report_type == 'user_activity'): ?>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar-small" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                                    <?php echo strtoupper(substr($row['full_name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight: 600;"><?php echo Security::escapeOutput($row['full_name']); ?></div>
                                    <div style="font-size: 0.875rem; color: #666;">@<?php echo $row['username']; ?></div>
                                </div>
                            </div>
                        </td>
                        <td><?php echo Security::escapeOutput($row['department'] ?? 'N/A'); ?></td>
                        <td><?php echo $row['last_login'] ? date('M d, Y', strtotime($row['last_login'])) : 'Never'; ?></td>
                        <td><?php echo $row['attendance_days']; ?></td>
                        <td><?php echo $row['task_count']; ?></td>
                        <td><?php echo $row['completed_tasks']; ?></td>
                        <td>
                            <?php 
                            $rate = $row['task_count'] > 0 ? round(($row['completed_tasks'] / $row['task_count']) * 100) : 0;
                            ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $rate; ?>%; height: 100%; background: #fe7f2d;"></div>
                                </div>
                                <span style="font-size: 0.875rem; font-weight: 600;"><?php echo $rate; ?>%</span>
                            </div>
                        </td>
                    <?php elseif ($report_type == 'project_progress'): ?>
                        <td>
                            <div style="font-weight: 600;"><?php echo Security::escapeOutput($row['project_name']); ?></div>
                        </td>
                        <td><?php echo Security::escapeOutput($row['manager_name'] ?? 'Unassigned'); ?></td>
                        <td>
                            <span class="badge <?php 
                                switch($row['status']) {
                                    case 'ongoing': echo 'badge-success'; break;
                                    case 'planning': echo 'badge-info'; break;
                                    case 'completed': echo 'badge-primary'; break;
                                    case 'on_hold': echo 'badge-warning'; break;
                                    default: echo 'badge-secondary';
                                }
                            ?>">
                                <?php echo ucwords(str_replace('_', ' ', $row['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem;">
                                <?php echo date('M d, Y', strtotime($row['start_date'])); ?> - 
                                <?php echo date('M d, Y', strtotime($row['end_date'])); ?>
                            </div>
                        </td>
                        <td><?php echo $row['total_tasks']; ?></td>
                        <td><?php echo $row['completed_tasks']; ?></td>
                        <td>
                            <?php $rate = round($row['completion_rate']); ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $rate; ?>%; height: 100%; background: #fe7f2d;"></div>
                                </div>
                                <span style="font-size: 0.875rem; font-weight: 600;"><?php echo $rate; ?>%</span>
                            </div>
                        </td>
                    <?php elseif ($report_type == 'department_performance'): ?>
                        <td>
                            <div style="font-weight: 600;"><?php echo Security::escapeOutput($row['department']); ?></div>
                        </td>
                        <td><?php echo $row['employee_count']; ?></td>
                        <td><?php echo $row['project_count']; ?></td>
                        <td><?php echo $row['total_tasks']; ?></td>
                        <td><?php echo $row['completed_tasks']; ?></td>
                        <td>
                            <?php $rate = round($row['completion_rate']); ?>
                            <span class="badge <?php 
                                if ($rate >= 80) echo 'badge-success';
                                elseif ($rate >= 60) echo 'badge-warning';
                                else echo 'badge-danger';
                            ?>">
                                <?php echo $rate; ?>%
                            </span>
                        </td>
                    <?php elseif ($report_type == 'financial'): ?>
                        <td>
                            <div style="font-weight: 600;"><?php echo Security::escapeOutput($row['project_name']); ?></div>
                        </td>
                        <td>
                            <div style="font-weight: 600; color: #233d4d;">
                                $<?php echo number_format($row['budget'], 2); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($row['actual_cost']): ?>
                                <div style="color: #fe7f2d;">
                                    $<?php echo number_format($row['actual_cost'], 2); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #666;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="color: <?php echo $row['remaining_budget'] >= 0 ? '#28a745' : '#dc3545'; ?>; font-weight: 600;">
                                $<?php echo number_format($row['remaining_budget'], 2); ?>
                            </div>
                        </td>
                        <td><?php echo $row['total_tasks']; ?></td>
                        <td><?php echo $row['completed_tasks']; ?></td>
                        <td>
                            <?php $rate = round($row['completion_rate']); ?>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1; height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                                    <div style="width: <?php echo $rate; ?>%; height: 100%; background: #fe7f2d;"></div>
                                </div>
                                <span style="font-size: 0.875rem; font-weight: 600;"><?php echo $rate; ?>%</span>
                            </div>
                        </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Summary Stats -->
    <?php if ($report_type == 'financial'): ?>
    <div style="padding: 1.5rem; border-top: 1px solid #eee;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
            <div class="card glass-stat" style="text-align: center;">
                <div class="stat-number" style="color: #233d4d;">
                    $<?php echo number_format($total_budget, 2); ?>
                </div>
                <div class="stat-label">Total Budget</div>
            </div>
            <div class="card glass-stat" style="text-align: center;">
                <div class="stat-number" style="color: #fe7f2d;">
                    $<?php echo number_format($total_actual, 2); ?>
                </div>
                <div class="stat-label">Actual Cost</div>
            </div>
            <div class="card glass-stat" style="text-align: center;">
                <div class="stat-number" style="color: <?php echo $total_remaining >= 0 ? '#28a745' : '#dc3545'; ?>;">
                    $<?php echo number_format($total_remaining, 2); ?>
                </div>
                <div class="stat-label">Remaining Budget</div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php elseif ($report_type): ?>
<!-- No Data Message -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-chart-pie"></i> <?php echo $report_title; ?></h3>
    </div>
    <div style="text-align: center; padding: 3rem;">
        <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">
            <i class="fas fa-chart-line"></i>
        </div>
        <h4 style="color: #666; margin-bottom: 0.5rem;">No Data Available</h4>
        <p style="color: #999;">Try adjusting your filters or select a different report type.</p>
    </div>
</div>
<?php endif; ?>

<!-- Report Templates -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Report Templates</h3>
    </div>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; padding: 1.5rem;">
        <a href="?type=user_activity&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" 
           class="card glass-card glass-hover" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(254, 127, 45, 0.1); 
                            display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-users" style="color: #fe7f2d; font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.25rem;">User Activity</h4>
                    <p style="color: #666; font-size: 0.875rem;">Monthly user activity report</p>
                </div>
            </div>
        </a>
        
        <a href="?type=project_progress&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" 
           class="card glass-card glass-hover" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(35, 61, 77, 0.1); 
                            display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-project-diagram" style="color: #233d4d; font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.25rem;">Project Progress</h4>
                    <p style="color: #666; font-size: 0.875rem;">Current month project status</p>
                </div>
            </div>
        </a>
        
        <a href="?type=department_performance&start_date=<?php echo date('Y-m-01', strtotime('-1 month')); ?>&end_date=<?php echo date('Y-m-t', strtotime('-1 month')); ?>" 
           class="card glass-card glass-hover" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(40, 167, 69, 0.1); 
                            display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-chart-line" style="color: #28a745; font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.25rem;">Last Month Performance</h4>
                    <p style="color: #666; font-size: 0.875rem;">Department performance last month</p>
                </div>
            </div>
        </a>
        
        <a href="?type=financial" 
           class="card glass-card glass-hover" style="padding: 1.5rem;">
            <div style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(220, 53, 69, 0.1); 
                            display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-dollar-sign" style="color: #dc3545; font-size: 1.5rem;"></i>
                </div>
                <div>
                    <h4 style="margin-bottom: 0.25rem;">Financial Overview</h4>
                    <p style="color: #666; font-size: 0.875rem;">Budget and expenditure report</p>
                </div>
            </div>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Update form based on report type
function updateForm() {
    const reportType = document.getElementById('report_type').value;
    const departmentGroup = document.getElementById('departmentGroup');
    
    if (reportType === 'department_performance') {
        departmentGroup.style.display = 'block';
    } else {
        departmentGroup.style.display = 'none';
    }
}

// Reset filters
function resetFilters() {
    document.getElementById('reportForm').reset();
    window.location.href = 'reports.php';
}

// Export report
function exportReport() {
    const reportType = document.getElementById('report_type').value;
    if (!reportType) {
        alert('Please select a report type first.');
        return;
    }
    
    // Create export data
    const table = document.querySelector('.glass-table');
    if (!table) {
        alert('No data to export.');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    rows.forEach(row => {
        const rowData = [];
        const cells = row.querySelectorAll('th, td');
        
        cells.forEach(cell => {
            // Remove icons and badges for clean export
            const text = cell.innerText.replace(/\n/g, ' ').replace(/\s+/g, ' ').trim();
            rowData.push(`"${text}"`);
        });
        
        csv.push(rowData.join(','));
    });
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    
    link.setAttribute('href', url);
    link.setAttribute('download', `report_${reportType}_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Initialize chart when report data is available
<?php if (!empty($chart_data)): ?>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    const chartConfig = {
        type: '<?php echo $report_type == 'project_progress' ? 'line' : 'bar'; ?>',
        data: <?php echo json_encode($chart_data); ?>,
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'top',
                },
                title: {
                    display: true,
                    text: '<?php echo $report_title; ?>'
                }
            },
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    };
    
    new Chart(ctx, chartConfig);
});
<?php endif; ?>
</script>

<style>
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