<?php

/** 全局错误与异常处理器。 */
class ErrorHandler
{
    /** 注册全局错误、异常与关机处理。 */
    public static function register()
    {
        set_error_handler(array(__CLASS__, 'handleError'));
        set_exception_handler(array(__CLASS__, 'handleException'));
        register_shutdown_function(array(__CLASS__, 'handleShutdown'));
    }

    /** 启动阶段兜底异常输出，避免初始化未完成时直接白屏。 */
    public static function handleBootstrapException(Throwable $e)
    {
        static::safeLog($e);
        static::emitFailure($e, 500, static::debugEnabled() ? $e->getMessage() : '服务初始化失败');
    }

    /** 将 PHP 错误转换为异常。 */
    public static function handleError($severity, $message, $file, $line)
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /** 处理未捕获异常并记录日志。 */
    public static function handleException(Throwable $e)
    {
        $code = (int) $e->getCode();
        $statusCode = $code >= 400 && $code < 600 ? $code : 500;
        $message = static::debugEnabled() ? $e->getMessage() : (static::inInstallMode() ? '初始化异常' : '服务异常');

        static::safeLog($e, static::resolveRequest());
        static::emitFailure($e, $statusCode, $message);
    }

    /** 捕获致命错误并记录异常日志。 */
    public static function handleShutdown()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR), true)) {
            $exception = new ErrorException($error['message'], 500, $error['type'], $error['file'], $error['line']);
            static::safeLog($exception, static::resolveRequest());

            if (!headers_sent()) {
                static::emitFailure(
                    $exception,
                    500,
                    static::debugEnabled() ? $error['message'] : (static::inInstallMode() ? '初始化异常' : '服务异常')
                );
            }
            return;
        }

        if (class_exists('Logger', false)) {
            Logger::flushRequest();
        }
    }

    /** 统一输出 HTML 或 JSON 错误响应。 */
    protected static function emitFailure(Throwable $e, $statusCode, $message)
    {
        if (static::shouldRenderHtml()) {
            static::renderHtml($e, $statusCode, $message);
        }

        static::renderJson($e, $statusCode, $message);
    }

    /** 输出 JSON 异常响应。 */
    protected static function renderJson(Throwable $e, $statusCode, $message)
    {
        $payload = array(
            'success' => false,
            'code' => (int) $statusCode,
            'data' => array(
                'exception' => get_class($e),
            ),
            'message' => $message,
        );

        if (class_exists('Response', false)) {
            Response::json($payload, $statusCode);
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** 在浏览器初始化场景下输出可读 HTML 异常页。 */
    protected static function renderHtml(Throwable $e, $statusCode, $message)
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: text/html; charset=utf-8');
        }

        $debug = static::debugEnabled();
        $detail = $debug
            ? htmlspecialchars($e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8')
            : '请检查 PHP 扩展、数据库、Redis、目录权限、APP_URL 与伪静态配置。';
        $trace = $debug
            ? '<pre style="margin:0;white-space:pre-wrap;word-break:break-word;">'
                . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8')
                . '</pre>'
            : '';

        echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
        echo '<title>初始化异常</title>';
        echo '<style>
            body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:#f3f6fb;color:#1f2937}
            .wrap{max-width:900px;margin:48px auto;padding:0 20px}
            .card{background:#fff;border-radius:18px;padding:28px;box-shadow:0 20px 60px rgba(15,23,42,.08)}
            h1{margin:0 0 12px;font-size:28px}
            p{margin:0 0 16px;color:#475569}
            .badge{display:inline-block;padding:6px 10px;border-radius:999px;background:#fee2e2;color:#b91c1c;font-size:12px;margin-bottom:14px}
            .detail{background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:16px;color:#334155;white-space:pre-wrap;word-break:break-word}
            .actions{margin-top:18px;display:flex;gap:10px}
            button{border:0;background:#0f766e;color:#fff;padding:12px 18px;border-radius:12px;cursor:pointer}
            .secondary{background:#334155}
          </style></head><body><div class="wrap"><div class="card">';
        echo '<span class="badge">HTTP ' . (int) $statusCode . '</span>';
        echo '<h1>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p>初始化阶段已触发全局异常捕获，页面未中断为白屏。</p>';
        echo '<div class="detail">' . $detail . $trace . '</div>';
        echo '<div class="actions"><button onclick="location.reload()">刷新重试</button><button class="secondary" onclick="history.back()">返回上一页</button></div>';
        echo '</div></div></body></html>';
        exit;
    }

    /** 判断当前是否更适合返回 HTML。 */
    protected static function shouldRenderHtml()
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (strpos($path, '/api') === 0 || $path === '/install/status' || $path === '/install/run') {
            return false;
        }

        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        if ($requestedWith === 'xmlhttprequest') {
            return false;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($accept !== '' && strpos($accept, 'text/html') === false && strpos($accept, 'application/xhtml+xml') === false) {
            return false;
        }

        return static::inInstallMode();
    }

    /** 判断当前是否仍处于初始化阶段。 */
    protected static function inInstallMode()
    {
        $lockFile = defined('BASE_PATH') ? BASE_PATH . '/storage/install/installed.lock' : null;

        if (class_exists('App', false)) {
            $config = App::get('config');
            if (is_array($config) && isset($config['app']['install_lock'])) {
                $lockFile = $config['app']['install_lock'];
            }
        }

        return $lockFile ? !is_file($lockFile) : false;
    }

    /** 安全记录异常，日志系统不可用时退回 error_log。 */
    protected static function safeLog(Throwable $e, $request = null)
    {
        try {
            if (class_exists('Logger', false)) {
                if (class_exists('Request', false) && $request instanceof Request) {
                    Logger::logException($e, $request);
                } else {
                    Logger::logException($e);
                }
                return;
            }
        } catch (Throwable $ignored) {
        }

        error_log('[aichat] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    }

    /** 尝试读取当前请求对象。 */
    protected static function resolveRequest()
    {
        if (!class_exists('App', false)) {
            return null;
        }

        try {
            return App::request();
        } catch (Throwable $ignored) {
            return null;
        }
    }

    /** 读取调试模式，启动早期不可用时默认关闭。 */
    protected static function debugEnabled()
    {
        if (!class_exists('App', false)) {
            return false;
        }

        $config = App::get('config');
        return is_array($config) && !empty($config['app']['debug']);
    }
}
