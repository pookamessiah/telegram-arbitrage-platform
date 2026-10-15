<?php
// Enable strict error reporting so errors show on screen
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db.php';

$login_error = $register_error = $register_success = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Registration Handler
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $register_error = "All fields are required.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
                $stmt->execute([$email, $username]);
                if ($stmt->fetch()) {
                    $register_error = "Username or Email is already registered.";
                } else {
                    $password_hash = password_hash($password, PASSWORD_BCRYPT);
                    $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, arbitrage_access) VALUES (?, ?, ?, 'user', 0)");
                    if ($stmt->execute([$username, $email, $password_hash])) {
                        $register_success = "Account created successfully! You can now log in.";
                    } else {
                        $register_error = "Database insertion failed.";
                    }
                }
            } catch (PDOException $e) {
                $register_error = "SQL Error: " . $e->getMessage();
            }
        }
    } 
    // Login Handler
    elseif ($action === 'login') {
        $identity = trim($_POST['identity'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($identity) || empty($password)) {
            $login_error = "Please enter both credentials.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
                $stmt->execute([$identity, $identity]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    header("Location: user/dashboard.php");
                    exit;
                } else {
                    $login_error = "Invalid username/email or password.";
                }
            } catch (PDOException $e) {
                $login_error = "SQL Error: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TG Automation Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center justify-content-center vh-100">
    <div class="card shadow-sm p-4" style="width: 100%; max-width: 450px;">
        <h3 class="text-center mb-3 text-primary fw-bold">TG Automation</h3>
        
        <ul class="nav nav-tabs nav-justified mb-3" id="authTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Login</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Register</button>
            </li>
        </ul>

        <div class="tab-content" id="authTabContent">
            <!-- Login Tab -->
            <div class="tab-pane fade show active" id="login" role="tabpanel">
                <?php if ($login_error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($login_error) ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Username or Email</label>
                        <input type="text" name="identity" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Sign In</button>
                </form>
            </div>

            <!-- Register Tab -->
            <div class="tab-pane fade" id="register" role="tabpanel">
                <?php if ($register_error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($register_error) ?></div><?php endif; ?>
                <?php if ($register_success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($register_success) ?></div><?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Create Account</button>
                </form>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
