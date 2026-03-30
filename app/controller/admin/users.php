<?php

/** 后台用户管理控制器。 */
class AdminUsersController extends BaseController
{
    protected function ensureSuper()
    {
        if ((int) ($this->user()['role'] ?? 0) !== 1) {
            response(false, array(), '权限不足', 403);
        }
    }

    /** 返回用户列表。 */
    public function handle()
    {
        $this->ensureSuper();
        response(true, (new AdminUserService())->listing(), '获取成功', 200);
    }

    /** 新增或更新用户。 */
    public function save()
    {
        $this->requirePost();
        $this->ensureSuper();

        try {
            $record = (new AdminUserService())->save($this->request()->all());
            response(true, $record, '保存成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    /** 删除用户。 */
    public function delete()
    {
        $this->requirePost();
        $this->ensureSuper();

        try {
            (new AdminUserService())->delete((int) $this->input('id', 0), $this->user());
            response(true, array(), '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminUsersController();
