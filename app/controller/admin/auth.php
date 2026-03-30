<?php

/** 后台认证控制器，供独立前端页面调用。 */
class AdminAuthController extends BaseController
{
    /** 登录并返回 token 与用户信息。 */
    public function login()
    {
        $this->requirePost();

        $this->requireFields(array(
            'username' => '用户名不能为空',
            'password' => '密码不能为空',
        ));

        $result = $this->auth()->login($this->input('username'), $this->input('password'));
        if (empty($result['success'])) {
            response(false, array(), $result['message'] ?? '用户名或密码错误', 401);
        }

        App::setUser($result['user']);
        $_SESSION['admin_token'] = $result['token'];

        response(true, array(
            'token' => $result['token'],
            'user' => $result['user'],
        ), '登录成功', 200);
    }

    /** 退出后台登录。 */
    public function logout()
    {
        if ($this->request()->method() !== 'POST' && $this->request()->method() !== 'GET') {
            response(false, array(), '请求方法不支持', 405);
        }

        App::setUser(null);
        unset($_SESSION['user'], $_SESSION['admin_token']);

        response(true, array(), '退出成功', 200);
    }

    /** 返回当前登录用户。 */
    public function me()
    {
        response(true, array(
            'user' => $this->user(),
        ), '获取成功', 200);
    }
}

return new AdminAuthController();
