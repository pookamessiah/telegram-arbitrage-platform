<?php
require_once __DIR__ . '/../auth.php';
check_auth();

$user_id =$_SESSION['user_id'];
$success_msg =$error_msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) &&$_POST['action'] === 'add_account') {
        $phone_number = trim($_POST['phone_number']);
        $api_id = intval($_POST['api_id']);
        $api_hash = trim($_POST['api_hash']);
        $session_string = trim($_POST['session_string']);
        $proxy_server = trim($_POST['proxy_server']);
        $schedule_start =$_POST['schedule_start'] ?: null;
        $schedule_end =$_POST['schedule_end'] ?: null;

        if (!empty($phone_number) && !empty($api_id) && !empty($api_hash)) {
            $stmt =$pdo->prepare("INSERT INTO telegram_accounts (user_id, phone_number, api_id, api_hash, session_string, proxy_server, work_schedule_start, work_schedule_end, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$user_id, $phone_number,$api_id, $api_hash,$session_string, $proxy_server,$schedule_start, $schedule_end]);$success_msg = "Telegram account connected successfully!";
        } else {
            $error_msg = "Phone Number, API ID, and API Hash are required.";
        }
    } elseif (isset($_POST['action']) &&$_POST['action'] === 'delete_account') {
        $account_id = intval($_POST['account_id']);
        $stmt =$pdo->prepare("DELETE FROM telegram_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $user_id]);$success_msg = "Telegram account removed successfully!";
    }
}

$stmt =$pdo->prepare("SELECT * FROM telegram_accounts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$accounts =$stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Telegram Accounts - Automation Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Connected Telegram Accounts</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

        <!-- Add Account Card -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <h4 class="card-title mb-3">Connect New Telegram Number</h4>
                <p class="text-muted small">Get your `API_ID` and `API_HASH` from <a href="https://my.telegram.org" target="_blank">my.telegram.org</a>. Generate your Pyrogram session string locally or via an authenticated session generator before connecting.</p>
                <form method="POST">
                    <input type="hidden" name="action" value="add_account">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Phone Number</label>
                            <input type="text" name="phone_number" class="form-control" placeholder="+1234567890" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">API ID</label>
                            <input type="number" name="api_id" class="form-control" placeholder="1234567" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">API Hash</label>
                            <input type="text" name="api_hash" class="form-control" placeholder="32-character hash" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Pyrogram Session String</label>
                            <textarea name="session_string" class="form-control" rows="2" placeholder="Paste session string token here..."></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Proxy Server (Optional)</label>
                            <input type="text" name="proxy_server" class="form-control" placeholder="socks5://user:pass@host:port">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Work Schedule Start (Optional)</label>
                            <input type="time" name="schedule_start" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Work Schedule End (Optional)</label>
                            <input type="time" name="schedule_end" class="form-control">
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-success">Link Telegram Account</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Accounts List Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">Your Connected Numbers</h4>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Phone Number</th>
                                <th>API ID</th>
                                <th>Status</th>
                                <th>Schedule</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($accounts)): ?>
                                <tr><td colspan="5" class="text-center text-muted">No Telegram accounts connected yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($accounts as$acc): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($acc['phone_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($acc['api_id']) ?></td>
                                        <td><span class="badge bg-success"><?= strtoupper($acc['status']) ?></span></td>
                                        <td><?= ($acc['work_schedule_start'] &&$acc['work_schedule_end']) ? "{$acc['work_schedule_start']} - {$acc['work_schedule_end']}" : '24/7 Active' ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete_account">
                                                <input type="hidden" name="account_id" value="<?= $acc['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Disconnect this Telegram account?')">Disconnect</button>
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
</body>
</html>
