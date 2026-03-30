<?php

/** 文件状态更新控制器。 */
class DownloadStatusController extends BaseController
{
    /** 更新文件下载状态。 */
    public function handle()
    {
        $this->requirePost();

        $user = $this->user();
        $filename = $this->input('filename');
        $isDownloaded = $this->input('is_downloaded', 1);

        if (empty($filename)) {
            response(false, array(), '请提供文件名', 400);
        }

        $file = FileModel::findByUserAndFilename($user['id'], $filename);

        if (!$file) {
            response(false, array(), '文件不存在', 404);
        }

        FileModel::updateBy(
            array('id' => $file['id'], 'user_id' => $user['id']),
            array('is_downloaded' => (int) ((bool) $isDownloaded))
        );

        response(true, array(), '文件状态更新成功', 200);
    }
}

return new DownloadStatusController();
