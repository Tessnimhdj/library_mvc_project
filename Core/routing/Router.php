<?php

class Router
{
    private static array $routes = [];

    private static string $basePath = '/mes_projet/library_mvc_project';

    public static function get(string $uri, $action)
    {
        $uri = '/' . ltrim($uri, '/');
        self::$routes['GET'][$uri] = $action;
    }

    public static function post(string $uri, $action)
    {
        $uri = '/' . ltrim($uri, '/');
        self::$routes['POST'][$uri] = $action;
    }

    public static function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = self::normalizeUri($_SERVER['REQUEST_URI']);

        if (!isset(self::$routes[$method][$uri])) {
            http_response_code(404);
            echo "404 - Page Not Found<br>";
            echo "Route not found: $uri";
            return;
        }

        $action = self::$routes[$method][$uri];

        if (is_callable($action)) {
            return $action();
        }

        if (is_string($action) && strpos($action, '@') !== false) {
            list($controller, $methodName) = explode('@', $action);

            $controllerClass = "app\\upload_file\\Controllers\\$controller";

            $controllerFile = self::resolveControllerFile($controllerClass);

            if ($controllerFile && file_exists($controllerFile)) {
                require_once $controllerFile;
            }

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller not found: $controllerClass (expected file: $controllerFile)");
            }

            $obj = new $controllerClass;

            if (!method_exists($obj, $methodName)) {
                throw new Exception("Method $methodName not found in $controllerClass");
            }

            return $obj->$methodName();
        }
    }

    private static function normalizeUri($uri)
    {
        if (str_starts_with($uri, self::$basePath)) {
            $uri = substr($uri, strlen(self::$basePath));
        }

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($path === '' || $path === null) $path = '/';
        if (!str_starts_with($path, '/')) $path = '/' . $path;

        return rtrim($path, '/') ?: '/';
    }

    private static function resolveControllerFile(string $fullyQualifiedClass): ?string
    {
        // افترضنا أن namespace يبدأ بـ app\...
        // ونحوّل الـ namespace إلى مسار نسبي من جذر المشروع
        // Router.php يوجد في Core/routing -> ../../.. للوصول لجذر المشروع
        $projectRoot = realpath(__DIR__ . '/../../..');

        // استبدال "\" ب "/" ثم إضافة .php
        $relative = str_replace('\\', '/', $fullyQualifiedClass) . '.php';

        // غالباً المسار المطلوب هو: <projectRoot>/app/upload_file/Controllers/UploadController.php
        $full = $projectRoot . '/' . $relative;

        return $full;
    }
}
