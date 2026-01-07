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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Security::sanitizeInput($_POST['action'] ?? '');
    
    // Validate CSRF token
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        switch ($action) {
            case 'update_status':
                $task_id = Security::sanitizeInput($_POST['task_id'], 'int');
                $status = Security::sanitizeInput($_POST['status']);
                $progress_notes = Security::sanitizeInput($_POST['progress_notes']);
                
                // Check if task belongs to this employee
                $checkStmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
                $checkStmt->execute([$task_id, $employee_id]);
                
                if ($checkStmt->fetch()) {
                    $update_data = ['status' => $status];
                    
                    if ($status === 'completed') {
                        $update_data['completed_at'] = date('Y-m-d H:i:s');
                    }
                    
                    if ($progress_notes) {
                        // Add progress notes to description
                        $stmt = $db->prepare("SELECT description FROM tasks WHERE id = ?");
                        $stmt->execute([$task_id]);
                        $task = $stmt->fetch();
                        
                        $new_description = $task['description'] . "\n\n--- Progress Update " . date('Y-m-d H:i') . " ---\n" . $progress_notes;
                        $update_data['description'] = $new_description;
                    }
                    
                    // Build update query
                    $set_clause = [];
                    $params = [];
                    foreach ($update_data as $key => $value) {
                        $set_clause[] = "$key = ?";
                        $params[] = $value;
                    }
                    $params[] = $task_id;
                    
                    $stmt = $db->prepare("UPDATE tasks SET " . implode(', ', $set_clause) . " WHERE id = ?");
                    $stmt->execute($params);
                    
                    $success = "Task status updated successfully.";
                } else {
                    $error = "You don't have permission to update this task.";
                }
                break;
                
            case 'add_comment':
                $task_id = Security::sanitizeInput($_POST['task_id'], 'int');
                $comment = Security::sanitizeInput($_POST['comment']);
                
                // Check if task belongs to this employee
                $checkStmt = $db->prepare("SELECT id FROM tasks WHERE id = ? AND assigned_to = ?");
                $checkStmt->execute([$task_id, $employee_id]);
                
                if ($checkStmt->fetch()) {
                    // Add comment to description
                    $stmt = $db->prepare("SELECT description FROM tasks WHERE id = ?");
                    $stmt->execute([$task_id]);
                    $task = $stmt->fetch();
                    
                    $new_description = $task['description'] . "\n\n--- Comment by " . $_SESSION['full_name'] . " on " . date('Y-m-d H:i') . " ---\n" . $comment;
                    
                    $stmt = $db->prepare("UPDATE tasks SET description = ? WHERE id = ?");
                    $stmt->execute([$new_description, $task_id]);
                    
                    $success = "Comment added successfully.";
                } else {
                    $error = "You don't have permission to comment on this task.";
                }
                break;
        }
    }
}

// Get action from URL
$action = Security::sanitizeInput($_GET['action'] ?? 'list');
$task_id = Security::sanitizeInput($_GET['id'] ?? 0, 'int');
$user_id = Security::sanitizeInput($_GET['user_id'] ?? 0, 'int'); // For manager viewing specific user

// Get filter parameters
$status_filter = Security::sanitizeInput($_GET['status'] ?? 'all');
$priority_filter = Security::sanitizeInput($_GET['priority'] ?? 'all');
$project_filter = Security::sanitizeInput($_GET['project_id'] ?? 'all');

// Get my tasks with filters
$where_conditions = ["t.assigned_to = ?"];
$params = [$employee_id];

if ($user_id > 0 && Auth::hasRole('manager')) {
    // Manager viewing specific employee's tasks
    $where_conditions = ["t.assigned_to = ?"];
    $params = [$user_id];
}

if ($status_filter !== 'all') {
    $where_conditions[] = "t.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter !== 'all') {
    $where_conditions[] = "t.priority = ?";
    $params[] = $priority_filter;
}

