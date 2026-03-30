<?php

/** 后台话术管理控制器。 */
class AdminScriptsController extends BaseController
{
    /** 话术列表。 */
    public function handle()
    {
        response(true, (new AdminScriptCrudService())->listing($this->user()), '获取成功', 200);
    }

    /** 新增或更新话术。 */
    public function save()
    {
        $this->requirePost();

        try {
            $record = (new AdminScriptCrudService())->save($this->request()->all(), $this->user());
            response(true, $record, '保存成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    /** 删除话术。 */
    public function delete()
    {
        $this->requirePost();

        try {
            (new AdminScriptCrudService())->delete((int) $this->input('id', 0), $this->user());
            response(true, array(), '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminScriptsController();
