<?php

namespace Core;

class View
{
    public static function render(string $viewFile, array $data = [], array $options = []): void
    {
        $status = (int) ($options['status'] ?? 200);
        $activeNav = isset($options['nav']) && is_string($options['nav']) ? $options['nav'] : '';

        extract($data, EXTR_SKIP);

        $pageTitle = '';
        $pageHead = '';
        $content = '';

        if (is_file($viewFile)) {
            try {
                ob_start();
                include $viewFile;
                $content = (string) ob_get_clean();
            } catch (\Throwable $e) {
                if (ob_get_level() > 0) {
                    ob_end_clean();
                }
                Log::error($e->getMessage());
                $status = $status >= 400 ? $status : 500;
                $content = self::errorMarkup($status, 'Something went wrong. Please try again.');
            }
        } else {
            Log::error('View fragment is missing');
            $status = $status >= 400 ? $status : 500;
            $content = self::errorMarkup($status, 'Something went wrong. Please try again.');
        }

        $defined = get_defined_vars();
        if (isset($defined['pageTitle']) && is_string($defined['pageTitle'])) {
            $pageTitle = $defined['pageTitle'];
        }
        if (isset($defined['pageHead']) && is_string($defined['pageHead'])) {
            $pageHead = $defined['pageHead'];
        }
        if (isset($options['title']) && is_string($options['title'])) {
            $pageTitle = $options['title'];
        }

        $title = $pageTitle;

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: DENY');
            header('Referrer-Policy: strict-origin-when-cross-origin');
        }

        include __DIR__ . '/layout/main.php';
    }

    public static function basePath(): string
    {
        $basePath = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));

        return rtrim($basePath, '/');
    }

    public static function url(string $path = ''): string
    {
        return self::basePath() . '/' . ltrim($path, '/');
    }

    public static function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    public static function renderError(int $status, string $message): void
    {
        self::render(__DIR__ . '/layout/error.php', [
            'errorStatus' => $status,
            'errorMessage' => $message,
        ], [
            'status' => $status,
        ]);
    }

    private static function errorMarkup(int $status, string $message): string
    {
        return '<section class="error-block"><p class="error-code">'
            . self::e((string) $status)
            . '</p><h1>'
            . self::e($message)
            . '</h1><a class="error-link" href="'
            . self::e(self::url('book'))
            . '">Back to books</a></section>';
    }
}
