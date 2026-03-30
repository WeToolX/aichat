<?php

/** 文件列表控制器。 */
class DownloadListController extends BaseController
{
    /** 获取一条可下载文件的元信息。 */
    public function handle()
    {
        $this->requirePost();

        $user = $this->user();
        $file = FileModel::randomPendingByUser($user['id']);

        if (!$file) {
            response(false, array(), '没有文件', 404);
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseUrl = $protocol . '://' . $host;
        $webPath = ltrim(str_replace('\\', '/', $file['file_path']), '/');

        response(true, array(
            'id' => $file['id'],
            'filename' => $file['filename'],
            'original_name' => $file['original_name'],
            'file_url' => $baseUrl . '/' . $webPath,
            'file_size' => $file['file_size'],
            'file_type' => $file['file_type'],
        ), '获取文件名成功', 200);
    }
}

return new DownloadListController();
