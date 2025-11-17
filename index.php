
<?php

session_start();
require_once __DIR__ . '/Core/Router.php';
require_once __DIR__ . '/app/upload_file/Controllers/UploadController.php';
require_once __DIR__ . '/Routes.php';

Router::run();
