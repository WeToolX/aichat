<?php

/** 后台个人资料控制器。 */
class AdminProfileController extends BaseController
{
    /** 返回当前用户资料。 */
    public function handle()
    {
        response(true, (new AdminProfileService())->profile($this->user()), '获取成功', 200);
    }

    /** 修改当前用户密码。 */
    public function password()
    {
        $this->requirePost();

        try {
            (new AdminProfileService())->changePassword($this->user(), $this->request()->all());
            response(true, array(), '密码修改成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminProfileController();
