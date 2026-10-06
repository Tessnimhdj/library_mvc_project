<?php
session_start();

require_once __DIR__ . '/Core/Env.php';
Env::load(__DIR__ . DIRECTORY_SEPARATOR . '.env');

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/Core/routing/Router.php';


spl_autoload_register(function($class){
    $class = str_replace('\\', '/', $class);

    $file = __DIR__ . '/' . $class . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});


Router::dispatch();
