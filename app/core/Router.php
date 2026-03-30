<?php

/** 文件结构路由器，按 controller 目录解析控制器文件与方法。 */
class Router
{
    /** 分发请求到对应控制器方法。 */
    public function dispatch(Request $request)
    {
        $route = $this->resolve($request->path());
        $request->setRouteInfo($route);

        // 路由层统一决定是否需要登录。
        if ($route['auth']) {
            App::auth()->requireUser();
        }

        $file = CONTROLLER_PATH . '/' . $route['file'] . '.php';
        if (!is_file($file)) {
            response(false, array(), '接口不存在', 404);
        }

        $controller = require $file;
        if (!is_object($controller)) {
            return;
        }

        $method = $route['method'];
        if (!method_exists($controller, $method)) {
            response(false, array(), '路由方法不存在', 404);
        }

        $controller->$method();
    }

    /** 根据文件结构解析控制器与方法。 */
    protected function resolve($path)
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/'))));
        $segments = array_values(array_filter($segments, function ($segment) {
            return strtolower($segment) !== 'api';
        }));
        if (empty($segments)) {
            return array('file' => 'index', 'method' => 'index', 'auth' => false);
        }

        $exactFile = implode('/', $segments);
        $exactCandidate = CONTROLLER_PATH . '/' . $exactFile . '.php';
        if (is_file($exactCandidate)) {
            return array(
                'file' => $exactFile,
                'method' => 'handle',
                'auth' => !$this->isPublicRoute($exactFile, 'handle'),
            );
        }

        for ($i = count($segments); $i > 0; $i--) {
            $file = implode('/', array_slice($segments, 0, $i));
            $candidate = CONTROLLER_PATH . '/' . $file . '.php';
            if (!is_file($candidate)) {
                continue;
            }

            // 约定剩余最后一个片段作为方法名。
            $remainder = array_slice($segments, $i);
            $method = empty($remainder) ? 'handle' : array_pop($remainder);
            if (!empty($remainder)) {
                response(false, array(), '多级目录路由解析失败', 404);
            }

            return array(
                'file' => $file,
                'method' => $method,
                'auth' => !$this->isPublicRoute($file, $method),
            );
        }

        response(false, array(), '接口不存在', 404);
    }

    /** 判断是否为免认证路由。 */
    protected function isPublicRoute($file, $method)
    {
        if (in_array($file, array('login', 'ping'), true)) {
            return true;
        }

        return $file === 'admin/auth' && in_array($method, array('login', 'logout'), true);
    }
}
