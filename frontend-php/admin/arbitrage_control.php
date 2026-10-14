<?php
require_once __DIR__ . '/../auth.php';
check_admin();

$success_msg = $error_msg = "";

// Handle updating arbitrage access permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_arbitrage') {
    $target_user_id = intval($_POST['user_id']);
    $arbitrage_access = intval($_POST['arbitrage_access']);

    $stmt = $pdo->prepare("UPDATE users SET arbitrage_access = ? WHERE id = ?");
    if ($stmt->execute([$arbitrage_access, $target_user_id])) {
        $status_label = $arbitrage_access ? "granted to" : "revoked from";
        $success_msg = "Crypto/Betting Arbitrage access successfully {$status_label} User ID #{$target_user_id}.";
    } else {
        $error_msg = "Failed to update user access settings.";
    }
}

// Fetch all registered users
$stmt = $pdo->prepare("SELECT id, username, email, role, arbitrage_access, created_at FROM users ORDER BY created_at DESC");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Arbitrage Feature Control</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="mb-0">Arbitrage Module Control</h2>
                <p class="text-muted small mb-0">Grant or revoke Crypto & Betting Arbitrage outreach capabilities for users.</p>
            </div>
            <div>
                <a href="../user/dashboard.php" class="btn btn-outline-secondary me-2">User Panel</a>
                <a href="dashboard.php" class="btn btn-dark">Admin Overview</a>
            </div>
        </div>

        <?php if ($success_msg): ?>
            <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Users Table Card -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">User Access Permissions</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Arbitrage Access</th>
                                <th>Created Date</th>
                                <th>Toggle Permission</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No user accounts found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?= $u['id'] ?></td>
                                        <td><strong><?= htmlspecialchars($u['username']) ?></strong></td>
                                        <td><?= htmlspecialchars($u['email']) ?></td>
                                        <td>
                                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                                <?= strtoupper($u['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($u['arbitrage_access']): ?>
                                                <span class="badge bg-success">Granted</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark">Disabled</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><small class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></small></td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="toggle_arbitrage">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <?php if ($u['arbitrage_access']): ?>
                                                    <input type="hidden" name="arbitrage_access" value="0">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">Revoke Access</button>
                                                <?php else: ?>
                                                    <input type="hidden" name="arbitrage_access" value="1">
                                                    <button type="submit" class="btn btn-sm btn-success">Grant Access</button>
                                                <?php endif; ?>
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
