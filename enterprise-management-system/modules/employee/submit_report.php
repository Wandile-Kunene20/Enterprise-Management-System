<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/security.php';

Auth::checkSession();
if (!Auth::hasRole('employee')) {
    header('Location: ../../dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();
$user_id = $_SESSION['user_id'];

// Handle submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $report_title = Security::sanitizeInput($_POST['title'] ?? '');
        $report_body = Security::sanitizeInput($_POST['body'] ?? '');

        if (empty($report_body)) {
            $error = 'Please enter a report.';
        } else {
            // Insert report into DB
            try {
                $stmt = $db->prepare("INSERT INTO reports (user_id, title, body) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $report_title, $report_body]);
                $success = 'Report submitted successfully.';
            } catch (Exception $e) {
                // Fallback: save to file if DB insert fails
                $reports_dir = '../../uploads/reports/';
                if (!is_dir($reports_dir)) {
                    mkdir($reports_dir, 0755, true);
                }
                $filename = 'report_user_' . $user_id . '_' . time() . '.txt';
                $content = "Title: " . $report_title . "\n" .
                           "User: " . $_SESSION['full_name'] . " (ID: " . $user_id . ")\n" .
                           "Date: " . date('Y-m-d H:i:s') . "\n\n" .
                           $report_body;

                if (file_put_contents($reports_dir . $filename, $content) !== false) {
                    $success = 'Report submitted and saved to file (DB unavailable).';
                } else {
                    $error = 'Failed to save the report. Please try again.';
                }
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();
$page_title = 'Submit Report';
?>

<?php include '../../includes/header.php'; ?>

<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-file-alt"></i> Submit Report</h2>
        <a href="tasks.php" class="btn btn-outline btn-sm">Back to Tasks</a>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-error fade-in"><i class="fas fa-exclamation-circle"></i> <?php echo Security::escapeOutput($error); ?></div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="alert alert-success fade-in"><i class="fas fa-check-circle"></i> <?php echo Security::escapeOutput($success); ?></div>
    <?php endif; ?>

    <div style="padding: 1.5rem;">
        <form method="POST" action="" style="display: grid; gap: 1rem;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            <div class="form-group">
                <label for="title" class="form-label">Title</label>
                <input type="text" id="title" name="title" class="form-control glass-input" placeholder="Brief title (optional)">
            </div>
            <div class="form-group">
                <label for="body" class="form-label">Report</label>
                <textarea id="body" name="body" rows="8" class="form-control glass-input" required></textarea>
            </div>
            <div>
                <button type="submit" class="btn btn-primary glass-button"><i class="fas fa-paper-plane"></i> Submit Report</button>
            </div>
        </form>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
