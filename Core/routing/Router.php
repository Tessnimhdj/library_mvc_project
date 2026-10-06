<?php

class Router
{
    private static ?string $basePath = null;

    public static function dispatch()
    {
        self::$basePath = self::detectBasePath();
        $uri = self::normalizeUri($_SERVER['REQUEST_URI']);

        try {
            self::autoRoute($uri);
        } catch (\Throwable $e) {
            \Core\Log::error($e->getMessage());
            \Core\View::renderError(500, 'Something went wrong. Please try again.');
        }
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
            $controllerSegment = 'Book';
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

    private static function isValidSegment(string $segment): bool
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $segment) === 1;
    }

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

    private static function show404(string $uri): void
    {
        \Core\Log::error('404 Error - Route not found: ' . $uri);
        \Core\View::renderError(404, 'Page not found.');
    }
}