if ($project_filter !== 'all') {
    $where_conditions[] = "t.project_id = ?";
    $params[] = $project_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $db->prepare("
    SELECT t.*, p.name as project_name, u.full_name as assigned_by_name, u2.full_name as assigned_to_name
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    JOIN users u ON t.assigned_by = u.id
    JOIN users u2 ON t.assigned_to = u2.id
    $where_clause
    ORDER BY 
        CASE 
            WHEN t.due_date < CURDATE() AND t.status != 'completed' THEN 1
            WHEN t.priority = 'urgent' THEN 2
            WHEN t.priority = 'high' THEN 3
            WHEN t.priority = 'medium' THEN 4
            ELSE 5
        END,
        t.due_date ASC
");
$stmt->execute($params);
$tasks = $stmt->fetchAll();

// Get specific task for view
$task = null;
if ($task_id > 0) {
    $stmt = $db->prepare("
        SELECT t.*, p.name as project_name, u.full_name as assigned_by_name, u2.full_name as assigned_to_name
        FROM tasks t
        JOIN projects p ON t.project_id = p.id
        JOIN users u ON t.assigned_by = u.id
        JOIN users u2 ON t.assigned_to = u2.id
        WHERE t.id = ? AND t.assigned_to = ?
    ");
    $stmt->execute([$task_id, $employee_id]);
    $task = $stmt->fetch();
    
    if (!$task && in_array($action, ['view', 'edit'])) {
        header('Location: tasks.php');
        exit();
    }
}

// Get projects for filter
$stmt = $db->prepare("
    SELECT DISTINCT p.id, p.name 
    FROM tasks t
    JOIN projects p ON t.project_id = p.id
    WHERE t.assigned_to = ?
    ORDER BY p.name
");
$stmt->execute([$employee_id]);
$projects = $stmt->fetchAll();

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

$page_title = "My Tasks";
?>

<?php include '../../includes/header.php'; ?>

<?php if ($action == 'list' || $action == ''): ?>
<!-- Task List View -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-tasks"></i> My Tasks</h2>
        <div style="display: flex; gap: 1rem; align-items: center;">
            <button class="btn btn-outline" onclick="showTaskFilters()">
                <i class="fas fa-filter"></i> Filters
            </button>
            <?php if (Auth::hasRole('manager') && $user_id > 0): ?>
                <a href="tasks.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to My Tasks
                </a>
            <?php endif; ?>
        </div>
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
    
    <!-- Task Filters (Initially Hidden) -->
    <div id="taskFilters" style="padding: 1.5rem; border-top: 1px solid #eee; display: none;">
        <form method="GET" action="" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
            <div class="form-group">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-control glass-input">
                    <option value="all" <?php echo $status_filter == 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="in_progress" <?php echo $status_filter == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="deferred" <?php echo $status_filter == 'deferred' ? 'selected' : ''; ?>>Deferred</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="priority" class="form-label">Priority</label>
                <select id="priority" name="priority" class="form-control glass-input">
                    <option value="all" <?php echo $priority_filter == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                    <option value="urgent" <?php echo $priority_filter == 'urgent' ? 'selected' : ''; ?>>Urgent</option>
                    <option value="high" <?php echo $priority_filter == 'high' ? 'selected' : ''; ?>>High</option>
                    <option value="medium" <?php echo $priority_filter == 'medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="low" <?php echo $priority_filter == 'low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="project_id" class="form-label">Project</label>
                <select id="project_id" name="project_id" class="form-control glass-input">
                    <option value="all" <?php echo $project_filter == 'all' ? 'selected' : ''; ?>>All Projects</option>
                    <?php foreach ($projects as $project): ?>
                        <option value="<?php echo $project['id']; ?>" 
                                <?php echo $project_filter == $project['id'] ? 'selected' : ''; ?>>
                            <?php echo Security::escapeOutput($project['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group" style="align-self: end;">
                <div style="display: flex; gap: 0.5rem;">
                    <button type="submit" class="btn btn-primary glass-button">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <a href="tasks.php" class="btn btn-outline">Clear</a>
                </div>
            </div>
        </form>
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
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 3rem;">
                            <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h4 style="color: #666; margin-bottom: 0.5rem;">No Tasks Found</h4>
                            <p style="color: #999;"><?php echo $status_filter !== 'all' ? 'Try changing your filters.' : 'You haven\'t been assigned any tasks yet.'; ?></p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tasks as $tsk): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo Security::escapeOutput($tsk['title']); ?></div>
                            <div style="font-size: 0.875rem; color: #666;">
                                <?php echo Security::escapeOutput(substr($tsk['description'], 0, 50)); ?>...
                            </div>
                        </td>
                        <td><?php echo Security::escapeOutput($tsk['project_name']); ?></td>
                        <td>
                            <span class="badge <?php 
                                switch($tsk['priority']) {
                                    case 'urgent': echo 'badge-danger'; break;
                                    case 'high': echo 'badge-warning'; break;
                                    case 'medium': echo 'badge-info'; break;
                                    case 'low': echo 'badge-secondary'; break;
                                    default: echo 'badge-light';
                                }
                            ?>">
                                <?php echo ucfirst($tsk['priority']); ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge <?php 
                                switch($tsk['status']) {
                                    case 'completed': echo 'badge-success'; break;
                                    case 'in_progress': echo 'badge-primary'; break;
                                    case 'pending': echo 'badge-warning'; break;
                                    case 'deferred': echo 'badge-danger'; break;
                                    default: echo 'badge-secondary';
                                }
                            ?>">
                                <?php echo ucwords(str_replace('_', ' ', $tsk['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem;">
                                <?php echo date('M d, Y', strtotime($tsk['due_date'])); ?>
                                <?php if (strtotime($tsk['due_date']) < time() && $tsk['status'] != 'completed'): ?>
                                    <div style="font-size: 0.75rem; color: #dc3545;">
                                        <i class="fas fa-exclamation-circle"></i> Overdue
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?action=view&id=<?php echo $tsk['id']; ?>" 
                                   class="btn btn-outline btn-sm" title="View Task">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <?php if ($tsk['status'] != 'completed'): ?>
                                    <button class="btn btn-outline btn-sm" 
                                            onclick="showUpdateStatusModal(<?php echo $tsk['id']; ?>, '<?php echo addslashes($tsk['title']); ?>', '<?php echo $tsk['status']; ?>')"
                                            title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
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

<?php elseif ($action == 'view' && $task): ?>
<!-- View Task Details -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-eye"></i> Task Details</h2>
        <div>
            <a href="tasks.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back to Tasks
            </a>
            <?php if ($task['status'] != 'completed'): ?>
                <button class="btn btn-primary glass-button" onclick="showUpdateStatusModal(<?php echo $task_id; ?>, '<?php echo addslashes($task['title']); ?>', '<?php echo $task['status']; ?>')">
                    <i class="fas fa-edit"></i> Update Status
                </button>
            <?php endif; ?>
        </div>
    </div>
    
    <div style="padding: 2rem;">
        <!-- Task Header -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h3 style="color: #233d4d; margin-bottom: 0.5rem;"><?php echo Security::escapeOutput($task['title']); ?></h3>
                <div style="display: flex; gap: 2rem; margin-top: 1.5rem;">
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Project</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['project_name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Assigned By</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['assigned_by_name']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Assigned To</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['assigned_to_name']); ?></div>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="card glass-card" style="padding: 1.5rem;">
                    <h4 style="margin-bottom: 1rem;">Task Info</h4>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">Status</div>
                            <div>
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
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">Priority</div>
                            <div>
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
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">Due Date</div>
                            <div style="font-weight: 600;">
                                <?php echo date('F d, Y', strtotime($task['due_date'])); ?>
                                <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'completed'): ?>
                                    <div style="font-size: 0.75rem; color: #dc3545;">
                                        <i class="fas fa-exclamation-circle"></i> Overdue
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($task['completed_at']): ?>
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">Completed On</div>
                            <div style="font-weight: 600; color: #28a745;">
                                <?php echo date('F d, Y H:i', strtotime($task['completed_at'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Task Description -->
        <div class="card glass-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3>Description</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="white-space: pre-line; line-height: 1.6;">
                    <?php echo nl2br(Security::escapeOutput($task['description'])); ?>
                </div>
            </div>
        </div>
        
        <!-- Add Comment -->
        <div class="card glass-card">
            <div class="card-header">
                <h3>Add Comment</h3>
            </div>
            <div style="padding: 1.5rem;">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="add_comment">
                    <input type="hidden" name="task_id" value="<?php echo $task_id; ?>">
                    
                    <div class="form-group">
                        <label for="comment" class="form-label">Your Comment</label>
                        <textarea id="comment" name="comment" class="form-control glass-input" rows="3" required></textarea>
                    </div>
                    
                    <div style="text-align: right;">
                        <button type="submit" class="btn btn-primary glass-button">
                            <i class="fas fa-paper-plane"></i> Add Comment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Update Status Modal -->
<div id="updateStatusModal" class="modal" style="display: none;">
    <div class="modal-content glass-modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Update Task Status</h3>
            <button class="close-btn" onclick="closeUpdateStatusModal()">&times;</button>
        </div>
        <form id="updateStatusForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="task_id" id="updateTaskId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Task:</label>
                    <div id="updateTaskTitle" style="font-weight: 600; padding: 0.5rem; background: #f8f9fa; border-radius: 8px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="status" class="form-label">Status *</label>
                    <select id="status" name="status" class="form-control glass-input" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="deferred">Deferred</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="progress_notes" class="form-label">Progress Notes</label>
                    <textarea id="progress_notes" name="progress_notes" class="form-control glass-input" rows="3"></textarea>
                    <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                        <i class="fas fa-info-circle"></i> This will be added to the task description
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeUpdateStatusModal()">Cancel</button>
                <button type="submit" class="btn btn-primary glass-button">
                    <i class="fas fa-save"></i> Update Status
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
</style>

<script>
// Show/hide task filters
function showTaskFilters() {
    const filters = document.getElementById('taskFilters');
    filters.style.display = filters.style.display === 'none' ? 'block' : 'none';
}

// Update Status Modal
function showUpdateStatusModal(taskId, taskTitle, currentStatus) {
    const modal = document.getElementById('updateStatusModal');
    document.getElementById('updateTaskId').value = taskId;
    document.getElementById('updateTaskTitle').textContent = taskTitle;
    document.getElementById('status').value = currentStatus;
    
    modal.style.display = 'flex';
}

function closeUpdateStatusModal() {
    document.getElementById('updateStatusModal').style.display = 'none';
    document.getElementById('updateStatusForm').reset();
}

// Close modal when clicking outside
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