<?php
require_once __DIR__ . '/../auth.php';
check_admin();

$success_msg = $error_msg = "";

// Handle User Management Actions (Change Role or Delete User)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target_user_id = intval($_POST['user_id'] ?? 0);

    // Prevent admin from modifying or deleting their own account accidentally
    if ($target_user_id === intval($_SESSION['user_id'])) {
        $error_msg = "You cannot modify or delete your own administrator account.";
    } else {
        if ($action === 'update_role') {
            $new_role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            if ($stmt->execute([$new_role, $target_user_id])) {
                $success_msg = "User role successfully updated to " . strtoupper($new_role) . ".";
            } else {
                $error_msg = "Failed to update user role.";
            }
        } elseif ($action === 'delete_user') {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$target_user_id])) {
                $success_msg = "User account successfully deleted.";
            } else {
                $error_msg = "Failed to delete user account.";
            }
        }
    }
}

// Fetch all registered users
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.role, u.arbitrage_access, u.created_at,
           (SELECT COUNT(*) FROM telegram_accounts ta WHERE ta.user_id = u.id) as total_accounts
    FROM users u
    ORDER BY u.created_at DESC
");
$stmt->execute();
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - User Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Admin Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="dashboard.php">TG Admin Control Center</a>
            <div class="d-flex align-items-center">
                <a href="arbitrage_control.php" class="btn btn-sm btn-outline-light me-2">Arbitrage Control</a>
                <a href="../user/dashboard.php" class="btn btn-sm btn-outline-light me-2">User View</a>
                <a href="../logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-3">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2>User Account Management</h2>
                <p class="text-muted small mb-0">View registered platform users, adjust system roles, and manage accounts.</p>
            </div>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Admin Overview</a>
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
                <h4 class="card-title mb-3">Registered Users</h4>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Username / Email</th>
                                <th>Role</th>
                                <th>TG Accounts</th>
                                <th>Arbitrage Status</th>
                                <th>Joined Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($users)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No users registered yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td>#<?= $u['id'] ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($u['username']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($u['email']) ?></small>
                                        </td>
                                        <td>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <select name="role" class="form-select form-select-sm d-inline-block w-auto" onchange="this.form.submit()">
                                                    <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                    <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td><span class="badge bg-secondary"><?= $u['total_accounts'] ?> Linked</span></td>
                                        <td>
                                            <?= $u['arbitrage_access'] ? '<span class="badge bg-success">Granted</span>' : '<span class="badge bg-secondary">Disabled</span>' ?>
                                        </td>
                                        <td><small class="text-muted"><?= date('M j, Y', strtotime($u['created_at'])) ?></small></td>
                                        <td>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to permanently delete this user account and all their campaigns?');">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
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
