<?php

include_once __DIR__ . '/app/upload_file/Controllers/UploadController.php';


$controller1 = new UploadController();


Router::get('/', function () {
    $controller1 = new UploadController();
    $controller1->index();
});

Router::get('/import', function () {
    $controller1 = new UploadController();
    $controller1->import();
});
