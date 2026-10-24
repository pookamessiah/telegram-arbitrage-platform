<?php
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$file = __DIR__ . $uri;

// If a directory is requested (e.g., /user/), look for index.php or dashboard.php
if (is_dir($file)) {
    if (file_exists($file . '/index.php')) {
        include $file . '/index.php';
        exit;
    } elseif (file_exists($file . '/dashboard.php')) {
        include $file . '/dashboard.php';
        exit;
    }
}

// If the requested file exists, serve it normally
if (file_exists($file) && !is_dir($file)) {
    return false;
}

// Default fallback for clean requests
if ($uri === '/' || $uri === '') {
    include 'index.php';
    exit;
}

http_response_code(404);
echo "<div style='font-family:sans-serif; padding:40px; text-align:center;'>
    <h2>404 Not Found</h2>
    <p>The requested resource <b>" . htmlspecialchars($uri) . "</b> was not found on this server.</p>
    <a href='/user/dashboard.php'>Return to Dashboard</a>
</div>";
