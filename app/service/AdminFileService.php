<?php

/** 后台文件管理服务。 */
class AdminFileService
{
    protected $uploadPath = 'uploads/';
    protected $archivePath = 'is_delect/';
    protected $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'wb', 'wb2', 'wbk', 'iec');
    protected $maxSize = 20971520;

    public function __construct()
    {
        $config = app('config');
        if (!empty($config['system']['upload_path'])) {
            $this->uploadPath = rtrim($config['system']['upload_path'], '/') . '/';
        }
        if (!empty($config['system']['allowed_extensions']) && is_array($config['system']['allowed_extensions'])) {
            $this->allowedExtensions = $config['system']['allowed_extensions'];
        }
        if (!empty($config['system']['max_upload_size'])) {
            $this->maxSize = (int) $config['system']['max_upload_size'];
        }
    }

    /** 返回当前用户的文件列表与汇总。 */
    public function listing(array $user, array $filters = array())
    {
        $userId = (int) $user['id'];
        $status = trim((string) ($filters['status'] ?? 'all'));
        $search = trim((string) ($filters['search'] ?? ''));
        $params = array(':user_id' => $userId);
        $where = array('user_id = :user_id');

        if ($status === 'downloaded') {
            $where[] = 'is_downloaded = 1';
        } elseif ($status === 'pending') {
            $where[] = 'is_downloaded = 0';
        }

        if ($search !== '') {
            $where[] = '(filename LIKE :search_filename OR original_name LIKE :search_original_name)';
            $params[':search_filename'] = '%' . $search . '%';
            $params[':search_original_name'] = '%' . $search . '%';
        }

        $sql = 'SELECT * FROM files WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
        $items = FileModel::queryAll($sql, $params);

        return array(
            'summary' => array(
                'total' => FileModel::countBy(array('user_id' => $userId)),
                'downloaded' => FileModel::countBy(array('user_id' => $userId, 'is_downloaded' => 1)),
                'pending' => FileModel::countBy(array('user_id' => $userId, 'is_downloaded' => 0)),
            ),
            'items' => $items,
        );
    }

    /** 上传一个或多个文件。 */
    public function upload(array $user, array $files)
    {
        $normalized = $this->normalizeUploadFiles($files);
        if (empty($normalized)) {
            throw new RuntimeException('请选择要上传的文件', 400);
        }

        $uploadDir = BASE_PATH . '/' . $this->uploadPath;
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $success = 0;
        $failed = 0;

        foreach ($normalized as $file) {
            if ((int) $file['error'] !== UPLOAD_ERR_OK) {
                $failed++;
                continue;
            }

            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $this->allowedExtensions, true) && strpos($ext, 'wb') !== 0) {
                $failed++;
                continue;
            }

            if ((int) $file['size'] > $this->maxSize) {
                $failed++;
                continue;
            }

            $filename = $file['name'];
            $targetPath = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                $failed++;
                continue;
            }

            $existing = FileModel::findOneBy(array('user_id' => (int) $user['id'], 'filename' => $filename));
            $data = array(
                'original_name' => $file['name'],
                'file_path' => $this->uploadPath . $filename,
                'file_size' => (int) $file['size'],
                'file_type' => (string) ($file['type'] ?? 'application/octet-stream'),
                'is_downloaded' => 0,
            );

            if ($existing) {
                FileModel::updateBy(array('id' => $existing['id'], 'user_id' => (int) $user['id']), $data);
            } else {
                $data['user_id'] = (int) $user['id'];
                $data['filename'] = $filename;
                FileModel::create($data);
            }

            $success++;
        }

        return array(
            'success_count' => $success,
            'failed_count' => $failed,
        );
    }

    /** 删除单个文件。 */
    public function delete($id, array $user)
    {
        $file = FileModel::findOneBy(array('id' => (int) $id, 'user_id' => (int) $user['id']));
        if (!$file) {
            throw new RuntimeException('文件不存在或无权限', 404);
        }

        $this->archiveFile($file);
        FileModel::db()->query('DELETE FROM files WHERE id = :id AND user_id = :user_id LIMIT 1', array(
            ':id' => (int) $id,
            ':user_id' => (int) $user['id'],
        ));

        return true;
    }

    /** 按状态批量删除文件。 */
    public function bulkDelete(array $user, array $filters = array())
    {
        $mode = trim((string) ($filters['mode'] ?? 'selected'));
        $items = array();

        if ($mode === 'selected') {
            $ids = array_values(array_filter(array_map('intval', (array) ($filters['ids'] ?? array()))));
            if (empty($ids)) {
                throw new RuntimeException('请选择要删除的文件', 400);
            }
            $items = FileModel::findAllBy(array('user_id' => (int) $user['id'], 'id IN' => $ids));
        } elseif ($mode === 'all') {
            $items = FileModel::findAllBy(array('user_id' => (int) $user['id']));
        } elseif ($mode === 'downloaded') {
            $items = FileModel::findAllBy(array('user_id' => (int) $user['id'], 'is_downloaded' => 1));
        } elseif ($mode === 'pending') {
            $items = FileModel::findAllBy(array('user_id' => (int) $user['id'], 'is_downloaded' => 0));
        } else {
            throw new RuntimeException('无效的删除模式', 400);
        }

        $count = 0;
        foreach ($items as $item) {
            $this->archiveFile($item);
            FileModel::db()->query('DELETE FROM files WHERE id = :id AND user_id = :user_id LIMIT 1', array(
                ':id' => (int) $item['id'],
                ':user_id' => (int) $user['id'],
            ));
            $count++;
        }

        return array('deleted_count' => $count);
    }

    protected function archiveFile(array $file)
    {
        $archiveDir = BASE_PATH . '/' . $this->archivePath;
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0755, true);
        }

        $source = BASE_PATH . '/' . ltrim($file['file_path'], '/');
        if (!is_file($source)) {
            return;
        }

        $destination = $archiveDir . basename($source);
        @rename($source, $destination);
    }

    protected function normalizeUploadFiles(array $files)
    {
        if (empty($files['name'])) {
            return array();
        }

        if (!is_array($files['name'])) {
            return array($files);
        }

        $normalized = array();
        foreach ($files['name'] as $index => $name) {
            $normalized[] = array(
                'name' => $name,
                'type' => $files['type'][$index] ?? '',
                'tmp_name' => $files['tmp_name'][$index] ?? '',
                'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                'size' => $files['size'][$index] ?? 0,
            );
        }

        return $normalized;
    }
}
