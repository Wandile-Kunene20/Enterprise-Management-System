<?php
require_once '../../includes/auth.php';
require_once '../../includes/database.php';
require_once '../../includes/security.php';

Auth::checkSession();
if (!Auth::hasRole('manager')) {
    header('Location: ../../dashboard.php');
    exit();
}

$db = Database::getInstance()->getConnection();

// Pagination basic
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Fetch reports
$stmt = $db->prepare("SELECT r.*, u.full_name as author_name, u.email as author_email
                       FROM reports r
                       JOIN users u ON r.user_id = u.id
                       ORDER BY r.created_at DESC
                       LIMIT ? OFFSET ?");
$stmt->execute([$perPage, $offset]);
$reports = $stmt->fetchAll();

// Count total
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM reports");
$countStmt->execute();
$total = $countStmt->fetch()['total'];

$page_title = 'Submitted Reports';
?>

<?php include '../../includes/header.php'; ?>

<div class="card glass-card fade-in" style="margin-top: 2rem;">
    <div class="card-header">
        <h2><i class="fas fa-file-alt"></i> Submitted Reports</h2>
        <a href="dashboard.php" class="btn btn-outline btn-sm">Back</a>
    </div>
    <div class="table-container">
        <table class="table glass-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Submitted At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($reports)): ?>
                    <tr><td colspan="5" style="text-align:center; padding:1.5rem;">No reports found.</td></tr>
                <?php else: ?>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?php echo $r['id']; ?></td>
                            <td><?php echo Security::escapeOutput($r['title'] ?: '(No title)'); ?></td>
                            <td><?php echo Security::escapeOutput($r['author_name']); ?> <div style="font-size:0.8rem; color:#666;">(<?php echo Security::escapeOutput($r['author_email']); ?>)</div></td>
                            <td><?php echo date('M d, Y H:i', strtotime($r['created_at'])); ?></td>
                            <td>
                                <a href="reports.php?id=<?php echo $r['id']; ?>" class="btn btn-outline btn-sm">View</a>
                                <a href="reports.php?action=download&id=<?php echo $r['id']; ?>" class="btn btn-outline btn-sm">Download</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (isset($_GET['id'])): ?>
        <?php
            $rid = intval($_GET['id']);
            $s = $db->prepare("SELECT r.*, u.full_name FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
            $s->execute([$rid]);
            $rep = $s->fetch();
        ?>
        <?php if ($rep): ?>
            <div class="card glass-card" style="margin-top:1rem; padding:1rem;">
                <h3><?php echo Security::escapeOutput($rep['title'] ?: '(No title)'); ?></h3>
                <div style="font-size:0.9rem; color:#666; margin-bottom:1rem;">By <?php echo Security::escapeOutput($rep['full_name']); ?> on <?php echo date('M d, Y H:i', strtotime($rep['created_at'])); ?></div>
                <pre style="white-space:pre-wrap; font-family:inherit; background:#fafafa; padding:1rem; border-radius:6px;"><?php echo Security::escapeOutput($rep['body']); ?></pre>
            </div>
        <?php else: ?>
            <div class="alert alert-error">Report not found.</div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="margin-top:1rem;">
        <?php
            $lastPage = max(1, ceil($total / $perPage));
            for ($p = 1; $p <= $lastPage; $p++):
        ?>
            <a href="?page=<?php echo $p; ?>" class="btn btn-outline btn-sm" style="margin-right:0.25rem; <?php echo $p==$page ? 'font-weight:600;' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
