<?php
require_once __DIR__ . '/../auth.php';
check_admin();

// Fetch recent system logs or prospects activity log
$stmt = $pdo->prepare("
    SELECT p.*, ta.phone_number, u.username as owner_username
    FROM prospects p
    JOIN telegram_accounts ta ON p.telegram_account_id = ta.id
    JOIN users u ON ta.user_id = u.id
    ORDER BY p.updated_at DESC
    LIMIT 50
");
$stmt->execute();
$logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - System Logs & Activity</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="dashboard.php">TG Admin Logs</a>
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="btn btn-sm btn-outline-light me-2">Overview</a>
                <a href="../logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>
        </div>
    </nav>
    <div class="container py-3">
        <h2 class="mb-4">System Activity & Prospect Logs</h2>
        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Time</th>
                                <th>Owner</th>
                                <th>TG Number</th>
                                <th>Prospect</th>
                                <th>Score</th>
                                <th>Discussion Summary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-4">No activity logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                    <tr>
                                        <td><small class="text-muted"><?= $l['updated_at'] ?></small></td>
                                        <td><?= htmlspecialchars($l['owner_username']) ?></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($l['phone_number']) ?></span></td>
                                        <td>@<?= htmlspecialchars($l['prospect_username'] ?: 'N/A') ?></td>
                                        <td><span class="badge bg-info text-dark"><?= $l['interest_score'] ?>/100</span></td>
                                        <td><small><= htmlspecialchars($l['discussion_summary']) ?></small></td>
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
