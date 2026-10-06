<?php

require_once __DIR__ . '/../Core/Env.php';
Env::load(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

define('server_name', Env::get('DB_HOST', 'localhost'));
define('user_name', Env::get('DB_USER', 'root'));
define('password', Env::get('DB_PASSWORD', ''));
define('database_name', Env::get('DB_NAME', 'library'));

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);

$uploadDir = __DIR__ . '/../Public/Uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

define('UPLOAD_DIR', realpath($uploadDir) . DIRECTORY_SEPARATOR);
