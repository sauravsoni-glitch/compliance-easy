<?php
/**
 * Dev smoke check: MySQL connectivity, core tables, router dispatch for /login.
 * Run: php scripts/smoke_check.php   or   composer smoke
 * (Not for production.)
 */
$root = dirname(__DIR__);
chdir($root);
require $root . (is_file($root . '/vendor/autoload.php') ? '/vendor/autoload.php' : '/app/autoload.php');

$ok = 0;
$fail = 0;

// 1) Database
try {
    $c = require $root . '/config/database.php';
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $c['host'],
        $c['port'],
        $c['database'],
        $c['charset']
    );
    $pdo = new PDO($dsn, $c['username'], $c['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $n = (int) $pdo->query('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema = ' . $pdo->quote($c['database']))->fetchColumn();
    echo "[DB] OK — database `{$c['database']}`, tables (information_schema count): {$n}\n";
    $required = ['users', 'organizations', 'roles', 'compliances'];
    $missing = [];
    foreach ($required as $t) {
        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ? AND table_name = ?'
        );
        $st->execute([$c['database'], $t]);
        if ((int) $st->fetchColumn() === 0) {
            $missing[] = $t;
        }
    }
    if ($missing !== []) {
        echo '[DB] WARN — missing tables: ' . implode(', ', $missing) . " (run migrations / schema)\n";
        $fail++;
    } else {
        echo '[DB] OK — core tables present (users, organizations, roles, compliances)' . "\n";
        $ok++;
    }
} catch (Throwable $e) {
    echo '[DB] FAIL — ' . $e->getMessage() . "\n";
    $fail++;
}

// 2) Router dispatch without HTTP (minimal)
try {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/login';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $routes = require $root . '/config/routes.php';
    $router = new App\Core\Router($routes);
    [$controllerName, $action] = $router->dispatch();
    if ($controllerName === 'AuthController' && $action === 'loginPage') {
        echo "[Router] OK — /login → AuthController::loginPage\n";
        $ok++;
    } else {
        echo "[Router] FAIL — unexpected dispatch for /login\n";
        $fail++;
    }
} catch (Throwable $e) {
    echo '[Router] FAIL — ' . $e->getMessage() . "\n";
    $fail++;
}

exit($fail > 0 ? 1 : 0);
