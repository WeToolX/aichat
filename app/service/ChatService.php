<?php

/** 聊天服务，负责 chat_id 生成、历史消息整理与关键词回复判断。 */
class ChatService
{
    public function __construct()
    {
        (new MomoService())->ensureSchema();
    }

    /** 生成标准 chat_id。 */
    public function generateChatId($sendMomoid)
    {
        return MomoSingle::single($sendMomoid);
    }

    /** 获取指定会话的历史消息。 */
    public function getHistory($momoUserId, $userId, array $filters = array())
    {
        $momoUser = MomoUser::find($momoUserId);

        if (!$momoUser || (int) $momoUser['user_id'] !== (int) $userId) {
            throw new RuntimeException('无权限查看此聊天记录');
        }

        $messages = ChatMessage::historyByUser($momoUserId, $filters);

        $formatted = array();
        foreach ($messages as $message) {
            $formatted[] = array(
                'id' => $message['id'],
                'content' => $message['message'],
                'is_send' => $message['is_self'],
                'is_self' => (int) ($message['is_self'] ?? 0),
                'isSayHi' => (int) ($message['isSayHi'] ?? 0),
                'message_time' => date('Y-m-d H:i:s', $this->millisecondToSecond($message['timestamp'] ?? 0)),
            );
        }

        return $formatted;
    }

    /** 检查最近一轮对话是否命中关键词回复。 */
    public function matchReplyKeyword($momoid, $sendId, $userId, $isSuper)
    {
        $momoUser = MomoUser::findByMomoidPair($momoid, $sendId);

        if (!$momoUser) {
            throw new RuntimeException('用户不存在');
        }

        $lastSelf = ChatMessage::latestSelfTimestamp($momoUser['id']);

        if (!$lastSelf) {
            throw new RuntimeException('没有找到我的回复记录');
        }

        $messages = ChatMessage::incomingAfterTimestamp($momoUser['id'], $lastSelf['timestamp']);

        if (empty($messages)) {
            throw new RuntimeException('没有找到对方的回复记录');
        }

        $content = trim(implode(' ', array_column($messages, 'message')));
        $matched = (new KeywordService())->matchByContent($content, $userId, $isSuper);

        if ($matched) {
            return array(
                'matched' => true,
                'keyword' => $matched['keyword'],
                'reply' => $matched['reply'],
                'user_reply' => $content,
            );
        }

        return array(
            'matched' => false,
            'user_reply' => $content,
        );
    }

    /** 将毫秒时间戳转换为 date() 可接受的整型秒。 */
    protected function millisecondToSecond($timestamp)
    {
        return (int) floor(((int) $timestamp) / 1000);
    }
}
