<?php

/** 后台设置管理控制器。 */
class AdminSettingsController extends BaseController
{
    /** 返回当前用户设置。 */
    public function handle()
    {
        response(true, (new AdminSettingCrudService())->get($this->user()), '获取成功', 200);
    }

    /** 保存当前用户设置。 */
    public function save()
    {
        $this->requirePost();

        try {
            $settings = (new AdminSettingCrudService())->save($this->request()->all(), $this->user());
            response(true, $settings, '保存成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminSettingsController();
