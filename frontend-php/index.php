<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/db.php';

// Redirect to user dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user/dashboard.php");
    exit;
}

$login_error = $register_error = $register_success = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. User Registration Logic
    if ($action === 'register') {
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($username) || empty($email) || empty($password)) {
            $register_error = "All fields are required.";
        } elseif ($password !== $confirm_password) {
            $register_error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $register_error = "Password must be at least 6 characters long.";
        } else {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $register_error = "Username or Email already taken.";
            } else {
                // Insert new user
                $password_hash = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, role) VALUES (?, ?, ?, 'user')");
                if ($stmt->execute([$username, $email, $password_hash])) {
                    $register_success = "Account created successfully! You can now log in.";
                } else {
                    $register_error = "Registration failed. Please try again.";
                }
            }
        }
    }

    // 2. User Login Logic
    elseif ($action === 'login') {
        $username_email = trim($_POST['username_email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username_email) || empty($password)) {
            $login_error = "Please fill in all fields.";
        } else {
            // Check credentials
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username_email, $username_email]);
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
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login & Registration - TG Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .auth-card {
            width: 100%;
            max-width: 480px;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <div class="auth-card card shadow-lg border-0 bg-white p-4">
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary">TG Automation</h3>
            <p class="text-muted small">Manage Telegram Userbots & AI Outreach</p>
        </div>

        <!-- Tab Controls -->
        <ul class="nav nav-pills nav-justified mb-4" id="authTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active fw-semibold" id="login-tab" data-bs-toggle="tab" data-bs-target="#login" type="button" role="tab">Login</button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link fw-semibold" id="register-tab" data-bs-toggle="tab" data-bs-target="#register" type="button" role="tab">Register</button>
            </li>
        </ul>

        <div class="tab-content" id="authTabsContent">
            <!-- Login Form Tab -->
            <div class="tab-pane fade show active" id="login" role="tabpanel">
                <?php if ($login_error): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($login_error) ?></div>
                <?php endif; ?>
                <?php if ($register_success): ?>
                    <div class="alert alert-success py-2 small"><?= htmlspecialchars($register_success) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Username or Email</label>
                        <input type="text" name="username_email" class="form-control" placeholder="Enter username or email" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Sign In</button>
                </form>
            </div>

            <!-- Registration Form Tab -->
            <div class="tab-pane fade" id="register" role="tabpanel">
                <?php if ($register_error): ?>
                    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($register_error) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="register">
                    <div class="mb-3">
                        <label class="form-label small text-muted">Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Choose a username" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted">Password</label>
                        <input type="password" name="password" class="form-control" placeholder="At least 6 characters" required>
                    </div>
                    <div class="mb-4">
                        <label class="form-label small text-muted">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
                    </div>
                    <button type="submit" class="btn btn-success w-100 py-2 fw-bold">Create Account</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
