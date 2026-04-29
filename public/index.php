<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PHP built-in server: serve existing files (CSS, JS, images), route the rest to the app
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($path !== '/' && $path !== '' && is_file(__DIR__ . $path)) {
        return false;
    }
}

define('ROOT_PATH', dirname(__DIR__));

// Load .env file if present (simple key=value parser, no external lib needed)
$envFile = ROOT_PATH . '/.env';
if (is_file($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$key, $val] = explode('=', $line, 2);
        $key = trim($key);
        $val = trim($val);
        // Strip surrounding quotes
        if (strlen($val) >= 2 && in_array($val[0], ['"', "'"], true) && $val[-1] === $val[0]) {
            $val = substr($val, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
        }
    }
}

if (is_file(ROOT_PATH . '/vendor/autoload.php')) {
    require ROOT_PATH . '/vendor/autoload.php';
} else {
    require ROOT_PATH . '/app/autoload.php';
}

\App\Core\MailIstTime::ensureDefaultTimezone();

use App\Core\Router;
use App\Core\Auth;
use App\Core\Database;

$routes = require ROOT_PATH . '/config/routes.php';
$router = new Router($routes);
[$controllerName, $action, $params] = $router->dispatch();

if (Auth::check()) {
    try {
        Auth::syncRoleFromDatabase(Database::getConnection());
        $u = Auth::user();
        if (($u['status'] ?? '') === 'inactive') {
            Auth::logout();
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
