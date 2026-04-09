<?php
$appConfig = require dirname(__DIR__) . '/config/app.php';
$isDebug = !empty($appConfig['debug']);
error_reporting(E_ALL);
ini_set('display_errors', $isDebug ? '1' : '0');

// PHP built-in server: serve existing files (CSS, JS, images), route the rest to the app
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path !== '/' && $path !== '' && is_file(__DIR__ . $path)) {
        return false;
    }
}

define('ROOT_PATH', dirname(__DIR__));
if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
} else {
    require ROOT_PATH . '/app/autoload.php';
}

$routes = require ROOT_PATH . '/config/routes.php';
$router = new \App\Core\Router($routes);
[$controllerName, $action, $params] = $router->dispatch();

// Security headers for browser hardening.
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://esm.sh; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; img-src 'self' data: blob:; connect-src 'self'; frame-ancestors 'self'; base-uri 'self'; form-action 'self';");

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!\App\Core\Csrf::validateRequest()) {
        http_response_code(419);
        header('Content-Type: text/html; charset=UTF-8');
        echo 'Security validation failed. Please refresh the page and try again.';
        exit;
    }
}

if (\App\Core\Auth::check()) {
    try {
        \App\Core\Auth::syncRoleFromDatabase(\App\Core\Database::getConnection());
        $u = \App\Core\Auth::user();
        if (($u['status'] ?? '') === 'inactive') {
            \App\Core\Auth::logout();
            $pre = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
            $pre = ($pre !== '/' && $pre !== '.' && $pre !== '') ? rtrim($pre, '/') : '';
            header('Location: ' . $pre . '/login', true, 302);
            exit;
        }
    } catch (\Throwable $e) {
        // DB unavailable or missing column — continue without sync
    }
}

$controllerClass = 'App\\Controllers\\' . $controllerName;
if (!class_exists($controllerClass)) {
    $controllerClass = 'App\\Controllers\\ErrorController';
    $action = 'notFound';
    $params = [];
}

$controller = new $controllerClass();
if (!method_exists($controller, $action)) {
    $controller = new \App\Controllers\ErrorController();
    $action = 'notFound';
    $params = [];
}
$controller->{$action}(...array_values($params));
