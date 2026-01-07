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

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = Security::sanitizeInput($_POST['action'] ?? '');
    
    // Validate CSRF token
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        switch ($action) {
            case 'create':
                $username = Security::sanitizeInput($_POST['username']);
                $email = Security::sanitizeInput($_POST['email'], 'email');
                $full_name = Security::sanitizeInput($_POST['full_name']);
                $role = Security::sanitizeInput($_POST['role']);
                $department = Security::sanitizeInput($_POST['department']);
                $position = Security::sanitizeInput($_POST['position']);
                
                // Generate random password
                $password = bin2hex(random_bytes(8)); // 16 character password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Check if username or email already exists
                $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$username, $email]);
                
                if ($stmt->fetch()) {
                    $error = 'Username or email already exists.';
                } else {
                    $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role, department, position, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE())");
                    $stmt->execute([$username, $password_hash, $email, $full_name, $role, $department, $position]);
                    
                    $success = "User created successfully. Temporary password: <strong>$password</strong>";
                }
                break;
                
            case 'update':
                $user_id = Security::sanitizeInput($_POST['user_id'], 'int');
                $email = Security::sanitizeInput($_POST['email'], 'email');
                $full_name = Security::sanitizeInput($_POST['full_name']);
                $role = Security::sanitizeInput($_POST['role']);
                $department = Security::sanitizeInput($_POST['department']);
                $position = Security::sanitizeInput($_POST['position']);
                $status = Security::sanitizeInput($_POST['status']);
                
                $stmt = $db->prepare("UPDATE users SET email = ?, full_name = ?, role = ?, department = ?, position = ?, status = ? WHERE id = ?");
                $stmt->execute([$email, $full_name, $role, $department, $position, $status, $user_id]);
                
                $success = "User updated successfully.";
                break;
                
            case 'delete':
                $user_id = Security::sanitizeInput($_POST['user_id'], 'int');
                
                // Prevent deleting own account
                if ($user_id == $_SESSION['user_id']) {
                    $error = "You cannot delete your own account.";
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    
                    $success = "User deleted successfully.";
                }
                break;
                
            case 'reset_password':
                $user_id = Security::sanitizeInput($_POST['user_id'], 'int');
                $new_password = bin2hex(random_bytes(8));
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$password_hash, $user_id]);
                
                $success = "Password reset successfully. New password: <strong>$new_password</strong>";
                break;
        }
    }
}

