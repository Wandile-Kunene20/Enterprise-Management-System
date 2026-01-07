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
            case 'update_profile':
                $full_name = Security::sanitizeInput($_POST['full_name']);
                $email = Security::sanitizeInput($_POST['email'], 'email');
                $phone = Security::sanitizeInput($_POST['phone']);
                $address = Security::sanitizeInput($_POST['address']);
                $date_of_birth = Security::sanitizeInput($_POST['date_of_birth']);
                
                // Check if email is already taken by another user
                $stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $employee_id]);
                
                if ($stmt->fetch()) {
                    $error = 'Email already taken by another user.';
                } else {
                    $stmt = $db->prepare("
                        UPDATE users 
                        SET full_name = ?, email = ?, phone = ?, address = ?, date_of_birth = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$full_name, $email, $phone, $address, $date_of_birth, $employee_id]);
                    
                    // Update session data
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    
                    $success = "Profile updated successfully.";
                }
                break;
                
            case 'change_password':
                $current_password = $_POST['current_password'];
                $new_password = $_POST['new_password'];
                $confirm_password = $_POST['confirm_password'];
                
                if ($new_password !== $confirm_password) {
                    $error = "New passwords do not match.";
                } else {
                    $result = Auth::changePassword($employee_id, $current_password, $new_password);
                    
                    if ($result['success']) {
                        $success = $result['message'];
                    } else {
                        $error = $result['message'];
                    }
                }
                break;
                
            case 'upload_avatar':
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    $file = $_FILES['avatar'];
                    
                    // Validate file
                    list($valid, $errors) = Security::validateFile($file);
                    
                    if ($valid) {
                        // Generate unique filename
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = 'avatar_' . $employee_id . '_' . time() . '.' . $extension;
                        $upload_path = '../../uploads/' . $filename;
                        
                        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                            // Update database
                            $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                            $stmt->execute([$filename, $employee_id]);
                            $success = "Profile picture updated successfully.";
                        } else {
                            $error = "Failed to upload file.";
                        }
                    } else {
                        $error = implode(' ', $errors);
                    }
                } else {
                    $error = "Please select a valid image file.";
                }
                break;
        }
    }
}

// Get employee information
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$employee_id]);
$employee = $stmt->fetch();

// Get employee statistics
$stats = [];

// Total tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ?");
$stmt->execute([$employee_id]);
$stats['total_tasks'] = $stmt->fetch()['total'];

// Completed tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'completed'");
$stmt->execute([$employee_id]);
$stats['completed_tasks'] = $stmt->fetch()['total'];

// Ongoing tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'in_progress'");
$stmt->execute([$employee_id]);
$stats['ongoing_tasks'] = $stmt->fetch()['total'];

// Pending tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND status = 'pending'");
$stmt->execute([$employee_id]);
$stats['pending_tasks'] = $stmt->fetch()['total'];

// Overdue tasks
$stmt = $db->prepare("SELECT COUNT(*) as total FROM tasks WHERE assigned_to = ? AND due_date < CURDATE() AND status != 'completed'");
$stmt->execute([$employee_id]);
$stats['overdue_tasks'] = $stmt->fetch()['total'];

// Calculate completion rate
$completion_rate = $stats['total_tasks'] > 0 ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) : 0;

