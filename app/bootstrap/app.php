<?php

if (defined('API_BOOTSTRAPPED')) {
    return;
}

define('API_BOOTSTRAPPED', true);
define('BASE_PATH', dirname(__DIR__, 2));
define('APP_PATH', dirname(__DIR__));
define('CONTROLLER_PATH', APP_PATH . '/controller');

require_once __DIR__ . '/env.php';
require_once APP_PATH . '/core/ErrorHandler.php';

try {
    $config = require BASE_PATH . '/config/config.php';
    date_default_timezone_set($config['app']['timezone']);

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    require_once APP_PATH . '/core/Support/helpers.php';
    require_once APP_PATH . '/core/Support/redis_stub.php';

    spl_autoload_register(function ($class) {
        static $classMap = null;

        if ($classMap === null) {
            $classMap = array();
            $paths = array(
                APP_PATH . '/core',
                APP_PATH . '/models',
                APP_PATH . '/service',
                APP_PATH,
            );

            foreach ($paths as $path) {
                foreach (glob($path . '/*.php') ?: array() as $file) {
                    $classMap[pathinfo($file, PATHINFO_FILENAME)] = $file;
                }
            }
        }

        if (isset($classMap[$class])) {
            require_once $classMap[$class];
        }
    });

    App::bootstrap($config);
    Logger::boot();
    ErrorHandler::register();
} catch (Throwable $e) {
    ErrorHandler::handleBootstrapException($e);
}
