<?php

/** 统一日志服务，负责请求日志、异常日志与日志压缩归档。 */
class Logger
{
    const MAX_FILE_SIZE = 5242880;

    protected static $requestContext = array();
    protected static $requestLogged = false;

    /** 初始化日志目录。 */
    public static function boot()
    {
        static::ensureDirectory(static::baseDir());
        static::ensureDirectory(static::requestArchiveDir());
        static::ensureDirectory(static::exceptionArchiveDir());
    }

    /** 记录当前请求上下文。 */
    public static function captureRequest(Request $request)
    {
        static::$requestContext = array(
            'time' => date('Y-m-d H:i:s'),
            'ip' => $request->ip(),
            'method' => $request->method(),
            'route' => $request->path(),
            'get' => static::normalize($request->query()),
            'post' => static::normalize($request->all()),
        );
        static::$requestLogged = false;
    }

    /** 写入请求与响应日志。 */
    public static function logResponse($payload, $status)
    {
        $entry = array_merge(static::requestContext(), array(
            'status' => (int) $status,
            'response' => static::normalize($payload),
        ));

        static::append(static::requestLogFile(), static::requestArchiveDir(), static::formatEntry($entry));
        static::$requestLogged = true;
    }

    /** 写入异常日志。 */
    public static function logException(Throwable $e, Request $request = null)
    {
        if ($request !== null && empty(static::$requestContext)) {
            static::captureRequest($request);
        }

        $entry = array_merge(static::requestContext(), array(
            'error' => array(
                'type' => get_class($e),
                'code' => (int) $e->getCode(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ),
        ));

        static::append(static::exceptionLogFile(), static::exceptionArchiveDir(), static::formatEntry($entry));
    }

    /** 在脚本结束时补记尚未输出 JSON 的请求。 */
    public static function flushRequest()
    {
        if (static::$requestLogged || empty(static::$requestContext)) {
            return;
        }

        $status = http_response_code();
        if (!$status) {
            $status = 200;
        }

        $entry = array_merge(static::requestContext(), array(
            'status' => (int) $status,
            'response' => '[stream-or-empty-response]',
        ));

        static::append(static::requestLogFile(), static::requestArchiveDir(), static::formatEntry($entry));
        static::$requestLogged = true;
    }

    /** 获取当前请求上下文。 */
    protected static function requestContext()
    {
        if (!empty(static::$requestContext)) {
            return static::$requestContext;
        }

        return array(
            'time' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'method' => strtoupper($_SERVER['REQUEST_METHOD'] ?? 'CLI'),
            'route' => $_SERVER['REQUEST_URI'] ?? '/',
            'get' => static::normalize($_GET),
            'post' => static::normalize($_POST),
        );
    }

    /** 追加日志内容，必要时先压缩归档旧文件。 */
    protected static function append($file, $archiveDir, $content)
    {
        static::rotateIfNeeded($file, $archiveDir);
        file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
        static::rotateIfNeeded($file, $archiveDir);
    }

    /** 超过阈值时压缩归档当前日志文件。 */
    protected static function rotateIfNeeded($file, $archiveDir)
    {
        if (!is_file($file) || filesize($file) < static::MAX_FILE_SIZE) {
            return;
        }

        $archiveName = pathinfo($file, PATHINFO_FILENAME) . '_' . date('Ymd_His') . '.log.gz';
        $archivePath = rtrim($archiveDir, '/') . '/' . $archiveName;
        $content = file_get_contents($file);

        if ($content === false) {
            return;
        }

        file_put_contents($archivePath, gzencode($content, 9), LOCK_EX);
        file_put_contents($file, '');
    }

    /** 格式化为单条可读日志块。 */
    protected static function formatEntry(array $entry)
    {
        return str_repeat('=', 80) . PHP_EOL
            . static::stringify('时间', $entry['time'] ?? '')
            . static::stringify('来源IP', $entry['ip'] ?? '')
            . static::stringify('请求方式', $entry['method'] ?? '')
            . static::stringify('请求路由', $entry['route'] ?? '')
            . static::stringify('GET数据', $entry['get'] ?? array())
            . static::stringify('POST数据', $entry['post'] ?? array())
            . static::stringify('响应状态', $entry['status'] ?? '')
            . static::stringify('响应数据', $entry['response'] ?? '')
            . static::stringify('错误情况', $entry['error'] ?? '')
            . PHP_EOL;
    }

    /** 将日志字段格式化为字符串。 */
    protected static function stringify($label, $value)
    {
        if (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }

        return $label . ': ' . (string) $value . PHP_EOL;
    }

    /** 规整日志内容，避免超大字段污染日志。 */
    protected static function normalize($value, $depth = 0)
    {
        if ($depth > 5) {
            return '[depth-limit]';
        }

        if (is_array($value)) {
            $result = array();
            $count = 0;
            foreach ($value as $key => $item) {
                $result[$key] = static::normalize($item, $depth + 1);
                $count++;
                if ($count >= 200) {
                    $result['__truncated__'] = 'too-many-items';
                    break;
                }
            }
            return $result;
        }

        if (is_object($value)) {
            return static::normalize((array) $value, $depth + 1);
        }

        if (is_string($value) && strlen($value) > 5000) {
            return substr($value, 0, 5000) . '...[truncated]';
        }

        return $value;
    }

    /** 获取日志根目录。 */
    protected static function baseDir()
    {
        return BASE_PATH . '/storage/logs';
    }

    /** 请求日志文件。 */
    protected static function requestLogFile()
    {
        return static::baseDir() . '/request.log';
    }

    /** 异常日志文件。 */
    protected static function exceptionLogFile()
    {
        return static::baseDir() . '/exception.log';
    }

    /** 请求日志归档目录。 */
    protected static function requestArchiveDir()
    {
        return static::baseDir() . '/request_archive';
    }

    /** 异常日志归档目录。 */
    protected static function exceptionArchiveDir()
    {
        return static::baseDir() . '/exception_archive';
    }

    /** 确保目录存在。 */
    protected static function ensureDirectory($directory)
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
