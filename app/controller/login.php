<?php

/** 登录控制器。 */
class LoginController extends BaseController
{
    /** 登录接口入口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'username' => '用户名不能为空',
                'password' => '密码不能为空',
            ));

            $result = $this->auth()->login($this->input('username'), $this->input('password'));
            if (!$result['success']) {
                response(false, array(), $result['message'], 401);
            }

            response(true, array(
                'token' => $result['token'],
                'user' => $result['user'],
            ), '登录成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new LoginController();
