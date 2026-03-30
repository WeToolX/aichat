<?php

/** 后台文件管理控制器。 */
class AdminFilesController extends BaseController
{
    public function handle()
    {
        $service = new AdminFileService();
        $result = $service->listing($this->user(), array(
            'status' => $this->request()->query('status', 'all'),
            'search' => $this->request()->query('search', ''),
        ));
        response(true, $result, '获取成功', 200);
    }

    public function upload()
    {
        $this->requirePost();

        try {
            $result = (new AdminFileService())->upload($this->user(), $_FILES['files'] ?? array());
            response(true, $result, '上传成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    public function delete()
    {
        $this->requirePost();

        try {
            (new AdminFileService())->delete((int) $this->input('id', 0), $this->user());
            response(true, array(), '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    public function bulkDelete()
    {
        $this->requirePost();

        try {
            $result = (new AdminFileService())->bulkDelete($this->user(), $this->request()->all());
            response(true, $result, '删除成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminFilesController();
