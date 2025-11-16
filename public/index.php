
<?php
session_start();
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../app/Controllers/UploadController.php';

$controller = new UploadController();

$base_path = '/mes_projet/library_mvc_project/public';

$uri = str_replace($base_path, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

$uri = str_replace($base_path, '', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$uri = trim($uri, '/');

if ($uri === '' || $uri === 'index.php') {
    $controller->index();
} elseif ($uri === 'import') {
    $controller->import();
} else {
    http_response_code(404);
    echo "404 Not Found";
}