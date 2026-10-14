<?php
require_once __DIR__ . '/../auth.php';
check_auth();

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add Campaign Group & Schedule Timer
    if ($action === 'add_campaign') {
        $account_id = intval($_POST['telegram_account_id']);
        $template_id = intval($_POST['template_id']);
        $group_identifier = trim($_POST['group_identifier']);
        $override_endpoint = trim($_POST['override_endpoint']);
        $work_start = $_POST['work_start'] ?: null;
        $work_end = $_POST['work_end'] ?: null;

        if (!empty($account_id) && !empty($template_id) && !empty($group_identifier)) {
            $stmt = $pdo->prepare("
                INSERT INTO campaign_groups (telegram_account_id, template_id, group_identifier, override_endpoint_value, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$account_id, $template_id, $group_identifier, $override_endpoint]);
            
            // Optionally update the account schedule if provided
            if ($work_start && $work_end) {
                $stmt_sched = $pdo->prepare("UPDATE telegram_accounts SET work_schedule_start = ?, work_schedule_end = ? WHERE id = ? AND user_id = ?");
                $stmt_sched->execute([$work_start, $work_end, $account_id, $user_id]);
            }

            $success_msg = "Campaign group and schedule configured successfully!";
        } else {
            $error_msg = "Telegram Account, Template, and Group Identifier are required.";
        }
    } 
    // 2. Toggle Campaign Status (Active/Paused)
    elseif ($action === 'toggle_status') {
        $campaign_id = intval($_POST['campaign_id']);
        $current_status = intval($_POST['current_status']);
        $new_status = $current_status ? 0 : 1;

        $stmt = $pdo->prepare("
            UPDATE campaign_groups cg
            JOIN telegram_accounts ta ON cg.telegram_account_id = ta.id
            SET cg.is_active = ?
            WHERE cg.id = ? AND ta.user_id = ?
        ");
        $stmt->execute([$new_status, $campaign_id, $user_id]);
        $success_msg = "Campaign status updated.";
    }
    // 3. Delete Campaign
    elseif ($action === 'delete_campaign') {
        $campaign_id = intval($_POST['campaign_id']);
        $stmt = $pdo->prepare("
            DELETE cg FROM campaign_groups cg
            JOIN telegram_accounts ta ON cg.telegram_account_id = ta.id
            WHERE cg.id = ? AND ta.user_id = ?
        ");
        $stmt->execute([$campaign_id, $user_id]);
        $success_msg = "Campaign removed successfully.";
    }
}

// Fetch user's active telegram accounts and templates for the dropdown form
$stmt_acc = $pdo->prepare("SELECT id, phone_number, work_schedule_start, work_schedule_end FROM telegram_accounts WHERE user_id = ? AND status = 'active'");
$stmt_acc->execute([$user_id]);
$accounts = $stmt_acc->fetchAll();

$stmt_tmpl = $pdo->prepare("SELECT id, template_name FROM templates WHERE user_id = ?");
$stmt_tmpl->execute([$user_id]);
$templates = $stmt_tmpl->fetchAll();

// Fetch all existing campaigns for this user
$stmt_camps = $pdo->prepare("
    SELECT cg.*, ta.phone_number, ta.work_schedule_start, ta.work_schedule_end, t.template_name 
    FROM campaign_groups cg
    JOIN telegram_accounts ta ON cg.telegram_account_id = ta.id
    JOIN templates t ON cg.template_id = t.id
    WHERE ta.user_id = ?
    ORDER BY cg.id DESC
");
$stmt_camps->execute([$user_id]);
$campaigns = $stmt_camps->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Campaign Scheduler & Groups - Telegram Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="dashboard.php">TG Automation Dashboard</a>
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-sm btn-outline-light me-2">Overview</a>
                <a href="accounts.php" class="btn btn-sm btn-outline-light me-2">Accounts</a>
                <a href="templates.php" class="btn btn-sm btn-outline-light me-2">Templates</a>
                <a href="../logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container pb-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>Campaign Scheduler & Group Manager</h2>
                <p class="text-muted small mb-0">Assign behavior templates to specific group chats, set working hours, and manage endpoints.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Add Campaign & Schedule Form -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <h4 class="card-title mb-3">Add Group Chat & Set Schedule</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="add_campaign">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Telegram Account</label>
                            <select name="telegram_account_id" class="form-select" required>
                                <option value="">-- Select Account --</option>
                                <?php foreach ($accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['phone_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Behavior Template</label>
                            <select name="template_id" class="form-select" required>
                                <option value="">-- Select Template --</option>
                                <?php foreach ($templates as $tmpl): ?>
                                    <option value="<?= $tmpl['id'] ?>"><?= htmlspecialchars($tmpl['template_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Group Chat Link or Username</label>
                            <input type="text" name="group_identifier" class="form-control" placeholder="@crypto_group or https://t.me/..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Override Endpoint Link (Optional)</label>
                            <input type="text" name="override_endpoint" class="form-control" placeholder="Custom platform or group link">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Schedule Start Time (Optional)</label>
                            <input type="time" name="work_start" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Schedule End Time (Optional)</label>
                            <input type="time" name="work_end" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Save Campaign & Schedule</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Active Campaigns Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">Configured Group Campaigns</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Group Identifier</th>
                                <th>TG Account</th>
                                <th>Template</th>
                                <th>Working Schedule</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($campaigns)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No group campaigns configured yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($campaigns as $camp): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($camp['group_identifier']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($camp['phone_number']) ?></span></td>
                                        <td><?= htmlspecialchars($camp['template_name']) ?></td>
                                        <td>
                                            <?php if ($camp['work_start'] && $camp['work_end']): ?>
                                                <?= htmlspecialchars($camp['work_start']) ?> - <?= htmlspecialchars($camp['work_end']) ?>
                                            <?php else: ?>
                                                <span class="text-muted small">24/7 (No timer set)</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($camp['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Paused</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="campaign_id" value="<?= $camp['id'] ?>">
                                                <input type="hidden" name="current_status" value="<?= $camp['is_active'] ?>">
                                                <button type="submit" class="btn btn-sm <?= $camp['is_active'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                                    <?= $camp['is_active'] ? 'Pause' : 'Resume' ?>
                                                </button>
                                            </form>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="delete_campaign">
                                                <input type="hidden" name="campaign_id" value="<?= $camp['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this group campaign?')">Delete</button>
                                            </form>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
