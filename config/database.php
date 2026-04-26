<?php
return [
    'host'     => getenv('DB_HOST')     ?: '127.0.0.1',
    'port'     => (int) (getenv('DB_PORT') ?: 3306),
    'database' => getenv('DB_DATABASE') ?: 'compliance_saas',
    'username' => getenv('DB_USERNAME') ?: 'root',
    'password' => (($p = getenv('DB_PASSWORD')) !== false) ? $p : '',
    'charset'  => 'utf8mb4',
];
