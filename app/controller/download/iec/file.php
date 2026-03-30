<?php

/** IEC 文件下载控制器。 */
class DownloadIecFileController extends BaseController
{
    protected $relativePath = 'uploads/iec/iec_file.iec';
    protected $filename = 'iec_file.iec';

    /** 下载 IEC 固定文件。 */
    public function handle()
    {
        $this->requirePost();

        $filePath = BASE_PATH . '/' . $this->relativePath;
        if (!is_file($filePath)) {
            response(false, array(), 'IEC文件不存在: ' . $filePath, 404);
        }

        header_remove('Content-Type');
        @ob_end_clean();
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . urlencode($this->filename) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // 使用分块输出方式传输文件。
        $handle = fopen($filePath, 'rb');
        if ($handle) {
            $bufferSize = 4 * 1024 * 1024;
            while (!feof($handle)) {
                echo fread($handle, $bufferSize);
                flush();
                if (ob_get_level()) {
                    ob_flush();
                }
            }
            fclose($handle);
        }

        exit;
    }
}

return new DownloadIecFileController();
