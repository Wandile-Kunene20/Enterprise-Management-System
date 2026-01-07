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
            case 'create':
                $name = Security::sanitizeInput($_POST['name']);
                $description = Security::sanitizeInput($_POST['description']);
                $department_id = Security::sanitizeInput($_POST['department_id'], 'int');
                $start_date = Security::sanitizeInput($_POST['start_date']);
                $end_date = Security::sanitizeInput($_POST['end_date']);
                $status = Security::sanitizeInput($_POST['status']);
                $budget = Security::sanitizeInput($_POST['budget'], 'float');
                
                $stmt = $db->prepare("
                    INSERT INTO projects (name, description, manager_id, department_id, start_date, end_date, status, budget) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$name, $description, $manager_id, $department_id, $start_date, $end_date, $status, $budget]);
                
                $project_id = $db->lastInsertId();
                $success = "Project created successfully. <a href='?action=view&id=$project_id'>View Project</a>";
                break;
                
            case 'update':
                $project_id = Security::sanitizeInput($_POST['project_id'], 'int');
                $name = Security::sanitizeInput($_POST['name']);
                $description = Security::sanitizeInput($_POST['description']);
                $department_id = Security::sanitizeInput($_POST['department_id'], 'int');
                $start_date = Security::sanitizeInput($_POST['start_date']);
                $end_date = Security::sanitizeInput($_POST['end_date']);
                $status = Security::sanitizeInput($_POST['status']);
                $budget = Security::sanitizeInput($_POST['budget'], 'float');
                
                // Check if project belongs to this manager
                $checkStmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND manager_id = ?");
                $checkStmt->execute([$project_id, $manager_id]);
                
                if ($checkStmt->fetch()) {
                    $stmt = $db->prepare("
                        UPDATE projects 
                        SET name = ?, description = ?, department_id = ?, start_date = ?, end_date = ?, status = ?, budget = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$name, $description, $department_id, $start_date, $end_date, $status, $budget, $project_id]);
                    $success = "Project updated successfully.";
                } else {
                    $error = "You don't have permission to update this project.";
                }
                break;
                
            case 'delete':
                $project_id = Security::sanitizeInput($_POST['project_id'], 'int');
                
                // Check if project belongs to this manager
                $checkStmt = $db->prepare("SELECT id FROM projects WHERE id = ? AND manager_id = ?");
                $checkStmt->execute([$project_id, $manager_id]);
                
                if ($checkStmt->fetch()) {
                    $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
                    $stmt->execute([$project_id]);
                    $success = "Project deleted successfully.";
                } else {
                    $error = "You don't have permission to delete this project.";
                }
                break;
        }
    }
}

// Get action from URL
$action = Security::sanitizeInput($_GET['action'] ?? 'list');
$project_id = Security::sanitizeInput($_GET['id'] ?? 0, 'int');

// Get departments for dropdown
$stmt = $db->prepare("SELECT id, name FROM departments ORDER BY name");
$stmt->execute();
$departments = $stmt->fetchAll();

