<?php

/** 请求对象，负责统一读取路径、请求头、Query 与 Body 参数。 */
class Request
{
    protected $body;
    protected $headers;
    protected $routeInfo = array();

    /** 获取来源 IP。 */
    public function ip()
    {
        $keys = array(
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ($keys as $key) {
            if (empty($_SERVER[$key])) {
                continue;
            }

            $value = trim((string) $_SERVER[$key]);
            if ($value === '') {
                continue;
            }

            if ($key === 'HTTP_X_FORWARDED_FOR') {
                $parts = explode(',', $value);
                return trim($parts[0]);
            }

            return $value;
        }

        return 'unknown';
    }

    /** 获取请求方法。 */
    public function method()
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    /** 获取原始请求 URI。 */
    public function uri()
    {
        return $_SERVER['REQUEST_URI'] ?? '/';
    }

    /** 获取标准化后的业务路径。 */
    public function path()
    {
        $uri = strtok($this->uri(), '?');
        if (!$uri) {
            return '/';
        }

        $path = '/' . trim($uri, '/');
        $path = preg_replace('#^/api/index\.php#', '', $path);
        $path = preg_replace('#^/api#', '', $path);

        if ($path === '') {
            return '/';
        }

        return $path;
    }

    /** 读取 query 参数。 */
    public function query($key = null, $default = null)
    {
        if ($key === null) {
            return $_GET;
        }

        return array_get($_GET, $key, $default);
    }

    /** 读取请求体参数。 */
    public function input($key = null, $default = null)
    {
        $data = $this->all();
        if ($key === null) {
            return $data;
        }

        return array_get($data, $key, $default);
    }

    /** 获取完整请求体数据。 */
    public function all()
    {
        if ($this->body !== null) {
            return $this->body;
        }

        // 优先解析 JSON，其次兼容传统表单提交。
        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $this->body = $decoded;
        } elseif (!empty($_POST)) {
            $this->body = $_POST;
        } else {
            $this->body = array();
        }

        return $this->body;
    }

    /** 读取指定请求头。 */
    public function header($key, $default = null)
    {
        $headers = $this->headers();
        $lookup = strtolower($key);
        return array_key_exists($lookup, $headers) ? $headers[$lookup] : $default;
    }

    /** 提取全部请求头。 */
    public function headers()
    {
        if ($this->headers !== null) {
            return $this->headers;
        }

        $headers = array();
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }

        $this->headers = $headers;
        return $headers;
    }

    /** 写入路由解析结果。 */
    public function setRouteInfo(array $routeInfo)
    {
        $this->routeInfo = $routeInfo;
    }

    /** 获取路由解析结果。 */
    public function routeInfo()
    {
        return $this->routeInfo;
    }
}
