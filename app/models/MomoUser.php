<?php

/** 陌陌会话用户模型，封装会话查找、创建、可见性与摘要读取。 */
class MomoUser extends BaseModel
{
    protected static $table = 'momo_users';

    /** 按 momoid 与 send_momoid 查询会话用户。 */
    public static function findByMomoidPair($momoid, $sendMomoid)
    {
        return static::findOneBy(array('momoid' => $momoid, 'send_momoid' => $sendMomoid));
    }

    /** 按用户归属查询会话用户。 */
    public static function findOwnedByPair($momoid, $sendMomoid, $userId)
    {
        return static::findOneBy(array('momoid' => $momoid, 'send_momoid' => $sendMomoid, 'user_id' => $userId));
    }

    /** 超管可跨用户查看，普通用户仅可查看自己的会话。 */
    public static function findVisibleByPair($momoid, $sendMomoid, array $user)
    {
        if ((int) ($user['role'] ?? 0) === 1) {
            return static::findByMomoidPair($momoid, $sendMomoid);
        }

        return static::findOwnedByPair($momoid, $sendMomoid, $user['id']);
    }

    /** 查询未被拉黑的会话用户。 */
    public static function findUnblockedByPair($momoid, $sendMomoid)
    {
        return static::findOneBy(array('momoid' => $momoid, 'send_momoid' => $sendMomoid, 'is_block' => 0));
    }

    /** 按 session 查询未拉黑用户，可选带 momoid 限定。 */
    public static function findUnblockedBySession($sessionId, $momoid = '')
    {
        if ($momoid !== '') {
            return static::findOneBy(array('momoid' => $momoid, 'send_momoid' => $sessionId, 'is_block' => 0));
        }

        return static::findOneBy(array('send_momoid' => $sessionId, 'is_block' => 0));
    }

    /** 创建默认的陌陌会话用户记录。 */
    public static function createDefault($momoid, $sendMomoid, array $data = array())
    {
        $defaults = array(
            'momoid' => $momoid,
            'send_momoid' => $sendMomoid,
            'chat_id' => MomoSingle::single($sendMomoid),
            'is_friend' => 0,
            'is_online' => 0,
            'is_send' => 0,
            'send_num' => 0,
            'is_block' => 0,
            'last_message' => '',
            'last_interaction' => round(microtime(true) * 1000),
        );

        return static::create(array_merge($defaults, $data));
    }

    /** 检查同用户下是否存在重复会话组合。 */
    public static function duplicateForUser($momoid, $sendMomoid, $userId, $excludedId)
    {
        return static::findOneBy(array(
            'momoid' => $momoid,
            'send_momoid' => $sendMomoid,
            'user_id' => $userId,
            'id !=' => $excludedId,
        ));
    }

    /** 返回接口展示用的精简用户摘要。 */
    public static function findSummaryById($id)
    {
        return static::findOneBy(array('id' => $id), 'id, momoid, send_momoid, chat_id, is_friend, is_online, is_send, send_num, is_block, last_message');
    }

    /** 将指定主账号下的会话在线状态先统一置为离线。 */
    public static function resetOnlineStatusByMomoid($momoid, $userId)
    {
        return static::updateBy(array('momoid' => $momoid, 'user_id' => $userId), array('is_online' => 0));
    }

    /** 将指定主账号下的部分会话批量标记为在线。 */
    public static function markOnlineByMomoid($momoid, array $sendMomoidList, $userId)
    {
        if (empty($sendMomoidList)) {
            return 0;
        }

        $params = array(
            ':momoid' => $momoid,
            ':user_id' => $userId,
        );
        $placeholders = array();

        foreach (array_values($sendMomoidList) as $index => $sendMomoid) {
            $placeholder = ':send_momoid_' . $index;
            $placeholders[] = $placeholder;
            $params[$placeholder] = $sendMomoid;
        }

        $sql = 'UPDATE ' . static::table() . ' SET is_online = 1, updated_at = CURRENT_TIMESTAMP WHERE momoid = :momoid AND user_id = :user_id AND send_momoid IN (' . implode(', ', $placeholders) . ')';
        static::db()->query($sql, $params);

        $countSql = 'SELECT COUNT(*) AS total FROM ' . static::table() . ' WHERE momoid = :momoid AND user_id = :user_id AND is_online = 1';
        $result = static::queryOne($countSql, array(':momoid' => $momoid, ':user_id' => $userId));

        return (int) ($result['total'] ?? 0);
    }

    /** 统计指定主账号下当前用户拥有的会话数量。 */
    public static function countByMomoid($momoid, $userId)
    {
        return static::countBy(array('momoid' => $momoid, 'user_id' => $userId));
    }
}
