<?php

class Router
{
    private static ?string $basePath = null;

    public static function dispatch()
    {
        self::$basePath = self::detectBasePath();
        $uri = self::normalizeUri($_SERVER['REQUEST_URI']);
        self::autoRoute($uri);
    }

    public static function basePath(): string
    {
        if (self::$basePath === null) {
            self::$basePath = self::detectBasePath();
        }

        return self::$basePath;
    }

    public static function url(string $path = '/'): string
    {
        $base = self::basePath();
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base . '/';
        }

        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }

    private static function detectBasePath(): string
    {
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        return rtrim($basePath, '/');
    }

    private static function autoRoute(string $uri): void
    {
        $uri = trim($uri, '/');

        if ($uri === '') {
            $controllerName = 'UploadController';
            $methodName = 'index';
        } else {
            $segments = explode('/', $uri);
            $controllerName = ucfirst($segments[0]) . 'Controller';
            $methodName = isset($segments[1]) && $segments[1] !== '' ? $segments[1] : 'index';
            $methodName = lcfirst(str_replace('-', '', ucwords($methodName, '-')));
        }

        $controllerInfo = self::findController($controllerName);
        if (!$controllerInfo) {
            self::show404($uri, "Controller not found: $controllerName");
            return;
        }

        require_once $controllerInfo['file'];

        $controllerClass = $controllerInfo['class'];
        if (!class_exists($controllerClass)) {
            self::show404($uri, "Controller class not found: $controllerClass");
            return;
        }

        $controller = new $controllerClass;

        if (!self::isRoutableMethod($controller, $methodName)) {
            self::show404($uri, "Method '$methodName' not found in $controllerClass");
            return;
        }

        $controller->$methodName();
    }

    private static function isRoutableMethod(object $controller, string $methodName): bool
    {
        if ($methodName === '' || str_starts_with($methodName, '__') || !method_exists($controller, $methodName)) {
            return false;
        }

        $method = new ReflectionMethod($controller, $methodName);

        return $method->isPublic();
    }

    private static function findController(string $controllerName): ?array
    {
        $projectRoot = realpath(__DIR__ . '/../..');
        $appPath = $projectRoot . '/app';
        $searchedPaths = [];

        if (is_dir($appPath)) {
            $folders = array_filter(glob($appPath . '/*'), 'is_dir');
            foreach ($folders as $folderPath) {
                $folderName = basename($folderPath);
                $controllerFile = $folderPath . '/Controllers/' . $controllerName . '.php';
                $searchedPaths[] = $controllerFile;

                if (file_exists($controllerFile)) {
                    return [
                        'class' => "app\\$folderName\\Controllers\\$controllerName",
                        'file' => $controllerFile
                    ];
                }
            }
        }

        self::logSearchedPaths($controllerName, $searchedPaths);

        return null;
    }

    private static function logSearchedPaths(string $controllerName, array $paths)
    {
        $logFile = __DIR__ . '/../logs/errors.log';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }

        $logMessage = "[" . date('Y-m-d H:i:s') . "] Searching for $controllerName in:\n";
        foreach ($paths as $path) {
            $exists = file_exists($path) ? 'EXISTS' : 'NOT FOUND';
            $logMessage .= "  - [$exists] $path\n";
        }

        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
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

    private static function show404(string $uri, string $message = null)
    {
        http_response_code(404);

        echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <title>Page Not Found</title>
    <style>
        body {
            background-color: #f2f2f2;
            font-family: Arial, sans-serif;
            text-align: center;
            padding-top: 100px;
            color: #333;
        }
        .container {
            background-color: #fff;
            display: inline-block;
            padding: 40px 60px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 48px;
            margin-bottom: 20px;
            color: #e74c3c;
        }
        p {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>404</h1>
        <p>Page not found.</p>
    </div>
</body>
</html>";

        $logFile = __DIR__ . '/../logs/errors.log';
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($logFile), 0755, true);
        }

        $logMessage  = "[" . date('Y-m-d H:i:s') . "] 404 Error - Route not found: $uri";
        if ($message) {
            $logMessage .= " | Details: $message";
        }

        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }
}