<?php

namespace Core;

class Log
{
    public static function error(string $message): void
    {
        error_log($message);

        $directory = __DIR__ . '/logs';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents(
            $directory . '/errors.log',
            '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL,
            FILE_APPEND
        );
    }
}
