<?php
require_once __DIR__ . '/../auth.php';
check_auth();

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = "";
$auth_step = 1; // 1: Initial form / Request code, 2: Enter OTP code

if (!isset($_SESSION['tg_auth'])) {
    $_SESSION['tg_auth'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_code') {
        $phone_number = trim($_POST['phone_number'] ?? '');
        $api_id = trim($_POST['api_id'] ?? '');
        $api_hash = trim($_POST['api_hash'] ?? '');

        if (empty($phone_number) || empty($api_id) || empty($api_hash)) {
            $error_msg = "Phone Number, API ID, and API Hash are required.";
        } else {
            $payload = json_encode([
                "action" => "send_code",
                "api_id" => intval($api_id),
                "api_hash" => $api_hash,
                "phone_number" => $phone_number
            ]);

            $python_path = realpath(__DIR__ . '/../../backend-python/telegram_auth_helper.py');
            $command = "python3 " . escapeshellarg($python_path);
            
            $descriptors = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], $payload);
                fclose($pipes[0]);
                
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error_output = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                
                proc_close($process);
                
                $response = json_decode($output, true);
                if ($response && isset($response['status']) && $response['status'] === 'success') {
                    $_SESSION['tg_auth'] = [
                        'phone_number' => $phone_number,
                        'api_id' => intval($api_id),
                        'api_hash' => $api_hash,
                        'phone_code_hash' => $response['phone_code_hash'],
                        'session_string' => $response['session_string']
                    ];
                    $auth_step = 2;
                    $success_msg = "Verification code sent to your Telegram app!";
                } else {
                    $error_msg = "Telegram Error: " . ($response['message'] ?? $error_output ?: 'Failed to send code.');
                }
            } else {
                $error_msg = "Failed to execute Python authentication helper.";
            }
        }
    } elseif ($action === 'verify_code') {
        $code = trim($_POST['code'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $proxy_server = trim($_POST['proxy_server'] ?? '');
        $schedule_start = $_POST['schedule_start'] ?: null;
        $schedule_end = $_POST['schedule_end'] ?: null;
        $tg = $_SESSION['tg_auth'] ?? [];

        if (empty($code) || empty($tg)) {
            $error_msg = "Verification code is required.";
            $auth_step = 2;
        } else {
            $payload = json_encode([
                "action" => "verify_code",
                "api_id" => $tg['api_id'],
                "api_hash" => $tg['api_hash'],
                "phone_number" => $tg['phone_number'],
                "phone_code_hash" => $tg['phone_code_hash'],
                "code" => $code,
                "session_string" => $tg['session_string'],
                "password" => $password ?: null
            ]);

            $python_path = realpath(__DIR__ . '/../../backend-python/telegram_auth_helper.py');
            $command = "python3 " . escapeshellarg($python_path);
            
            $descriptors = [
                0 => ["pipe", "r"],
                1 => ["pipe", "w"],
                2 => ["pipe", "w"]
            ];
            
            $process = proc_open($command, $descriptors, $pipes);
            if (is_resource($process)) {
                fwrite($pipes[0], $payload);
                fclose($pipes[0]);
                
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $error_output = stream_get_contents($pipes[2]);
                fclose($pipes[2]);
                
                proc_close($process);
                
                $response = json_decode($output, true);
                if ($response && isset($response['status']) && $response['status'] === 'success') {
                    $session_string = $response['session_string'];

                    $stmt = $pdo->prepare("INSERT INTO telegram_accounts (user_id, phone_number, api_id, api_hash, session_string, proxy_server, work_schedule_start, work_schedule_end, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([
                        $user_id, 
                        $tg['phone_number'], 
                        $tg['api_id'], 
                        $tg['api_hash'], 
                        $session_string, 
                        $proxy_server, 
                        $schedule_start, 
                        $schedule_end
                    ]);
                    
                    unset($_SESSION['tg_auth']);
                    $success_msg = "Telegram account authenticated and connected successfully!";
                    $auth_step = 1;
                } elseif ($response && isset($response['status']) && $response['status'] === 'password_required') {
                    $auth_step = 2;
                    $error_msg = "Two-Step Verification (2FA) password is required for this account.";
                } else {
                    $auth_step = 2;
                    $error_msg = "Verification Error: " . ($response['message'] ?? 'Invalid code or expired.');
                }
            } else {
                $error_msg = "Failed to execute Python verification helper.";
                $auth_step = 2;
            }
        }
    } elseif ($action === 'add_account_manual') {
        $phone_number = trim($_POST['phone_number']);
        $api_id = intval($_POST['api_id']);
        $api_hash = trim($_POST['api_hash']);
        $session_string = trim($_POST['session_string']);
        $proxy_server = trim($_POST['proxy_server']);
        $schedule_start = $_POST['schedule_start'] ?: null;
        $schedule_end = $_POST['schedule_end'] ?: null;

        if (!empty($phone_number) && !empty($api_id) && !empty($api_hash) && !empty($session_string)) {
            $stmt = $pdo->prepare("INSERT INTO telegram_accounts (user_id, phone_number, api_id, api_hash, session_string, proxy_server, work_schedule_start, work_schedule_end, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$user_id, $phone_number, $api_id, $api_hash, $session_string, $proxy_server, $schedule_start, $schedule_end]);
            $success_msg = "Telegram account connected successfully!";
        } else {
            $error_msg = "All fields including Session String are required for manual connection.";
        }
    } elseif ($action === 'delete_account') {
        $account_id = intval($_POST['account_id']);
        $stmt = $pdo->prepare("DELETE FROM telegram_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$account_id, $user_id]);
        $success_msg = "Telegram account removed successfully!";
    }
}

$stmt = $pdo->prepare("SELECT * FROM telegram_accounts WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$accounts = $stmt->fetchAll();
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

        <!-- Add Account Card with Tabs -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <h4 class="card-title mb-3">Connect New Telegram Number</h4>
                <p class="text-muted small">Get your <code>API_ID</code> and <code>API_HASH</code> from <a href="https://my.telegram.org" target="_blank">my.telegram.org</a>.</p>

                <ul class="nav nav-tabs mb-4" id="connectTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?= $auth_step == 1 ? 'active' : '' ?>" id="auto-tab" data-bs-toggle="tab" data-bs-target="#auto-connect" type="button" role="tab">Interactive Web Login (Automatic)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="manual-tab" data-bs-toggle="tab" data-bs-target="#manual-connect" type="button" role="tab">Manual Session String</button>
                    </li>
                </ul>

                <div class="tab-content" id="connectTabContent">
                    <!-- Tab 1: Interactive Web Login -->
                    <div class="tab-pane fade <?= $auth_step == 1 ? 'show active' : '' ?>" id="auto-connect" role="tabpanel">
                        <?php if ($auth_step == 1): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="request_code">
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
                                        <button type="submit" class="btn btn-primary">Request Telegram Login Code</button>
                                    </div>
                                </div>
                            </form>
                        <?php elseif ($auth_step == 2): ?>
                            <div class="alert alert-info">A login code has been sent to your Telegram app. Enter it below:</div>
                            <form method="POST">
                                <input type="hidden" name="action" value="verify_code">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Telegram OTP Code</label>
                                        <input type="text" name="code" class="form-control" placeholder="12345" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">2FA Password (If enabled)</label>
                                        <input type="password" name="password" class="form-control" placeholder="Cloud password">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Proxy Server (Optional)</label>
                                        <input type="text" name="proxy_server" class="form-control" placeholder="socks5://user:pass@host:port">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Work Schedule Start (Optional)</label>
                                        <input type="time" name="schedule_start" class="form-control">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Work Schedule End (Optional)</label>
                                        <input type="time" name="schedule_end" class="form-control">
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-success">Verify & Connect Account</button>
                                        <a href="accounts.php" class="btn btn-outline-secondary ms-2">Cancel</a>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <!-- Tab 2: Manual Session String -->
                    <div class="tab-pane fade" id="manual-connect" role="tabpanel">
                        <form method="POST">
                            <input type="hidden" name="action" value="add_account_manual">
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
                                    <textarea name="session_string" class="form-control" rows="2" placeholder="Paste session string token here..." required></textarea>
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
                                    <button type="submit" class="btn btn-success">Link Telegram Account Manually</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
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
                                <?php foreach ($accounts as $acc): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($acc['phone_number']) ?></strong></td>
                                        <td><?= htmlspecialchars($acc['api_id']) ?></td>
                                        <td><span class="badge bg-success"><?= strtoupper($acc['status']) ?></span></td>
                                        <td><?= ($acc['work_schedule_start'] && $acc['work_schedule_end']) ? "{$acc['work_schedule_start']} - {$acc['work_schedule_end']}" : '24/7 Active' ?></td>
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
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