// Get all users
$stmt = $db->prepare("SELECT * FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();

// Get departments for dropdown
$stmt = $db->prepare("SELECT DISTINCT department FROM users WHERE department IS NOT NULL ORDER BY department");
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

// Set page title
$page_title = "User Management - Admin";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <link rel="stylesheet" href="../../css/glass.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include '../../includes/header.php'; ?>
    
    <main class="dashboard">
        <div class="container">
            <!-- Page Header -->
            <div class="card glass-card fade-in" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2><i class="fas fa-users"></i> User Management</h2>
                    <button class="btn btn-primary glass-button" onclick="showUserModal('create')">
                        <i class="fas fa-user-plus"></i> Add New User
                    </button>
                </div>
                <p>Manage system users, their roles, and permissions.</p>
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
            
            <!-- Users Table -->
            <div class="card glass-card fade-in">
                <div class="card-header">
                    <h3><i class="fas fa-list"></i> All Users</h3>
                    <div class="table-search-container">
                        <input type="text" id="userSearch" class="form-control glass-input" placeholder="Search users...">
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table glass-table" id="usersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Full Name</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-small" style="background: linear-gradient(90deg, #fe7f2d, #233d4d);">
                                                <?php echo strtoupper(substr($user['full_name'], 0, 1)); ?>
                                            </div>
                                            <span><?php echo Security::escapeOutput($user['username']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo Security::escapeOutput($user['full_name']); ?></td>
                                    <td><?php echo Security::escapeOutput($user['email']); ?></td>
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
                                    <td>
                                        <div class="table-actions">
                                            <button class="btn btn-outline btn-sm" 
                                                    onclick="showUserModal('edit', <?php echo $user['id']; ?>)"
                                                    title="Edit User">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline btn-sm" 
                                                    onclick="resetPassword(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')"
                                                    title="Reset Password">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo $user['username']; ?>')"
                                                        title="Delete User">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!-- User Modal -->
    <div id="userModal" class="modal" style="display: none;">
        <div class="modal-content glass-modal" style="max-width: 500px;">
            <div class="modal-header">
                <h3 id="modalTitle">Add New User</h3>
                <button class="close-btn" onclick="closeUserModal()">&times;</button>
            </div>
            <form id="userForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" id="formAction" value="create">
                <input type="hidden" name="user_id" id="userId" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label for="username" class="form-label">Username *</label>
                        <input type="text" id="username" name="username" class="form-control glass-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" class="form-label">Email *</label>
                        <input type="email" id="email" name="email" class="form-control glass-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="full_name" class="form-label">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control glass-input" required>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="role" class="form-label">Role *</label>
                            <select id="role" name="role" class="form-control glass-input" required>
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status" class="form-label">Status *</label>
                            <select id="status" name="status" class="form-control glass-input" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
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
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeUserModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary glass-button">
                        <i class="fas fa-save"></i> Save User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal" style="display: none;">
        <div class="modal-content glass-modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Confirm Delete</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form id="deleteForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="deleteUserId" value="">
                
                <div class="modal-body">
                    <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                    <p class="text-danger">This action cannot be undone.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger glass-button">
                        <i class="fas fa-trash"></i> Delete User
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Password Reset Modal -->
    <div id="passwordModal" class="modal" style="display: none;">
        <div class="modal-content glass-modal" style="max-width: 400px;">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button class="close-btn" onclick="closePasswordModal()">&times;</button>
            </div>
            <form id="passwordForm" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="passwordUserId" value="">
                
                <div class="modal-body">
                    <p>Are you sure you want to reset password for user <strong id="passwordUserName"></strong>?</p>
                    <p>A new temporary password will be generated and shown on screen.</p>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closePasswordModal()">Cancel</button>
                    <button type="submit" class="btn btn-warning glass-button">
                        <i class="fas fa-key"></i> Reset Password
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
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
        }
        
        .table-search-container {
            width: 300px;
        }
    </style>
    
    <script>
        // User modal functions
        function showUserModal(action, userId = null) {
            const modal = document.getElementById('userModal');
            const form = document.getElementById('userForm');
            const title = document.getElementById('modalTitle');
            
            if (action === 'create') {
                title.textContent = 'Add New User';
                document.getElementById('formAction').value = 'create';
                form.reset();
                document.getElementById('status').value = 'active';
                document.getElementById('role').value = 'employee';
            } else if (action === 'edit' && userId) {
                title.textContent = 'Edit User';
                document.getElementById('formAction').value = 'update';
                document.getElementById('userId').value = userId;
                
                // Fetch user data and populate form
                fetch(`get_user.php?id=${userId}`)
                    .then(response => response.json())
                    .then(user => {
                        document.getElementById('username').value = user.username;
                        document.getElementById('email').value = user.email;
                        document.getElementById('full_name').value = user.full_name;
                        document.getElementById('role').value = user.role;
                        document.getElementById('status').value = user.status;
                        document.getElementById('department').value = user.department || '';
                        document.getElementById('position').value = user.position || '';
                    });
            }
            
            modal.style.display = 'flex';
        }
        
        function closeUserModal() {
            document.getElementById('userModal').style.display = 'none';
        }
        
        // Delete modal functions
        function confirmDelete(userId, username) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = username;
            document.getElementById('deleteModal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Password reset functions
        function resetPassword(userId, username) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('passwordUserName').textContent = username;
            document.getElementById('passwordModal').style.display = 'flex';
        }
        
        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
        }
        
        // Search functionality
        document.getElementById('userSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#usersTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
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
</body>
</html>

<!--you made changes after this line-->

<?php
require_once 'includes/auth.php';