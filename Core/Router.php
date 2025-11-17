<?php

class Router
{
    private static $routes = [
        'GET' => [],
        'POST' => []
    ];

    public static function get($path, $callback)
    {
        self::$routes['GET'][$path] = $callback;
    }

    public static function post($path, $callback)
    {
        self::$routes['POST'][$path] = $callback;
    }

    public static function run()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);


        $uri = rtrim($uri, '/');      
        if ($uri === '') {            
            $uri = '/';               
        }

        if (isset(self::$routes[$method][$uri])) {
            call_user_func(self::$routes[$method][$uri]);
        } else {
            http_response_code(404);
            echo "404 - Route Not Found";
        }
    }
}
