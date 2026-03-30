<?php

/** 文件模型，封装下载文件查询与状态更新相关的数据访问。 */
class FileModel extends BaseModel
{
    protected static $table = 'files';

    /** 查询用户未下载的指定文件 ID。 */
    public static function pendingByUserAndId($userId, $id)
    {
        return static::findOneBy(array('user_id' => $userId, 'id' => $id, 'is_downloaded' => 0));
    }

    /** 查询用户未下载的指定文件名。 */
    public static function pendingByUserAndFilename($userId, $filename)
    {
        return static::findOneBy(array('user_id' => $userId, 'filename' => $filename, 'is_downloaded' => 0));
    }

    /** 随机获取一条待下载文件。 */
    public static function randomPendingByUser($userId)
    {
        return static::queryOne(
            'SELECT id, filename, original_name, file_path, file_size, file_type FROM files WHERE user_id = :user_id AND is_downloaded = 0 ORDER BY RAND() LIMIT 1',
            array(':user_id' => $userId)
        );
    }

    /** 按用户和文件名查询文件。 */
    public static function findByUserAndFilename($userId, $filename)
    {
        return static::findOneBy(array('user_id' => $userId, 'filename' => $filename));
    }
}
