<?php

if (!function_exists('env_value')) {
    /** 读取环境变量，兼容 .env 与系统环境变量。 */
    function env_value($key, $default = null)
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
        return $value !== false && $value !== null ? $value : $default;
    }
}

if (!function_exists('env_bool')) {
    /** 读取布尔型环境变量。 */
    function env_bool($key, $default = false)
    {
        return filter_var(env_value($key, $default), FILTER_VALIDATE_BOOLEAN);
    }
}

if (!function_exists('env_list')) {
    /** 读取逗号分隔的环境变量列表。 */
    function env_list($key, array $default = array())
    {
        $value = env_value($key, null);
        if ($value === null || $value === false) {
            return $default;
        }

        $items = array_filter(array_map('trim', explode(',', (string) $value)), function ($item) {
            return $item !== '';
        });

        return empty($items) ? $default : array_values($items);
    }
}

if (!function_exists('load_env_file')) {
    /** 加载项目根目录 .env 文件。 */
    function load_env_file($path)
    {
        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (!is_array($lines)) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);
            $length = strlen($value);
            if ($length >= 2) {
                $first = $value[0];
                $last = $value[$length - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if (function_exists('putenv')) {
                putenv($key . '=' . $value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

load_env_file(dirname(__DIR__, 2) . '/.env');
