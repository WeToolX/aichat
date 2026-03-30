<?php

/** 路由入口健康检查控制器，可删除候选，后台页面与外部业务接口均未依赖。 */
class IndexController extends BaseController
{
    /** 浏览器访问根路径时跳转到后台入口，接口访问仍返回调试信息。 */
    public function index()
    {
        if ($this->shouldRedirectHtml()) {
            $target = !empty($_SESSION['user']) ? '/admin/index.php' : '/login.php';
            header('Location: ' . $target);
            exit;
        }

        response(true, array(
            'message' => 'API router is running',
            'path' => $this->request()->path(),
            'user' => $this->user(),
        ), 'ok', 200);
    }

    protected function shouldRedirectHtml()
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        if ($accept === '') {
            return true;
        }

        return strpos($accept, 'text/html') !== false || strpos($accept, 'application/xhtml+xml') !== false;
    }
}

return new IndexController();
