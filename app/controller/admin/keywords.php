<?php

/** 后台关键词管理控制器。 */
class AdminKeywordsController extends BaseController
{
    /** 关键词列表。 */
    public function handle()
    {
        response(true, (new AdminKeywordCrudService())->listing($this->user()), '获取成功', 200);
    }

    /** 新增或更新关键词。 */
    public function save()
    {
        $this->requirePost();

        try {
            $record = (new AdminKeywordCrudService())->save($this->request()->all(), $this->user());
            response(true, $record, '保存成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    /** 删除关键词。 */
    public function delete()
    {
        $this->requirePost();

        try {
            (new AdminKeywordCrudService())->delete((int) $this->input('id', 0), $this->user());
            response(true, array(), '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminKeywordsController();