// Get recent activity
$stmt = $db->prepare("
    SELECT 'task' as type, t.title, 
           CASE 
             WHEN t.status = 'completed' THEN 'Task completed'
             ELSE 'Task status updated'
           END as description,
           COALESCE(t.completed_at, t.created_at) as activity_date
    FROM tasks t
    WHERE t.assigned_to = ?
    ORDER BY activity_date DESC
    LIMIT 5
");
$stmt->execute([$employee_id]);
$recent_activity = $stmt->fetchAll();

// Generate CSRF token
$csrf_token = Security::generateCSRFToken();

$page_title = "My Profile";
?>

<?php include '../../includes/header.php'; ?>

<!-- Profile Header -->
<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-user-circle"></i> My Profile</h2>
        <div style="display: flex; gap: 1rem;">
            <button class="btn btn-outline" onclick="showUploadAvatarModal()">
                <i class="fas fa-camera"></i> Change Photo
            </button>
            <button class="btn btn-outline" onclick="showChangePasswordModal()">
                <i class="fas fa-key"></i> Change Password
            </button>
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
    
    <div style="padding: 2rem;">
        <!-- Profile Overview -->
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 3rem; margin-bottom: 3rem;">
            <!-- Left Column: Avatar and Stats -->
            <div>
                <div style="text-align: center;">
                    <div style="position: relative; display: inline-block;">
                        <div class="user-avatar" style="width: 150px; height: 150px; background: linear-gradient(90deg, #fe7f2d, #233d4d); 
                                                        font-size: 3rem; margin-bottom: 1rem;">
                            <?php 
                            if ($employee['profile_image']) {
                                echo '<img src="../../uploads/' . Security::escapeOutput($employee['profile_image']) . '" 
                                      style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">';
                            } else {
                                echo strtoupper(substr($employee['full_name'], 0, 1));
                            }
                            ?>
                        </div>
                        <button class="btn btn-outline btn-sm" 
                                style="position: absolute; bottom: 10px; right: 10px;"
                                onclick="showUploadAvatarModal()">
                            <i class="fas fa-camera"></i>
                        </button>
                    </div>
                    
                    <h3 style="margin-bottom: 0.5rem;"><?php echo Security::escapeOutput($employee['full_name']); ?></h3>
                    <div style="color: #666; margin-bottom: 1rem;">
                        <i class="fas fa-briefcase"></i>
                        <?php echo Security::escapeOutput($employee['position'] ?? 'Employee'); ?> | 
                        <?php echo Security::escapeOutput($employee['department'] ?? 'N/A'); ?>
                    </div>
                    
                    <div style="display: flex; justify-content: center; gap: 1rem; margin-top: 1rem;">
                        <div class="text-center">
                            <div style="font-size: 1.5rem; font-weight: 600; color: #fe7f2d;"><?php echo $completion_rate; ?>%</div>
                            <div style="font-size: 0.875rem; color: #666;">Performance</div>
                        </div>
                        <div class="text-center">
                            <div style="font-size: 1.5rem; font-weight: 600; color: #233d4d;"><?php echo $stats['total_tasks']; ?></div>
                            <div style="font-size: 0.875rem; color: #666;">Total Tasks</div>
                        </div>
                        <div class="text-center">
                            <div style="font-size: 1.5rem; font-weight: 600; color: #28a745;"><?php echo $stats['completed_tasks']; ?></div>
                            <div style="font-size: 0.875rem; color: #666;">Completed</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column: Profile Form -->
            <div>
                <h3 style="margin-bottom: 1.5rem;">Personal Information</h3>
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label for="full_name" class="form-label">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control glass-input" 
                                   value="<?php echo Security::escapeOutput($employee['full_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" id="email" name="email" class="form-control glass-input" 
                                   value="<?php echo Security::escapeOutput($employee['email']); ?>" required>
                        </div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="form-group">
                            <label for="phone" class="form-label">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control glass-input" 
                                   value="<?php echo Security::escapeOutput($employee['phone'] ?? ''); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_birth" class="form-label">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" class="form-control glass-input" 
                                   value="<?php echo $employee['date_of_birth'] ?? ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address" class="form-label">Address</label>
                        <textarea id="address" name="address" class="form-control glass-input" rows="3"><?php echo Security::escapeOutput($employee['address'] ?? ''); ?></textarea>
                    </div>
                    
                    <div style="margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary glass-button">
                            <i class="fas fa-save"></i> Update Profile
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Employment Details -->
        <div class="card glass-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3><i class="fas fa-briefcase"></i> Employment Details</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 2rem;">
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Employee ID</div>
                        <div style="font-weight: 600;">EMP-<?php echo str_pad($employee['id'], 4, '0', STR_PAD_LEFT); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Username</div>
                        <div style="font-weight: 600;">@<?php echo Security::escapeOutput($employee['username']); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Department</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($employee['department'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Position</div>
                        <div style="font-weight: 600;"><?php echo Security::escapeOutput($employee['position'] ?? 'N/A'); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Hire Date</div>
                        <div style="font-weight: 600;"><?php echo date('F d, Y', strtotime($employee['hire_date'])); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.875rem; color: #999;">Employment Status</div>
                        <div>
                            <span class="badge <?php 
                                echo $employee['status'] === 'active' ? 'badge-success' : 
                                     ($employee['status'] === 'inactive' ? 'badge-warning' : 'badge-danger'); 
                            ?>">
                                <?php echo ucfirst($employee['status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Task Statistics -->
        <div class="card glass-card" style="margin-bottom: 2rem;">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Task Statistics</h3>
            </div>
            <div style="padding: 1.5rem;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1.5rem; text-align: center;">
                    <div class="card glass-stat">
                        <div class="stat-number"><?php echo $stats['total_tasks']; ?></div>
                        <div class="stat-label">Total Tasks</div>
                    </div>
                    <div class="card glass-stat">
                        <div class="stat-number"><?php echo $stats['completed_tasks']; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                    <div class="card glass-stat">
                        <div class="stat-number"><?php echo $stats['ongoing_tasks']; ?></div>
                        <div class="stat-label">In Progress</div>
                    </div>
                    <div class="card glass-stat">
                        <div class="stat-number"><?php echo $stats['pending_tasks']; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <div class="card glass-stat">
                        <div class="stat-number" style="color: <?php echo $stats['overdue_tasks'] > 0 ? '#dc3545' : '#28a745'; ?>;">
                            <?php echo $stats['overdue_tasks']; ?>
                        </div>
                        <div class="stat-label">Overdue</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card glass-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Activity</h3>
            </div>
            <div style="padding: 1.5rem;">
                <?php if (empty($recent_activity)): ?>
                    <div style="text-align: center; padding: 2rem;">
                        <div style="font-size: 3rem; color: #ddd; margin-bottom: 1rem;">
                            <i class="fas fa-history"></i>
                        </div>
                        <h4 style="color: #666; margin-bottom: 0.5rem;">No Recent Activity</h4>
                        <p style="color: #999;">Your recent activities will appear here.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php foreach ($recent_activity as $activity): ?>
                        <div class="card glass-card" style="padding: 1rem;">
                            <div style="display: flex; justify-content: space-between; align-items: start;">
                                <div>
                                    <div style="font-weight: 600;">
                                        <i class="fas fa-<?php echo $activity['type'] === 'task' ? 'tasks' : 'user'; ?>" 
                                           style="color: #fe7f2d; margin-right: 0.5rem;"></i>
                                        <?php echo Security::escapeOutput($activity['description']); ?>
                                    </div>
                                    <?php if ($activity['type'] === 'task'): ?>
                                        <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                                            <?php echo Security::escapeOutput($activity['title']); ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div style="font-size: 0.875rem; color: #666;">
                                    <?php echo date('M d, Y H:i', strtotime($activity['activity_date'])); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<!-- Upload Avatar Modal -->
<div id="uploadAvatarModal" class="modal" style="display: none;">
    <div class="modal-content glass-modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Change Profile Picture</h3>
            <button class="close-btn" onclick="closeUploadAvatarModal()">&times;</button>
        </div>
        <form id="uploadAvatarForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="upload_avatar">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="avatar" class="form-label">Select Image</label>
                    <input type="file" id="avatar" name="avatar" class="form-control glass-input" accept="image/*" required>
                    <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                        <i class="fas fa-info-circle"></i> Max file size: 5MB. Allowed types: JPG, PNG, GIF
                    </div>
                </div>
                
                <div style="text-align: center; margin-top: 1rem;">
                    <div id="avatarPreview" style="width: 150px; height: 150px; border-radius: 50%; 
                                                   background: linear-gradient(90deg, #fe7f2d, #233d4d); 
                                                   margin: 0 auto; display: flex; align-items: center; 
                                                   justify-content: center; color: white; font-size: 2rem;">
                        <?php 
                        if ($employee['profile_image']) {
                            echo '<img src="../../uploads/' . Security::escapeOutput($employee['profile_image']) . '" 
                                  style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;" 
                                  id="currentAvatar">';
                        } else {
                            echo strtoupper(substr($employee['full_name'], 0, 1));
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeUploadAvatarModal()">Cancel</button>
                <button type="submit" class="btn btn-primary glass-button">
                    <i class="fas fa-upload"></i> Upload Picture
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="changePasswordModal" class="modal" style="display: none;">
    <div class="modal-content glass-modal" style="max-width: 500px;">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button class="close-btn" onclick="closeChangePasswordModal()">&times;</button>
        </div>
        <form id="changePasswordForm" method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <input type="hidden" name="action" value="change_password">
            
            <div class="modal-body">
                <div class="form-group">
                    <label for="current_password" class="form-label">Current Password *</label>
                    <input type="password" id="current_password" name="current_password" class="form-control glass-input" required>
                </div>
                
                <div class="form-group">
                    <label for="new_password" class="form-label">New Password *</label>
                    <input type="password" id="new_password" name="new_password" class="form-control glass-input" required>
                    <div style="font-size: 0.875rem; color: #666; margin-top: 0.25rem;">
                        <i class="fas fa-info-circle"></i> Minimum 8 characters with uppercase, lowercase, and number
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password" class="form-label">Confirm New Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control glass-input" required>
                </div>
                
                <div id="passwordStrength" style="margin-top: 1rem; display: none;">
                    <div style="font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Password Strength:</div>
                    <div style="height: 6px; background: #e0e0e0; border-radius: 3px; overflow: hidden;">
                        <div id="passwordStrengthBar" style="height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeChangePasswordModal()">Cancel</button>
                <button type="submit" class="btn btn-primary glass-button">
                    <i class="fas fa-key"></i> Change Password
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

.user-avatar {
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 600;
}

.text-center {
    text-align: center;
}
</style>

<script>
// Avatar Preview
document.getElementById('avatar').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            const currentAvatar = document.getElementById('currentAvatar');
            
            if (currentAvatar) {
                currentAvatar.src = e.target.result;
            } else {
                preview.innerHTML = `<img src="${e.target.result}" 
                    style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`;
            }
        };
        reader.readAsDataURL(file);
    }
});

// Password Strength Checker
document.getElementById('new_password').addEventListener('input', function(e) {
    const password = e.target.value;
    const strengthBar = document.getElementById('passwordStrengthBar');
    const strengthContainer = document.getElementById('passwordStrength');
    
    if (password.length === 0) {
        strengthContainer.style.display = 'none';
        return;
    }
    
    strengthContainer.style.display = 'block';
    
    let strength = 0;
    
    // Length check
    if (password.length >= 8) strength++;
    if (password.length >= 12) strength++;
    
    // Character variety checks
    if (/[a-z]/.test(password)) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^A-Za-z0-9]/.test(password)) strength++;
    
    // Calculate percentage and color
    const percentage = Math.min(strength * 20, 100);
    let color = '#dc3545'; // red
    
    if (percentage >= 60) {
        color = '#ffc107'; // yellow
    }
    if (percentage >= 80) {
        color = '#28a745'; // green
    }
    
    strengthBar.style.width = percentage + '%';
    strengthBar.style.backgroundColor = color;
});

// Modal Functions
function showUploadAvatarModal() {
    document.getElementById('uploadAvatarModal').style.display = 'flex';
}

function closeUploadAvatarModal() {
    document.getElementById('uploadAvatarModal').style.display = 'none';
    document.getElementById('uploadAvatarForm').reset();
}

function showChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'flex';
}

function closeChangePasswordModal() {
    document.getElementById('changePasswordModal').style.display = 'none';
    document.getElementById('changePasswordForm').reset();
    document.getElementById('passwordStrength').style.display = 'none';
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