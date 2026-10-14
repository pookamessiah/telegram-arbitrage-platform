<?php
require_once __DIR__ . '/../auth.php';
check_auth();

$user_id = $_SESSION['user_id'];
$success_msg = "";

// Handle status updates if user changes lead status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $prospect_id = intval($_POST['prospect_id']);
    $new_status = $_POST['status'];

    // Verify ownership via account join
    $stmt = $pdo->prepare("
        UPDATE prospects p
        JOIN telegram_accounts ta ON p.telegram_account_id = ta.id
        SET p.status = ?
        WHERE p.id = ? AND ta.user_id = ?
    ");
    $stmt->execute([$new_status, $prospect_id, $user_id]);
    $success_msg = "Prospect status updated successfully!";
}

// Fetch all prospects linked to the user's telegram accounts
$stmt = $pdo->prepare("
    SELECT p.*, ta.phone_number 
    FROM prospects p
    JOIN telegram_accounts ta ON p.telegram_account_id = ta.id
    WHERE ta.user_id = ?
    ORDER BY p.interest_score DESC, p.updated_at DESC
");
$stmt->execute([$user_id]);
$prospects = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Prospects & Leads - Telegram Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Prospect Group Chat Leads</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div>
        <?php endif; ?>

        <!-- Prospects Table Card -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">Discovered Leads & Discussion Summaries</h4>
                <p class="text-muted small">Here you can view members from target group chats whose discussions matched your selected interests, along with Gemini's interest score and summary.</p>
                
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Prospect Details</th>
                                <th>TG Account Used</th>
                                <th>Interest Score</th>
                                <th>Discussion Summary</th>
                                <th>Status</th>
                                <th>Action / Endpoint</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($prospects)): ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No prospects discovered yet. Ensure your bot accounts are active and running campaigns in group chats.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($prospects as $p): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($p['prospect_name'] ?: 'Unknown') ?></strong><br>
                                            <span class="text-muted small">@<?= htmlspecialchars($p['prospect_username'] ?: 'no_username') ?></span><br>
                                            <span class="badge bg-light text-dark font-monospace">ID: <?= htmlspecialchars($p['prospect_tg_id']) ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($p['phone_number']) ?></span>
                                        </td>
                                        <td>
                                            <?php 
                                                $score = intval($p['interest_score']);
                                                $badge_color = $score >= 80 ? 'bg-success' : ($score >= 50 ? 'bg-warning text-dark' : 'bg-secondary');
                                            ?>
                                            <span class="badge <?= $badge_color ?> fs-6"><?= $score ?>/100</span>
                                        </td>
                                        <td>
                                            <p class="mb-0 small"><?= htmlspecialchars($p['discussion_summary'] ?: 'No summary available.') ?></p>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_status">
                                                <input type="hidden" name="prospect_id" value="<?= $p['id'] ?>">
                                                <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="discovered" <?= $p['status'] == 'discovered' ? 'selected' : '' ?>>Discovered</option>
                                                    <option value="contacted" <?= $p['status'] == 'contacted' ? 'selected' : '' ?>>Contacted</option>
                                                    <option value="converted" <?= $p['status'] == 'converted' ? 'selected' : '' ?>>Converted</option>
                                                    <option value="ignored" <?= $p['status'] == 'ignored' ? 'selected' : '' ?>>Ignored</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <?php if ($p['prospect_username']): ?>
                                                <a href="https://t.me/<?= htmlspecialchars($p['prospect_username']) ?>" target="_blank" class="btn btn-sm btn-outline-primary mb-1 w-100">Open DM</a>
                                            <?php else: ?>
                                                <span class="text-muted small">No Username</span>
                                            <?php endif; ?>
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
</body>
</html>
