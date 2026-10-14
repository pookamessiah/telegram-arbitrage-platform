<?php
require_once __DIR__ . '/../auth.php';
check_auth();

$user_id = $_SESSION['user_id'];
$success_msg = $error_msg = "";

// Handle Template Creation / Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'create') {
        $template_name = trim($_POST['template_name']);
        $tone_scale = intval($_POST['tone_scale']);
        $interests = trim($_POST['interests']);
        $endpoint_type = $_POST['endpoint_type'];
        $endpoint_value = trim($_POST['endpoint_value']);
        $arbitrage_mode = isset($_POST['arbitrage_mode']) ? 1 : 0;

        if (!empty($template_name) && !empty($interests)) {
            $stmt = $pdo->prepare("INSERT INTO templates (user_id, template_name, tone_scale, interests, endpoint_type, endpoint_value, arbitrage_mode) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $template_name, $tone_scale, $interests, $endpoint_type, $endpoint_value, $arbitrage_mode]);
            $success_msg = "Template saved successfully!";
        } else {
            $error_msg = "Template Name and Interests are required.";
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete') {
        $template_id = intval($_POST['template_id']);
        $stmt = $pdo->prepare("DELETE FROM templates WHERE id = ? AND user_id = ?");
        $stmt->execute([$template_id, $user_id]);
        $success_msg = "Template deleted successfully!";
    }
}

// Fetch user's existing templates
$stmt = $pdo->prepare("SELECT * FROM templates WHERE user_id = ? ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$templates = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Behavior Templates - Telegram Automation</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Reusable Behavior Templates</h2>
            <a href="dashboard.php" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

        <?php if ($success_msg): ?><div class="alert alert-success"><?= htmlspecialchars($success_msg) ?></div><?php endif; ?>
        <?php if ($error_msg): ?><div class="alert alert-danger"><?= htmlspecialchars($error_msg) ?></div><?php endif; ?>

        <!-- Create Template Form -->
        <div class="card shadow-sm mb-5">
            <div class="card-body">
                <h4 class="card-title mb-3">Create New Template</h4>
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Template Name</label>
                            <input type="text" name="template_name" class="form-control" placeholder="e.g., Crypto Outreach v1" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Tone Scale (1 = Friendly, 10 = Professional): <span id="toneVal">5</span></label>
                            <input type="range" name="tone_scale" class="form-range" min="1" max="10" value="5" oninput="document.getElementById('toneVal').innerText = this.value">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Target Interests / Keywords (comma separated)</label>
                            <input type="text" name="interests" class="form-control" placeholder="crypto, arbitrage, trading, bitcoin, signals" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Endpoint Type</label>
                            <select name="endpoint_type" class="form-select">
                                <option value="nothing">Do Nothing / Just Chat</option>
                                <option value="dm">Direct Message (DM)</option>
                                <option value="telegram_platform">Telegram Platform / Group Link</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Endpoint Value (Group Link or Target Handle)</label>
                            <input type="text" name="endpoint_value" class="form-control" placeholder="https://t.me/your_channel_link">
                        </div>
                        <div class="col-md-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="arbitrage_mode" value="1" id="arbCheck">
                                <label class="form-check-label" for="arbCheck">Enable Crypto/Betting Arbitrage Convincing Mode</label>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Save Template</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Template List Table -->
        <div class="card shadow-sm">
            <div class="card-body">
                <h4 class="card-title mb-3">Your Saved Templates</h4>
                <div class="table-responsive">
                    <table class="table table-striped align-middle">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Tone Scale</th>
                                <th>Interests</th>
                                <th>Endpoint</th>
                                <th>Arbitrage Mode</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($templates)): ?>
                                <tr><td colspan="6" class="text-center text-muted">No templates created yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($templates as $t): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($t['template_name']) ?></strong></td>
                                        <td><?= $t['tone_scale'] ?>/10</td>
                                        <td><?= htmlspecialchars($t['interests']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= strtoupper($t['endpoint_type']) ?></span></td>
                                        <td><?= $t['arbitrage_mode'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Off</span>' ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="template_id" value="<?= $t['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this template?')">Delete</button>
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
