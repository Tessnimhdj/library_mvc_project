<?php

/**
 * Maps a request URI to one public controller action under app/.
 */
class Router
{
    private static ?string $basePath = null;

    /**
     * Normalizes the request URI and dispatches it.
     */
    public static function dispatch()
    {
        self::$basePath = self::detectBasePath();
        $uri = self::normalizeUri($_SERVER['REQUEST_URI']);

        try {
            self::autoRoute($uri);
        } catch (\Throwable $e) {
            self::show404($uri);
        }
    }

    /**
     * Returns the application base path.
     */
    public static function basePath(): string
    {
        if (self::$basePath === null) {
            self::$basePath = self::detectBasePath();
        }

        return self::$basePath;
    }

    /**
     * Builds an absolute path from the application base path.
     */
    public static function url(string $path = '/'): string
    {
        $base = self::basePath();
        if ($path === '' || $path === '/') {
            return $base === '' ? '/' : $base . '/';
        }

        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }

    /**
     * Reads the directory that contains the front controller.
     */
    private static function detectBasePath(): string
    {
        $basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        return rtrim($basePath, '/');
    }

    /**
     * Resolves the controller and action. The home URI calls UploadController::index.
     */
    private static function autoRoute(string $uri): void
    {
        $uri = trim($uri, '/');

        if ($uri === '') {
            $controllerSegment = 'Upload';
            $methodName = 'index';
        } else {
            $segments = explode('/', $uri);
            $controllerSegment = $segments[0];
            $methodSegment = (isset($segments[1]) && $segments[1] !== '') ? $segments[1] : 'index';

            if (!self::isValidSegment($controllerSegment) || !self::isValidSegment($methodSegment)) {
                self::show404($uri);
                return;
            }

            $methodName = lcfirst(str_replace('-', '', ucwords($methodSegment, '-')));
        }

        if (!self::isValidSegment($controllerSegment) || !self::isValidSegment($methodName)) {
            self::show404($uri);
            return;
        }

        $controllerName = ucfirst($controllerSegment) . 'Controller';
        if (!self::isValidSegment($controllerName)) {
            self::show404($uri);
            return;
        }

        $controllerInfo = self::findController($controllerName);
        if ($controllerInfo === null) {
            self::show404($uri);
            return;
        }

        require_once $controllerInfo['file'];

        $controllerClass = $controllerInfo['class'];
        if (!class_exists($controllerClass)) {
            self::show404($uri);
            return;
        }

        $classFile = (new \ReflectionClass($controllerClass))->getFileName();
        $classReal = $classFile === false ? false : realpath($classFile);
        if ($classReal === false || str_replace('\\', '/', $classReal) !== $controllerInfo['file']) {
            self::show404($uri);
            return;
        }

        $controller = new $controllerClass();

        if (!self::isRoutableMethod($controller, $methodName)) {
            self::show404($uri);
            return;
        }

        $controller->$methodName();
    }

    /**
     * Accepts a segment that starts with a letter and then uses only letters, digits, or underscores.
     */
    private static function isValidSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $segment) === 1;
    }

    /**
     * Finds a controller file under app/{feature}/Controllers and rejects paths outside app/.
     */
    private static function findController(string $controllerName): ?array
    {
        $projectRoot = realpath(__DIR__ . '/../..');
        if ($projectRoot === false) {
            return null;
        }

        $appRoot = realpath($projectRoot . DIRECTORY_SEPARATOR . 'app');
        if ($appRoot === false) {
            return null;
        }

        $appRoot = rtrim(str_replace('\\', '/', $appRoot), '/');
        $folders = glob($appRoot . '/*', GLOB_ONLYDIR);
        if ($folders === false) {
            return null;
        }

        foreach ($folders as $folderPath) {
            $candidate = $folderPath . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . $controllerName . '.php';
            $resolved = realpath($candidate);
            if ($resolved === false) {
                continue;
            }

            $resolved = str_replace('\\', '/', $resolved);
            $expectedSuffix = '/Controllers/' . $controllerName . '.php';
            if (!str_starts_with($resolved, $appRoot . '/') || !str_ends_with($resolved, $expectedSuffix)) {
                continue;
            }

            $folderName = basename(str_replace('\\', '/', $folderPath));

            return [
                'class' => "app\\$folderName\\Controllers\\$controllerName",
                'file' => $resolved
            ];
        }

        return null;
    }

    /**
     * Allows a public instance method declared on the controller class itself.
     */
    private static function isRoutableMethod(object $controller, string $methodName): bool
    {
        if (!self::isValidSegment($methodName) || str_starts_with($methodName, '__')) {
            return false;
        }

        try {
            $method = new \ReflectionMethod($controller, $methodName);
        } catch (\ReflectionException $e) {
            return false;
        }

        return $method->isPublic()
            && !$method->isStatic()
            && !$method->isConstructor()
            && !$method->isDestructor()
            && $method->getDeclaringClass()->getName() === get_class($controller);
    }

    /**
     * Removes the application base path and the query string from the request URI.
     */
    private static function normalizeUri($uri)
    {
        if (self::$basePath !== '' && str_starts_with($uri, self::$basePath)) {
            $uri = substr($uri, strlen(self::$basePath));
        }

        $path = parse_url($uri, PHP_URL_PATH) ?: '/';

        if ($path === '' || $path === null) {
            $path = '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }

        return rtrim($path, '/') ?: '/';
    }

    /**
     * Sends a generic 404 response and records the URI without internal details.
     */
    private static function show404(string $uri): void
    {
        if (!headers_sent()) {
            http_response_code(404);
        }

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
        $directory = dirname($logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $logMessage = "[" . date('Y-m-d H:i:s') . "] 404 Error - Route not found: " . $uri;
        file_put_contents($logFile, $logMessage . PHP_EOL, FILE_APPEND);
    }
}
