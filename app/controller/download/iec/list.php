<?php

/** IEC 文件列表控制器。 */
class DownloadIecListController extends BaseController
{
    protected $relativePath = 'uploads/iec/iec_file.iec';
    protected $filename = 'iec_file.iec';

    /** 返回 IEC 固定文件的元信息。 */
    public function handle()
    {
        $this->requirePost();

        $filePath = BASE_PATH . '/' . $this->relativePath;
        if (!is_file($filePath)) {
            response(false, array(), 'IEC文件不存在: ' . $filePath, 404);
        }

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        response(true, array(
            'id' => 1,
            'filename' => $this->filename,
            'original_name' => $this->filename,
            'file_url' => $protocol . '://' . $host . '/' . $this->relativePath,
            'file_size' => filesize($filePath),
            'file_type' => function_exists('mime_content_type') ? mime_content_type($filePath) : 'application/octet-stream',
        ), '获取IEC文件名成功', 200);
    }
}

return new DownloadIecListController();
