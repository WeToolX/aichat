<?php

/** 响应工具类，统一输出 JSON 结果。 */
class Response
{
    /** 输出 JSON 响应并结束脚本。 */
    public static function json(array $payload, $status = 200)
    {
        $status = (int) $status;
        if ($status < 100 || $status > 599) {
            $status = 500;
        }

        if (!headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        try {
            if (class_exists('Logger', false)) {
                Logger::logResponse($payload, $status);
            }
        } catch (Throwable $e) {
            error_log('[response] logResponse failed: ' . $e->getMessage());
        }

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
