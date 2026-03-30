<?php

/** 后台陌陌会话管理控制器。 */
class AdminMomoController extends BaseController
{
    public function handle()
    {
        $result = (new AdminMomoService())->listing($this->user(), array(
            'momoid' => $this->request()->query('momoid', ''),
            'search' => $this->request()->query('search', ''),
            'group_search' => $this->request()->query('group_search', ''),
            'with_items' => $this->request()->query('with_items', 1),
            'page' => $this->request()->query('page', 1),
            'per_page' => $this->request()->query('per_page', 20),
        ));
        response(true, $result, '获取成功', 200);
    }

    public function save()
    {
        $this->requirePost();

        try {
            $record = (new AdminMomoService())->save($this->request()->all(), $this->user());
            response(true, $record, '保存成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    public function delete()
    {
        $this->requirePost();

        try {
            (new AdminMomoService())->delete((int) $this->input('id', 0), $this->user());
            response(true, array(), '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    public function deleteMomoid()
    {
        $this->requirePost();

        try {
            (new AdminMomoService())->deleteByMomoid($this->input('momoid', ''), $this->user());
            response(true, array(), '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminMomoController();
