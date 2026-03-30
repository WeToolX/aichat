<?php

/** 文件下载控制器。 */
class DownloadFileController extends BaseController
{
    /** 下载用户文件。 */
    public function handle()
    {
        $this->requirePost();

        $user = $this->user();
        $filename = $this->input('filename');
        $fileId = $this->input('file_id');

        if (empty($filename) && empty($fileId)) {
            response(false, array(), '请提供文件名或文件ID', 400);
        }

        if (!empty($fileId)) {
            $file = FileModel::pendingByUserAndId($user['id'], $fileId);
        } else {
            $file = FileModel::pendingByUserAndFilename($user['id'], $filename);
        }

        if (!$file) {
            response(false, array(), '文件不存在', 404);
        }

        $filePath = BASE_PATH . '/' . ltrim($file['file_path'], '/');
        if (!is_file($filePath)) {
            response(false, array(), '文件不存在或已被删除', 404);
        }

        header_remove('Content-Type');
        @ob_end_clean();
        set_time_limit(0);
        ini_set('memory_limit', '512M');

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . urlencode($file['original_name']) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        // 使用大块读取，避免大文件下载时内存占用过高。
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
            FileModel::updateBy(array('id' => $file['id'], 'user_id' => $user['id']), array('is_downloaded' => 1));
        }

        exit;
    }
}

return new DownloadFileController();
