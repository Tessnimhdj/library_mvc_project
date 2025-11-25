<?php

class Router
{
    private static array $routes = [];
    private static ?string $basePath = null;
    private static bool $autoRouting = true;

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

    public static function enableAutoRouting(bool $enable = true)
    {
        self::$autoRouting = $enable;
    }

    public static function dispatch()
    {
        self::$basePath = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        $method = $_SERVER['REQUEST_METHOD'];
        $uri = self::normalizeUri($_SERVER['REQUEST_URI']);

        // أولاً: التحقق من Routes المسجلة يدوياً
        if (isset(self::$routes[$method][$uri])) {
            $action = self::$routes[$method][$uri];
            return self::executeAction($action);
        }

        // ثانياً: التوجيه التلقائي
        if (self::$autoRouting) {
            return self::autoRoute($uri, $method);
        }

        // إذا لم يتم العثور على route
        self::show404($uri);
    }

    private static function autoRoute(string $uri, string $method)
    {
        // إزالة الـ slash الأول
        $uri = trim($uri, '/');
        
        // إذا كان الـURI فارغاً، استخدم controller افتراضي
        if (empty($uri)) {
            $controllerName = 'UploadController';
            $methodName = 'index';
        } else {
            // تقسيم الـURI إلى أجزاء
            $segments = explode('/', $uri);
            
            // الجزء الأول = اسم الـController
            $controllerName = ucfirst($segments[0]) . 'Controller';
            
            // الجزء الثاني = اسم الـMethod (أو index افتراضياً)
            $methodName = isset($segments[1]) && !empty($segments[1]) ? $segments[1] : 'index';
            
            // تحويل method name من kebab-case إلى camelCase
            // مثال: upload-file => uploadFile
            $methodName = lcfirst(str_replace('-', '', ucwords($methodName, '-')));
        }

        // البحث عن الـController في جميع المجلدات
        $controllerClass = self::findController($controllerName);

        if (!$controllerClass) {
            self::show404($uri, "Controller not found: $controllerName in any app folder");
            return;
        }

        // تحميل ملف الـController
        $controllerFile = self::resolveControllerFile($controllerClass);
        
        if (!$controllerFile || !file_exists($controllerFile)) {
            self::show404($uri, "Controller file not found: $controllerFile");
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerClass)) {
            self::show404($uri, "Controller class not found: $controllerClass");
            return;
        }

        $controller = new $controllerClass;

        // التحقق من وجود الـMethod
        if (!method_exists($controller, $methodName)) {
            self::show404($uri, "Method '$methodName' not found in $controllerClass");
            return;
        }

        // تنفيذ الـMethod
        return $controller->$methodName();
    }

    private static function executeAction($action)
    {
        if (is_callable($action)) {
            return $action();
        }

        if (is_string($action) && strpos($action, '@') !== false) {
            $parts = explode('@', $action);

            if (count($parts) === 2) {
                list($controller, $methodName) = $parts;
                $controllerClass = self::findController($controller);

                if (!$controllerClass) {
                    throw new Exception("Controller not found: $controller");
                }
            } elseif (count($parts) === 3) {
                list($folder, $controller, $methodName) = $parts;
                $controllerClass = "app\\$folder\\Controllers\\$controller";
            } else {
                throw new Exception("Invalid action format: $action");
            }

            $controllerFile = self::resolveControllerFile($controllerClass);

            if ($controllerFile && file_exists($controllerFile)) {
                require_once $controllerFile;
            } else {
                throw new Exception("Controller file not found: $controllerFile");
            }

            if (!class_exists($controllerClass)) {
                throw new Exception("Controller class not found: $controllerClass");
            }

            $obj = new $controllerClass;

            if (!method_exists($obj, $methodName)) {
                throw new Exception("Method $methodName not found in $controllerClass");
            }

            return $obj->$methodName();
        }
    }

    private static function findController(string $controllerName): ?string
    {
        $projectRoot = realpath(__DIR__ . '/../..');
        $appPath = $projectRoot . '/app';

        if (!is_dir($appPath)) {
            return null;
        }

        $folders = array_filter(glob($appPath . '/*'), 'is_dir');

        foreach ($folders as $folderPath) {
            $folderName = basename($folderPath);
            $controllerFile = $folderPath . '/Controllers/' . $controllerName . '.php';

            if (file_exists($controllerFile)) {
                return "app\\$folderName\\Controllers\\$controllerName";
            }
        }

        return null;
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
        $projectRoot = realpath(__DIR__ . '/../..');
        $relative = str_replace('\\', '/', $fullyQualifiedClass) . '.php';
        $full = $projectRoot . '/' . $relative;
        return $full;
    }

    private static function show404(string $uri, string $message = null)
    {
        http_response_code(404);
        echo "404 - Page Not Found<br>";
        echo "Route not found: $uri<br>";
        if ($message) {
            echo "Details: $message";
        }
    }
}