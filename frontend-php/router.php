<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// Explicitly map /user, /user/, or /user/index.php to dashboard.php
if ($uri === '/user' || $uri === '/user/' || $uri === '/user/index.php') {
    if (file_exists(__DIR__ . '/user/dashboard.php')) {
        require __DIR__ . '/user/dashboard.php';
        exit;
    }
}

// If a directory is requested, check for dashboard.php or index.php
if (is_dir($file)) {
    if (file_exists($file . '/dashboard.php')) {
        include $file . '/dashboard.php';
        exit;
    } elseif (file_exists($file . '/index.php')) {
        include $file . '/index.php';
        exit;
    }
}

// If the exact file exists, serve it normally
if (file_exists($file) && !is_dir($file)) {
    return false;
}

// If someone requests a path without .php (e.g. /user/accounts), automatically append .php
if (file_exists($file . '.php') && !is_dir($file . '.php')) {
    include $file . '.php';
    exit;
}

// Default root fallback
if ($uri === '/' || $uri === '') {
    if (file_exists(__DIR__ . '/index.php')) {
        include 'index.php';
        exit;
    }
}

http_response_code(404);
echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'>
    <h2>404 Not Found</h2>
    <p>The requested resource <b>" . htmlspecialchars($uri) . "</b> was not found on this server.</p>
    <a href='/user/dashboard.php' style='display:inline-block; margin-top:15px; padding:10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Return to Dashboard</a>
</div>";
