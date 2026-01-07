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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Security::sanitizeInput($_POST['action'] ?? '');
    
    // Validate CSRF token
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        switch ($action) {
            case 'assign_task':
                $user_id = Security::sanitizeInput($_POST['user_id'], 'int');
                $project_id = Security::sanitizeInput($_POST['project_id'], 'int');
                $title = Security::sanitizeInput($_POST['title']);
                $description = Security::sanitizeInput($_POST['description']);
                $priority = Security::sanitizeInput($_POST['priority']);
                $due_date = Security::sanitizeInput($_POST['due_date']);
                
                $stmt = $db->prepare("
                    INSERT INTO tasks (title, description, project_id, assigned_to, assigned_by, priority, due_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $project_id, $user_id, $manager_id, $priority, $due_date]);
                
                $success = "Task assigned successfully.";
                break;
                
            case 'update_team':
                $user_id = Security::sanitizeInput($_POST['user_id'], 'int');
                $department = Security::sanitizeInput($_POST['department']);
                $position = Security::sanitizeInput($_POST['position']);
                
                $stmt = $db->prepare("UPDATE users SET department = ?, position = ? WHERE id = ?");
                $stmt->execute([$department, $position, $user_id]);
                
                $success = "Team member updated successfully.";
                break;
        }
    }
}

