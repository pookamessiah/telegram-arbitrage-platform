<?php
require_once __DIR__ . '/../auth.php';
check_admin();

// Fetch System Overview Metrics
$total_users = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_tg_accounts = $pdo->query("SELECT COUNT(*) FROM telegram_accounts")->fetchColumn();
$active_tg_accounts = $pdo->query("SELECT COUNT(*) FROM telegram_accounts WHERE status = 'active'")->fetchColumn();
$total_prospects = $pdo->query("SELECT COUNT(*) FROM prospects")->fetchColumn();
$arbitrage_users = $pdo->query("SELECT COUNT(*) FROM users WHERE arbitrage_access = 1")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - TG Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold text-warning" href="#">TG Admin Panel</a>
            <div class="d-flex align-items-center">
                <a href="../user/dashboard.php" class="btn btn-sm btn-outline-light me-2">User View</a>
                <a href="../logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container py-3">
        <h2 class="mb-4">System Overview</h2>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="text-muted">Total Users</h6>
                        <h3 class="text-primary mb-0"><?= $total_users ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="text-muted">Connected TG Accounts</h6>
                        <h3 class="text-success mb-0"><?= $active_tg_accounts ?> / <?= $total_tg_accounts ?> Active</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="text-muted">Discovered Leads</h6>
                        <h3 class="text-info mb-0"><?= $total_prospects ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <h6 class="text-muted">Arbitrage Enabled Users</h6>
                        <h3 class="text-warning mb-0"><?= $arbitrage_users ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h4>Arbitrage Controls</h4>
                            <p class="text-muted">Manage individual or global permissions for the Crypto and Betting Arbitrage outreach engine.</p>
                        </div>
                        <a href="arbitrage_control.php" class="btn btn-warning fw-bold">Manage Arbitrage Access &rarr;</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card shadow-sm h-100">
                    <div class="card-body d-flex flex-column justify-content-between">
                        <div>
                            <h4>User Account Management</h4>
                            <p class="text-muted">View user accounts, adjust roles, or manage system-wide settings.</p>
                        </div>
                        <a href="users.php" class="btn btn-primary fw-bold">Manage Users &rarr;</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
