<?php

define("server_name" , "localhost");
define("user_name", "root");
define("password", "");
define("database_name", "library");

define('MAX_UPLOAD_BYTES', 5 * 1024 * 1024);
define('ALLOWED_MIMES', [
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-excel'
]);

define('UPLOAD_DIR', __DIR__ . '/../../uploads/');

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}
