<?php

$fromEnv = static function (string $key, string $default): string {
    $v = getenv($key);
    if ($v === false || $v === '') {
        return $default;
    }
    return $v;
};

return [
    'host'     => $fromEnv('DB_HOST', '127.0.0.1'),
    'port'     => (int) $fromEnv('DB_PORT', '3306'),
    'database' => $fromEnv('DB_DATABASE', 'compliance_saas'),
    'username' => $fromEnv('DB_USERNAME', 'root'),
    'password' => $fromEnv('DB_PASSWORD', ''),
    'charset'  => 'utf8mb4',
];
