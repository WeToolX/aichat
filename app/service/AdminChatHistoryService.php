<?php

/** 后台聊天记录服务。 */
class AdminChatHistoryService
{
    public function __construct()
    {
        (new MomoService())->ensureSchema();
    }

    /** 返回当前用户可访问的会话信息。 */
    public function conversation(array $user, $momoUserId)
    {
        $record = $this->findConversation($user, $momoUserId);

        return array(
            'id' => (int) $record['id'],
            'momoid' => (string) ($record['momoid'] ?? ''),
            'send_momoid' => (string) ($record['send_momoid'] ?? ''),
            'last_message' => (string) ($record['last_message'] ?? ''),
            'last_interaction' => !empty($record['last_interaction']) ? (int) $record['last_interaction'] : 0,
            'last_interaction_text' => !empty($record['last_interaction']) ? date('Y-m-d H:i:s', $this->millisecondToSecond($record['last_interaction'])) : '无',
            'message_count' => ChatMessage::countByUser((int) $record['id']),
        );
    }

    /** 手动新增一条聊天记录。 */
    public function send(array $user, array $payload)
    {
        $record = $this->findConversation($user, (int) ($payload['momo_user_id'] ?? 0));
        $content = trim((string) ($payload['content'] ?? ''));
        $isSend = (int) ($payload['is_send'] ?? 0);

        if ($content === '') {
            throw new RuntimeException('消息内容不能为空', 400);
        }

        if (!in_array($isSend, array(0, 1), true)) {
            throw new RuntimeException('发送状态无效', 400);
        }

        $timestamp = $this->parseTimestamp($payload['message_time'] ?? '');

        $messageId = ChatMessage::create(array(
            'user_id' => (int) ($record['user_id'] ?? $user['id']),
            'momo_user_id' => (int) $record['id'],
            'message' => $content,
            'is_self' => $isSend,
            'timestamp' => $timestamp,
        ));

        MomoUser::updateBy(array('id' => (int) $record['id'], 'user_id' => (int) $user['id']), array(
            'last_message' => $content,
            'last_interaction' => $timestamp,
        ));

        return array(
            'id' => (int) $messageId,
            'momo_user_id' => (int) $record['id'],
            'content' => $content,
            'is_send' => $isSend,
            'message_time' => date('Y-m-d H:i:s', $this->millisecondToSecond($timestamp)),
            'timestamp' => $timestamp,
        );
    }

    /** 校验并返回当前用户拥有的会话。 */
    protected function findConversation(array $user, $momoUserId)
    {
        $momoUserId = (int) $momoUserId;
        if ($momoUserId <= 0) {
            throw new RuntimeException('缺少必要参数', 400);
        }

        $record = MomoUser::findOneBy(array(
            'id' => $momoUserId,
            'user_id' => (int) $user['id'],
        ));

        if (!$record) {
            throw new RuntimeException('会话不存在或无权限', 404);
        }

        return $record;
    }

    /** 将表单时间转换为毫秒时间戳。 */
    protected function parseTimestamp($value)
    {
        if (is_numeric($value)) {
            $numeric = (float) $value;
            if ($numeric > 0) {
                $normalized = (int) round($numeric);
                return $normalized > 100000000000 ? $normalized : $normalized * 1000;
            }
        }

        $raw = trim((string) $value);
        if ($raw === '') {
            return (int) round(microtime(true) * 1000);
        }

        $time = strtotime($raw);
        if ($time === false) {
            throw new RuntimeException('消息时间格式无效', 400);
        }

        return $time * 1000;
    }

    /** 将毫秒时间戳转换为 date() 可接受的整型秒。 */
    protected function millisecondToSecond($timestamp)
    {
        return (int) floor(((int) $timestamp) / 1000);
    }
}
