<?php

/** 聊天消息模型，封装会话消息查询、统计与去重判断。 */
class ChatMessage extends BaseModel
{
    protected static $table = 'chat_messages';

    /** 将 momo_users.id 解析为 chat_messages 的查询条件。 */
    protected static function conversationWhere($momoUserId)
    {
        $momoUser = MomoUser::find($momoUserId);
        if ($momoUser && isset($momoUser['user_id'])) {
            return array(
                'user_id' => (int) $momoUser['user_id'],
                'momo_user_id' => (int) $momoUserId,
            );
        }

        return array('momo_user_id' => $momoUserId);
    }

    /** 查询指定会话的完整历史。 */
    public static function historyByUser($momoUserId)
    {
        return static::findAllBy(static::conversationWhere($momoUserId), 'id, message, is_self, timestamp', 'timestamp ASC');
    }

    /** 查询会话最后一条消息。 */
    public static function latestByUser($momoUserId)
    {
        return static::findOneBy(static::conversationWhere($momoUserId), 'message, is_self, timestamp, m_type', 'timestamp DESC');
    }

    /** 查询最后一条对方发送的消息。 */
    public static function latestIncoming($momoUserId)
    {
        return static::findOneBy(array_merge(static::conversationWhere($momoUserId), array('is_self' => 1)), 'message, is_self, timestamp, m_type', 'timestamp DESC');
    }

    /** 查询最后一条对方消息的类型。 */
    public static function latestIncomingType($momoUserId)
    {
        return static::findOneBy(array_merge(static::conversationWhere($momoUserId), array('is_self' => 1)), 'm_type', 'timestamp DESC');
    }

    /** 查询最近一次自己回复的时间。 */
    public static function latestSelfTimestamp($momoUserId)
    {
        return static::findOneBy(array_merge(static::conversationWhere($momoUserId), array('is_self' => 1)), 'timestamp', 'timestamp DESC');
    }

    /** 查询某时间之后对方发送的内容。 */
    public static function incomingAfterTimestamp($momoUserId, $timestamp)
    {
        return static::findAllBy(
            array_merge(static::conversationWhere($momoUserId), array('is_self' => 0, 'timestamp >' => $timestamp)),
            'message, timestamp',
            'timestamp ASC'
        );
    }

    /** 统计会话总消息数。 */
    public static function countByUser($momoUserId)
    {
        return static::countBy(static::conversationWhere($momoUserId));
    }

    /** 统计本方发送消息数。 */
    public static function countOutgoing($momoUserId)
    {
        return static::countBy(array_merge(static::conversationWhere($momoUserId), array('is_self' => 0)));
    }

    /** 查询最近一条可用于摘要的外发文本。 */
    public static function latestOutgoingText($momoUserId)
    {
        return static::findOneBy(
            array_merge(static::conversationWhere($momoUserId), array('is_self' => 0, 'm_type IN' => array(0, 5))),
            'message',
            'timestamp DESC'
        );
    }

    /** 判断消息是否已存在，防止重复导入。 */
    public static function existsDuplicate($momoUserId, $message, $isSelf, $timestamp)
    {
        return static::existsBy(array_merge(static::conversationWhere($momoUserId), array(
            'message' => $message,
            'is_self' => $isSelf,
            'timestamp' => $timestamp,
        )));
    }

    /** 查询构造 AI 上下文所需的近期会话。 */
    public static function recentConversation($momoUserId, $limit = 20)
    {
        return static::findAllBy(
            array_merge(static::conversationWhere($momoUserId), array('m_type IN' => array(0, 5))),
            'message, is_self AS sender_type, timestamp, m_type',
            'timestamp ASC',
            $limit
        );
    }
}
