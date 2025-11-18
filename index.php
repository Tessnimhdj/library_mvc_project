<?php
session_start();

// تحميل Composer إن وجد
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

// تحميل الراوتر وملف المسارات
require_once __DIR__ . '/Core/routing/Router.php';
require_once __DIR__ . '/Core/routing/Routes.php';


spl_autoload_register(function($class){
    $class = str_replace('\\', '/', $class);

    $file = __DIR__ . '/' . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});


// وأخيراً نفّذ الـ dispatch
Router::dispatch();