// Get my team members
$stmt = $db->prepare("
    SELECT DISTINCT u.*, 
           COUNT(t.id) as task_count,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
           SUM(CASE WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
    FROM users u
    JOIN tasks t ON u.id = t.assigned_to
    JOIN projects p ON t.project_id = p.id
    WHERE p.manager_id = ? AND u.role = 'employee'
    GROUP BY u.id
    ORDER BY u.full_name
");
$stmt->execute([$manager_id]);
$team_members = $stmt->fetchAll();

// Get available employees (not in team)
$stmt = $db->prepare("
    SELECT u.* 
    FROM users u
    WHERE u.role = 'employee' 
    AND u.id NOT IN (
        SELECT DISTINCT t.assigned_to 
        FROM tasks t 
        JOIN projects p ON t.project_id = p.id 
        WHERE p.manager_id = ?
    )
    AND u.status = 'active'
    ORDER BY u.full_name
");
$stmt->execute([$manager_id]);
$available_employees = $stmt->fetchAll();

// Get my projects for task assignment
$stmt = $db->prepare("
    SELECT p.* 
    FROM projects p 
    WHERE p.manager_id = ? 
    AND p.status IN ('planning', 'ongoing')
    ORDER BY p.name
");
$stmt->execute([$manager_id]);
$my_projects = $stmt->fetchAll();

// Get departments for dropdown
$stmt = $db->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

$page_title = "Team Management";
?>

<?php include '../../includes/header.php'; ?>

<!-- Team Management Header -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-user-friends"></i> Team Management</h2>
        <button class="btn btn-primary glass-button" onclick="showAssignTaskModal()">
            <i class="fas fa-tasks"></i> Assign Task
        </button>
    </div>
    <p>Manage your team members, assign tasks, and track performance.</p>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error fade-in">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo Security::escapeOutput($error); ?></span>
    </div>
<?php endif; ?>

<?php if (isset($success)): ?>
    <div class="alert alert-success fade-in">
        <i class="fas fa-check-circle"></i>
        <span><?php echo $success; ?></span>
    </div>
<?php endif; ?>

<!-- Team Statistics -->
<div class="stats-grid">
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo count($team_members); ?></div>
        <div class="stat-label">Team Members</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-user-check" style="color: #28a745;"></i>
            In your team
        </div>
    </div>
    
    <?php 
    // Calculate team statistics
    $total_tasks = 0;
    $completed_tasks = 0;
    $overdue_tasks = 0;
    
    foreach ($team_members as $member) {
        $total_tasks += $member['task_count'];
        $completed_tasks += $member['completed_tasks'];
        $overdue_tasks += $member['overdue_tasks'];
    }
    
    $completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
    ?>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $total_tasks; ?></div>
        <div class="stat-label">Total Tasks</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-check-circle" style="color: #28a745;"></i>
            <?php echo $completed_tasks; ?> completed
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $overdue_tasks; ?></div>
        <div class="stat-label">Overdue Tasks</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-exclamation-triangle" style="color: #dc3545;"></i>
            Need attention
        </div>
    </div>
    
    <div class="card glass-stat fade-in">
        <div class="stat-number"><?php echo $completion_rate; ?>%</div>
        <div class="stat-label">Team Performance</div>
        <div style="font-size: 0.875rem; color: #666; margin-top: 0.5rem;">
            <i class="fas fa-chart-line" style="color: <?php echo $completion_rate >= 80 ? '#28a745' : ($completion_rate >= 60 ? '#ffc107' : '#dc3545'); ?>;"></i>
            Completion rate
        </div>
    </div>
</div>

<!-- Team Members -->
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-users"></i> My Team</h3>
        <div style="display: flex; gap: 0.5rem;">
            <input type="text" id="teamSearch" class="form-control glass-input" placeholder="Search team members..." 
                   style="width: 200px;">
            <button class="btn btn-outline" onclick="exportTeamReport()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    
    <div class="table-container">
        <table class="table glass-table" id="teamTable">
            <thead>
                <tr>
                    <th>Team Member</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Contact</th>
                    <th>Tasks</th>
                    <th>Performance</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($team_members)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem;">
                            <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">
                                <i class="fas fa-users"></i>
                            </div>
                            <h4 style="color: #666; margin-bottom: 0.5rem;">No Team Members</h4>
                            <p style="color: #999;">You don't have any team members assigned yet.</p>
                            <p style="color: #999; font-size: 0.875rem;">Assign tasks to employees to add them to your team.</p>
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
                                <div class="user-avatar" style="width: 40px; height: 40px; background: linear-gradient(90deg, #fe7f2d, #233d4d);">
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
                            <div style="font-size: 0.875rem;">
                                <div><i class="fas fa-envelope"></i> <?php echo Security::escapeOutput($member['email']); ?></div>
                                <div style="margin-top: 0.25rem;">
                                    <i class="fas fa-calendar"></i> 
                                    Joined: <?php echo date('M d, Y', strtotime($member['hire_date'])); ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Total:</span>
                                    <span style="font-weight: 600;"><?php echo $member['task_count']; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Completed:</span>
                                    <span style="color: #28a745; font-weight: 600;"><?php echo $member['completed_tasks']; ?></span>
                                </div>
                                <div style="display: flex; justify-content: space-between;">
                                    <span>Overdue:</span>
                                    <span style="color: #dc3545; font-weight: 600;"><?php echo $member['overdue_tasks']; ?></span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <div style="flex: 1;">
                                    <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                        <div style="width: <?php echo $performance; ?>%; height: 100%; 
                                                    background: <?php echo $performance >= 80 ? '#28a745' : ($performance >= 60 ? '#ffc107' : '#dc3545'); ?>;"></div>
                                    </div>
                                </div>
                                <span style="font-weight: 600; font-size: 0.875rem; min-width: 45px;
                                            color: <?php echo $performance >= 80 ? '#28a745' : ($performance >= 60 ? '#856404' : '#dc3545'); ?>;">
                                    <?php echo $performance; ?>%
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: #666; text-align: center; margin-top: 0.25rem;">
                                Completion rate
                            </div>
                        </td>
                        <td>
                            <div class="table-actions">
                                <button class="btn btn-outline btn-sm" 
                                        onclick="showAssignTaskModal(<?php echo $member['id']; ?>, '<?php echo addslashes($member['full_name']); ?>')"
                                        title="Assign Task">
                                    <i class="fas fa-tasks"></i>
                                </button>
                                <button class="btn btn-outline btn-sm" 
                                        onclick="showUpdateTeamModal(<?php echo $member['id']; ?>, 
                                                '<?php echo addslashes($member['full_name']); ?>',
                                                '<?php echo addslashes($member['department'] ?? ''); ?>',
                                                '<?php echo addslashes($member['position'] ?? ''); ?>')"
                                        title="Edit Details">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="../employee/tasks.php?user_id=<?php echo $member['id']; ?>" 
                                   class="btn btn-outline btn-sm" title="View Tasks">
                                    <i class="fas fa-eye"></i>
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

<!-- Available Employees -->
<?php if (!empty($available_employees)): ?>
<div class="card glass-card fade-in">
    <div class="card-header">
        <h3><i class="fas fa-user-plus"></i> Available Employees</h3>
        <span style="color: #666; font-size: 0.875rem;">
            Employees not yet in your team
        </span>
    </div>
    
    <div class="table-container">
        <table class="table glass-table">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department</th>
                    <th>Position</th>
                    <th>Email</th>
                    <th>Join Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($available_employees as $employee): ?>
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="user-avatar-small" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                                <?php echo strtoupper(substr($employee['full_name'], 0, 1)); ?>
                            </div>
                            <div>
                                <div style="font-weight: 600;"><?php echo Security::escapeOutput($employee['full_name']); ?></div>
                                <div style="font-size: 0.75rem; color: #666;">@<?php echo $employee['username']; ?></div>
                            </div>
                        </div>
                    </td>
                    <td><?php echo Security::escapeOutput($employee['department'] ?? 'N/A'); ?></td>
                    <td><?php echo Security::escapeOutput($employee['position'] ?? 'N/A'); ?></td>
                    <td><?php echo Security::escapeOutput($employee['email']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($employee['hire_date'])); ?></td>
                    <td>
                        <button class="btn btn-primary btn-sm" 
                                onclick="showAssignTaskModal(<?php echo $employee['id']; ?>, '<?php echo addslashes($employee['full_name']); ?>')">
                            <i class="fas fa-tasks"></i> Assign Task
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Modals -->
<!-- Assign Task Modal -->
<div id="assignTaskModal" class="modal" style="display: none;">
    <div class="modal-content glass-modal" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Assign New Task</h3>
            <button class="close-btn" onclick="closeAssignTaskModal()">&times;</button>
        </div>
        <form id="assignTaskForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="assign_task">
            <input type="hidden" name="user_id" id="assignUserId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Assigning to:</label>
                    <div id="assignUserName" style="font-weight: 600; padding: 0.5rem; background: #f8f9fa; border-radius: 8px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="project_id" class="form-label">Project *</label>
                    <select id="project_id" name="project_id" class="form-control glass-input" required>
                        <option value="">Select Project</option>
                        <?php foreach ($my_projects as $project): ?>
                            <option value="<?php echo $project['id']; ?>">
                                <?php echo Security::escapeOutput($project['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="title" class="form-label">Task Title *</label>
                    <input type="text" id="title" name="title" class="form-control glass-input" required>
                </div>
                
                <div class="form-group">
                    <label for="description" class="form-label">Description *</label>
                    <textarea id="description" name="description" class="form-control glass-input" rows="4" required></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label for="priority" class="form-label">Priority *</label>
                        <select id="priority" name="priority" class="form-control glass-input" required>
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="due_date" class="form-label">Due Date *</label>
                        <input type="date" id="due_date" name="due_date" class="form-control glass-input" 
                               value="<?php echo date('Y-m-d', strtotime('+7 days')); ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeAssignTaskModal()">Cancel</button>
                <button type="submit" class="btn btn-primary glass-button">
                    <i class="fas fa-paper-plane"></i> Assign Task
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Update Team Modal -->
<div id="updateTeamModal" class="modal" style="display: none;">
    <div class="modal-content glass-modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Update Team Member</h3>
            <button class="close-btn" onclick="closeUpdateTeamModal()">&times;</button>
        </div>
        <form id="updateTeamForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_team">
            <input type="hidden" name="user_id" id="updateUserId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Team Member:</label>
                    <div id="updateUserName" style="font-weight: 600; padding: 0.5rem; background: #f8f9fa; border-radius: 8px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="department" class="form-label">Department</label>
                    <select id="department" name="department" class="form-control glass-input">
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo Security::escapeOutput($dept); ?>">
                                <?php echo Security::escapeOutput($dept); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="position" class="form-label">Position</label>
                    <input type="text" id="position" name="position" class="form-control glass-input">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeUpdateTeamModal()">Cancel</button>
                <button type="submit" class="btn btn-primary glass-button">
                    <i class="fas fa-save"></i> Update Details
                </button>
            </div>
        </form>
    </div>
</div>

<style>
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
}

.modal-content {
    background: white;
    border-radius: 20px;
    animation: modalFadeIn 0.3s ease;
}

@keyframes modalFadeIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #eee;
}

.modal-body {
    padding: 1.5rem;
}

.modal-footer {
    padding: 1.5rem;
    border-top: 1px solid #eee;
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
}

.close-btn {
    background: none;
    border: none;
    font-size: 1.5rem;
    cursor: pointer;
    color: #666;
}

.close-btn:hover {
    color: #333;
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

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}
</style>

<script>
// Search functionality
document.getElementById('teamSearch').addEventListener('input', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#teamTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Assign Task Modal
function showAssignTaskModal(userId = null, userName = null) {
    const modal = document.getElementById('assignTaskModal');
    const assignUserId = document.getElementById('assignUserId');
    const assignUserName = document.getElementById('assignUserName');
    
    if (userId && userName) {
        assignUserId.value = userId;
        assignUserName.textContent = userName;
    } else {
        assignUserId.value = '';
        assignUserName.textContent = 'Select employee from list';
    }
    
    modal.style.display = 'flex';
}

function closeAssignTaskModal() {
    document.getElementById('assignTaskModal').style.display = 'none';
    document.getElementById('assignTaskForm').reset();
}

// Update Team Modal
function showUpdateTeamModal(userId, userName, department, position) {
    const modal = document.getElementById('updateTeamModal');
    document.getElementById('updateUserId').value = userId;
    document.getElementById('updateUserName').textContent = userName;
    document.getElementById('department').value = department;
    document.getElementById('position').value = position;
    
    modal.style.display = 'flex';
}

function closeUpdateTeamModal() {
    document.getElementById('updateTeamModal').style.display = 'none';
}

// Export team report
function exportTeamReport() {
    const table = document.getElementById('teamTable');
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
    link.setAttribute('download', `team_report_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Close modals when clicking outside
window.onclick = function(event) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    });
}
</script>

<?php include '../../includes/footer.php'; ?>