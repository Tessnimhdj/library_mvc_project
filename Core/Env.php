<?php

class Env
{
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            $value = trim(substr($line, $separator + 1));

            if ($key === '') {
                continue;
            }

            if (
                (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
                (str_starts_with($value, "'") && str_ends_with($value, "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }

        $value = getenv($key);
        if ($value === false) {
            return $default;
        }

        return (string) $value;
    }
}
