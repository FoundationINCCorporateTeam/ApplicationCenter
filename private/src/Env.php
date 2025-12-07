<?php
/**
 * Environment Configuration Loader
 * 
 * Loads environment variables from .env file into $_ENV
 */
class Env {
    private static $loaded = false;

    /**
     * Load environment variables from .env file
     * 
     * @param string|null $path Absolute path to .env file
     * @return bool
     */
    public static function load($path = null) {
        if (self::$loaded) return true;

        // Use absolute path inside allowed open_basedir
        if ($path === null) {
            $path = '/home/NolanI/web/bulletproof.astroyds.com/private/.env';
        }

        if (!file_exists($path)) {
            return false;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line || $line[0] === '#') continue;

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Remove surrounding quotes
                if (preg_match('/^(["\'])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
        return true;
    }

    public static function get($key, $default = null) {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function has($key) {
        return isset($_ENV[$key]) || getenv($key) !== false;
    }
}
