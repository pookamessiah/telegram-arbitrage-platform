<?php
require_once __DIR__ . '/../auth.php';
check_auth();

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$success_msg = $error_msg = "";

// Check user's arbitrage access permission
$stmt = $pdo->prepare("SELECT arbitrage_access FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();
$has_arbitrage = $user_info['arbitrage_access'] ?? 0;

// Fetch Account Statistics
// 1. Total Connected Telegram Accounts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM telegram_accounts WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_accounts = $stmt->fetchColumn();

// 2. Total Reusable Templates
$stmt = $pdo->prepare("SELECT COUNT(*) FROM templates WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_templates = $stmt->fetchColumn();

// 3. Discovered Prospects/Leads
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM prospects p
    JOIN telegram_accounts ta ON p.telegram_account_id = ta.id
    WHERE ta.user_id = ?
");
$stmt->execute([$user_id]);
$total_prospects = $stmt->fetchColumn();

// Handle Quick Campaign Setup (Linking a Telegram Account to a Group Chat & Template)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'launch_campaign') {
    $account_id = intval($_POST['account_id']);
    $template_id = intval($_POST['template_id']);
    $group_identifier = trim($_POST['group_identifier']);
    $override_endpoint = trim($_POST['override_endpoint']);

    if (!empty($account_id) && !empty($template_id) && !empty($group_identifier)) {
        $stmt = $pdo->prepare("
            INSERT INTO campaign_groups (telegram_account_id, template_id, group_identifier, override_endpoint_value, is_active)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([$account_id, $template_id, $group_identifier, $override_endpoint]);
        $success_msg = "New group chat campaign launched successfully!";
    } else {
        $error_msg = "Please select a Telegram Account, Template, and enter a Target Group Identifier.";
    }
}

// Fetch active accounts and templates for dropdown selection
$stmt = $pdo->prepare("SELECT id, phone_number FROM telegram_accounts WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$active_accounts = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT id, template_name FROM templates WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_templates = $stmt->fetchAll();

// Fetch Active Group Campaigns List
$stmt = $pdo->prepare("
    SELECT cg.*, ta.phone_number, t.template_name 
    FROM campaign_groups cg
    JOIN telegram_accounts ta ON cg.telegram_account_id = ta.id
    JOIN templates t ON cg.template_id = t.id
    WHERE ta.user_id = ?
    ORDER BY cg.id DESC
");
$stmt->execute([$user_id]);
$active_campaigns = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard - Telegram Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="#">TG Automation</a>
            <div class="d-flex align-items-center">
                <span class="text-light me-3">Welcome, <strong><?= htmlspecialchars($username) ?></strong></span>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                    <a href="../admin/arbitrage_control.php" class="btn btn-sm btn-outline-warning me-2">Admin Panel</a>
                <?php endif; ?>
                <a href="../logout.php" class="btn btn-sm btn-outline-light">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Stat Cards Overview -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body">
                        <h6 class="card-subtitle text-muted mb-1">Telegram Accounts</h6>
                        <h2 class="card-title mb-0 text-primary"><?= $total_accounts ?></h2>
                        <a href="accounts.php" class="btn btn-link px-0 text-decoration-none small">Manage Accounts &rarr;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body">
                        <h6 class="card-subtitle text-muted mb-1">Saved Templates</h6>
                        <h2 class="card-title mb-0 text-success"><?= $total_templates ?></h2>
                        <a href="templates.php" class="btn btn-link px-0 text-decoration-none small">Manage Templates &rarr;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm border-0 bg-white">
                    <div class="card-body">
                        <h6 class="card-subtitle text-muted mb-1">Discovered Prospects</h6>
                        <h2 class="card-title mb-0 text-info"><?= $total_prospects ?></h2>
                        <a href="prospects.php" class="btn btn-link px-0 text-decoration-none small">View Leads & Score &rarr;</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Launch Campaign Form -->
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h4 class="card-title mb-3">Quick Launch Group Campaign</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="launch_campaign">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Select Telegram Account</label>
                            <select name="account_id" class="form-select" required>
                                <option value="">-- Choose Account --</option>
                                <?php foreach ($active_accounts as $acc): ?>
                                    <option value="<?= $acc['id'] ?>"><?= htmlspecialchars($acc['phone_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Select Behavior Template</label>
                            <select name="template_id" class="form-select" required>
                                <option value="">-- Choose Template --</option>
                                <?php foreach ($user_templates as $tmpl): ?>
                                    <option value="<?= $tmpl['id'] ?>"><?= htmlspecialchars($tmpl['template_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Target Group Link / Username</label>
                            <input type="text" name="group_identifier" class="form-control" placeholder="@group_username or https://t.me/group" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Override Endpoint Link (Optional)</label>
                            <input type="text" name="override_endpoint" class="form-control" placeholder="Specific platform or group link for this group chat">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Launch Automation</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Active Group Campaigns List -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">Active Group Chat Campaigns</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Target Group</th>
                                <th>TG Account</th>
                                <th>Template Applied</th>
                                <th>Override Endpoint</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_campaigns)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No active group chat campaigns yet. Launch one above!</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_campaigns as $camp): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($camp['group_identifier']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($camp['phone_number']) ?></span></td>
                                        <td><?= htmlspecialchars($camp['template_name']) ?></td>
                                        <td><?= htmlspecialchars($camp['override_endpoint_value'] ?: 'Default Template Endpoint') ?></td>
                                        <td>
                                            <?= $camp['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Paused</span>' ?>
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