// Get my projects
$stmt = $db->prepare("
    SELECT p.*, d.name as department_name,
           COUNT(t.id) as total_tasks,
           SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks
    FROM projects p
    LEFT JOIN departments d ON p.department_id = d.id
    LEFT JOIN tasks t ON p.id = t.project_id
    WHERE p.manager_id = ?
    GROUP BY p.id
    ORDER BY p.created_at DESC
");
$stmt->execute([$manager_id]);
$projects = $stmt->fetchAll();

// Get specific project for view/edit
$project = null;
if ($project_id > 0) {
    $stmt = $db->prepare("
        SELECT p.*, d.name as department_name, u.full_name as manager_name
        FROM projects p
        LEFT JOIN departments d ON p.department_id = d.id
        LEFT JOIN users u ON p.manager_id = u.id
        WHERE p.id = ? AND p.manager_id = ?
    ");
    $stmt->execute([$project_id, $manager_id]);
    $project = $stmt->fetch();
    
    if (!$project && in_array($action, ['view', 'edit'])) {
        header('Location: projects.php');
        exit();
    }
}

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

$page_title = "Manage Projects";
?>

<?php include '../../includes/header.php'; ?>

<?php if ($action == 'list' || $action == ''): ?>
<!-- Project List View -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-project-diagram"></i> My Projects</h2>
        <a href="?action=create" class="btn btn-primary glass-button">
            <i class="fas fa-plus"></i> New Project
        </a>
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
    
    <div class="table-container">
        <table class="table glass-table">
            <thead>
                <tr>
                    <th>Project</th>
                    <th>Department</th>
                    <th>Status</th>
                    <th>Timeline</th>
                    <th>Progress</th>
                    <th>Budget</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($projects)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 3rem;">
                            <div style="font-size: 4rem; color: #ddd; margin-bottom: 1rem;">
                                <i class="fas fa-project-diagram"></i>
                            </div>
                            <h4 style="color: #666; margin-bottom: 0.5rem;">No Projects Found</h4>
                            <p style="color: #999;">You haven't created any projects yet.</p>
                            <a href="?action=create" class="btn btn-primary glass-button">
                                <i class="fas fa-plus"></i> Create Your First Project
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($projects as $proj): ?>
                    <?php 
                    $progress = $proj['total_tasks'] > 0 ? 
                        round(($proj['completed_tasks'] / $proj['total_tasks']) * 100) : 0;
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?php echo Security::escapeOutput($proj['name']); ?></div>
                            <div style="font-size: 0.875rem; color: #666;">
                                <?php echo Security::escapeOutput(substr($proj['description'], 0, 50)); ?>...
                            </div>
                        </td>
                        <td><?php echo Security::escapeOutput($proj['department_name'] ?? 'N/A'); ?></td>
                        <td>
                            <span class="badge <?php 
                                switch($proj['status']) {
                                    case 'ongoing': echo 'badge-success'; break;
                                    case 'planning': echo 'badge-info'; break;
                                    case 'completed': echo 'badge-primary'; break;
                                    case 'on_hold': echo 'badge-warning'; break;
                                    default: echo 'badge-secondary';
                                }
                            ?>">
                                <?php echo ucwords(str_replace('_', ' ', $proj['status'])); ?>
                            </span>
                        </td>
                        <td>
                            <div style="font-size: 0.875rem;">
                                <div><i class="fas fa-play-circle" style="color: #28a745;"></i> <?php echo date('M d, Y', strtotime($proj['start_date'])); ?></div>
                                <div><i class="fas fa-flag-checkered" style="color: #fe7f2d;"></i> <?php echo date('M d, Y', strtotime($proj['end_date'])); ?></div>
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
                                <?php echo $proj['completed_tasks']; ?> of <?php echo $proj['total_tasks']; ?> tasks
                            </div>
                        </td>
                        <td>
                            <?php if ($proj['budget']): ?>
                                <div style="font-weight: 600; color: #233d4d;">
                                    $<?php echo number_format($proj['budget'], 2); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #666;">Not set</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="table-actions">
                                <a href="?action=view&id=<?php echo $proj['id']; ?>" 
                                   class="btn btn-outline btn-sm" title="View Project">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="?action=edit&id=<?php echo $proj['id']; ?>" 
                                   class="btn btn-outline btn-sm" title="Edit Project">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button class="btn btn-danger btn-sm" 
                                        onclick="confirmDelete(<?php echo $proj['id']; ?>, '<?php echo addslashes($proj['name']); ?>')"
                                        title="Delete Project">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal" style="display: none;">
    <div class="modal-content glass-modal" style="max-width: 400px;">
        <div class="modal-header">
            <h3>Confirm Delete</h3>
            <button class="close-btn" onclick="closeModal()">&times;</button>
        </div>
        <form id="deleteForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="project_id" id="deleteProjectId" value="">
            
            <div class="modal-body">
                <p>Are you sure you want to delete project <strong id="deleteProjectName"></strong>?</p>
                <p class="text-danger">This will also delete all associated tasks.</p>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn btn-danger glass-button">
                    <i class="fas fa-trash"></i> Delete Project
                </button>
            </div>
        </form>
    </div>
</div>

<?php elseif ($action == 'create' || $action == 'edit'): ?>
<!-- Create/Edit Project Form -->
<div class="card glass-card fade-in" style="margin-top: 2rem; max-width: 800px; margin-left: auto; margin-right: auto;">
    <div class="card-header">
        <h2>
            <i class="fas fa-<?php echo $action == 'create' ? 'plus' : 'edit'; ?>"></i>
            <?php echo $action == 'create' ? 'Create New Project' : 'Edit Project'; ?>
        </h2>
        <a href="projects.php" class="btn btn-outline">
            <i class="fas fa-arrow-left"></i> Back to Projects
        </a>
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
    
    <form method="POST" action="">
        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
        <input type="hidden" name="action" value="<?php echo $action; ?>">
        <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
        
        <div style="padding: 2rem;">
            <div class="form-group">
                <label for="name" class="form-label">Project Name *</label>
                <input type="text" id="name" name="name" class="form-control glass-input" 
                       value="<?php echo $project['name'] ?? ''; ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description" class="form-label">Description *</label>
                <textarea id="description" name="description" class="form-control glass-input" 
                          rows="4" required><?php echo $project['description'] ?? ''; ?></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label for="department_id" class="form-label">Department *</label>
                    <select id="department_id" name="department_id" class="form-control glass-input" required>
                        <option value="">Select Department</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['id']; ?>" 
                                    <?php echo ($project['department_id'] ?? '') == $dept['id'] ? 'selected' : ''; ?>>
                                <?php echo Security::escapeOutput($dept['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="status" class="form-label">Status *</label>
                    <select id="status" name="status" class="form-control glass-input" required>
                        <option value="planning" <?php echo ($project['status'] ?? '') == 'planning' ? 'selected' : ''; ?>>Planning</option>
                        <option value="ongoing" <?php echo ($project['status'] ?? '') == 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                        <option value="completed" <?php echo ($project['status'] ?? '') == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="on_hold" <?php echo ($project['status'] ?? '') == 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                    </select>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group">
                    <label for="start_date" class="form-label">Start Date *</label>
                    <input type="date" id="start_date" name="start_date" class="form-control glass-input" 
                           value="<?php echo $project['start_date'] ?? date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="end_date" class="form-label">End Date *</label>
                    <input type="date" id="end_date" name="end_date" class="form-control glass-input" 
                           value="<?php echo $project['end_date'] ?? date('Y-m-d', strtotime('+1 month')); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="budget" class="form-label">Budget ($)</label>
                <input type="number" id="budget" name="budget" class="form-control glass-input" 
                       value="<?php echo $project['budget'] ?? ''; ?>" step="0.01" min="0">
            </div>
        </div>
        
        <div style="padding: 1.5rem; border-top: 1px solid #eee; display: flex; justify-content: flex-end; gap: 1rem;">
            <a href="projects.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary glass-button">
                <i class="fas fa-save"></i> 
                <?php echo $action == 'create' ? 'Create Project' : 'Update Project'; ?>
            </button>
        </div>
    </form>
</div>

<?php elseif ($action == 'view' && $project): ?>
<!-- View Project Details -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-eye"></i> Project Details</h2>
        <div>
            <a href="?action=edit&id=<?php echo $project_id; ?>" class="btn btn-outline">
                <i class="fas fa-edit"></i> Edit
            </a>
            <a href="projects.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    
    <div style="padding: 2rem;">
        <!-- Project Info -->
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
            <div>
                <h3 style="color: #233d4d; margin-bottom: 0.5rem;"><?php echo Security::escapeOutput($project['name']); ?></h3>
                <p style="color: #666; line-height: 1.6;"><?php echo nl2br(Security::escapeOutput($project['description'])); ?></p>
                
                <div style="display: flex; gap: 2rem; margin-top: 1.5rem;">
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Department</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($project['department_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Manager</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($project['manager_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Status</div>
                        <div>
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
                        </div>
                    </div>
                </div>
            </div>
            
            <div>
                <div class="card glass-card" style="padding: 1.5rem;">
                    <h4 style="margin-bottom: 1rem;">Project Timeline</h4>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">Start Date</div>
                            <div style="font-weight: 600; color: #28a745;">
                                <i class="fas fa-play-circle"></i>
                                <?php echo date('F d, Y', strtotime($project['start_date'])); ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">End Date</div>
                            <div style="font-weight: 600; color: #fe7f2d;">
                                <i class="fas fa-flag-checkered"></i>
                                <?php echo date('F d, Y', strtotime($project['end_date'])); ?>
                            </div>
                        </div>
                        <div>
                            <div style="font-size: 0.875rem; color: #999;">Days Remaining</div>
                            <div style="font-weight: 600;">
                                <?php 
                                $days_remaining = ceil((strtotime($project['end_date']) - time()) / (60 * 60 * 24));
                                $color = $days_remaining > 30 ? '#28a745' : ($days_remaining > 7 ? '#ffc107' : '#dc3545');
                                ?>
                                <span style="color: <?php echo $color; ?>;">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo $days_remaining > 0 ? $days_remaining . ' days' : 'Overdue'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Project Stats -->
        <?php 
        // Get project statistics
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress_tasks,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_tasks,
                SUM(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 ELSE 0 END) as overdue_tasks
            FROM tasks 
            WHERE project_id = ?
        ");
        $stmt->execute([$project_id]);
        $stats = $stmt->fetch();
        
        $progress = $stats['total_tasks'] > 0 ? 
            round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0;
        ?>
        
        <div class="card glass-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3>Project Progress</h3>
                <div style="font-weight: 600; font-size: 1.25rem; color: #fe7f2d;">
                    <?php echo $progress; ?>%
                </div>
            </div>
            <div style="padding: 1.5rem;">
                <div style="height: 12px; background: #e0e0e0; border-radius: 6px; overflow: hidden; margin-bottom: 1rem;">
                    <div style="width: <?php echo $progress; ?>%; height: 100%; background: linear-gradient(90deg, #fe7f2d, #ff9a52);"></div>
                </div>
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem;">
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: 600; color: #233d4d;"><?php echo $stats['total_tasks']; ?></div>
                        <div style="font-size: 0.875rem; color: #666;">Total Tasks</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: 600; color: #28a745;"><?php echo $stats['completed_tasks']; ?></div>
                        <div style="font-size: 0.875rem; color: #666;">Completed</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: 600; color: #17a2b8;"><?php echo $stats['in_progress_tasks']; ?></div>
                        <div style="font-size: 0.875rem; color: #666;">In Progress</div>
                    </div>
                    <div class="text-center">
                        <div style="font-size: 2rem; font-weight: 600; color: <?php echo $stats['overdue_tasks'] > 0 ? '#dc3545' : '#ffc107'; ?>;">
                            <?php echo $stats['overdue_tasks']; ?>
                        </div>
                        <div style="font-size: 0.875rem; color: #666;">Overdue</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Project Tasks -->
        <div class="card glass-card">
            <div class="card-header">
                <h3>Project Tasks</h3>
                <a href="../employee/tasks.php?action=create&project_id=<?php echo $project_id; ?>" 
                   class="btn btn-primary glass-button">
                    <i class="fas fa-plus"></i> Add Task
                </a>
            </div>
            <div class="table-container">
                <?php 
                // Get project tasks
                $stmt = $db->prepare("
                    SELECT t.*, u.full_name as assigned_to_name, u2.full_name as assigned_by_name
                    FROM tasks t
                    LEFT JOIN users u ON t.assigned_to = u.id
                    LEFT JOIN users u2 ON t.assigned_by = u2.id
                    WHERE t.project_id = ?
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
                $stmt->execute([$project_id]);
                $tasks = $stmt->fetchAll();
                ?>
                
                <table class="table glass-table">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Assigned To</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Due Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 2rem;">
                                    <div style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;">
                                        <i class="fas fa-tasks"></i>
                                    </div>
                                    <h4 style="color: #666; margin-bottom: 0.5rem;">No Tasks Found</h4>
                                    <p style="color: #999;">This project doesn't have any tasks yet.</p>
                                    <a href="../employee/tasks.php?action=create&project_id=<?php echo $project_id; ?>" 
                                       class="btn btn-primary glass-button">
                                        <i class="fas fa-plus"></i> Add First Task
                                    </a>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?php echo Security::escapeOutput($task['title']); ?></div>
                                    <div style="font-size: 0.875rem; color: #666;">
                                        <?php echo Security::escapeOutput(substr($task['description'], 0, 50)); ?>...
                                    </div>
                                </td>
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
                                        <?php echo date('M d, Y', strtotime($task['due_date'])); ?>
                                        <?php if (strtotime($task['due_date']) < time() && $task['status'] != 'completed'): ?>
                                            <div style="font-size: 0.75rem; color: #dc3545;">
                                                <i class="fas fa-exclamation-circle"></i> Overdue
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <a href="../employee/tasks.php?action=view&id=<?php echo $task['id']; ?>" 
                                           class="btn btn-outline btn-sm" title="View Task">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="../employee/tasks.php?action=edit&id=<?php echo $task['id']; ?>" 
                                           class="btn btn-outline btn-sm" title="Edit Task">
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
    </div>
</div>
<?php endif; ?>

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

.text-center {
    text-align: center;
}
</style>

<script>
function confirmDelete(projectId, projectName) {
    document.getElementById('deleteProjectId').value = projectId;
    document.getElementById('deleteProjectName').textContent = projectName;
    document.getElementById('deleteModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}
</script>

<?php include '../../includes/footer.php'; ?>