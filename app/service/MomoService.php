<?php

/** 陌陌核心业务服务，负责会话查询、更新、导入、拉黑与批量处理。 */
class MomoService
{
    protected $db;
    protected static $schemaEnsured = false;

    /** 初始化陌陌业务服务。 */
    public function __construct()
    {
        $this->db = App::db();
        if (!static::$schemaEnsured) {
            $this->ensureSchema();
            static::$schemaEnsured = true;
        }
    }

    /** 启动时补齐陌陌会话表缺失字段。 */
    public function ensureSchema()
    {
        $this->ensureColumn('momo_users', 'user_id', "ALTER TABLE momo_users ADD COLUMN user_id INT NOT NULL DEFAULT 1");
        $this->ensureColumn('momo_users', 'send_momoid', "ALTER TABLE momo_users ADD COLUMN send_momoid VARCHAR(100) NOT NULL DEFAULT ''");
        $this->ensureColumn('momo_users', 'chat_id', "ALTER TABLE momo_users ADD COLUMN chat_id VARCHAR(255) NOT NULL DEFAULT ''");
        $this->ensureColumn('momo_users', 'is_friend', "ALTER TABLE momo_users ADD COLUMN is_friend TINYINT(1) NOT NULL DEFAULT 0");
        $this->ensureColumn('momo_users', 'is_online', "ALTER TABLE momo_users ADD COLUMN is_online TINYINT(1) NOT NULL DEFAULT 0");
        $this->ensureColumn('momo_users', 'last_message', "ALTER TABLE momo_users ADD COLUMN last_message TEXT");
        $this->ensureColumn('momo_users', 'last_interaction', "ALTER TABLE momo_users ADD COLUMN last_interaction BIGINT NOT NULL DEFAULT 0");
        $this->ensureColumn('chat_messages', 'user_id', "ALTER TABLE chat_messages ADD COLUMN user_id INT NOT NULL DEFAULT 1");
        $this->ensureColumn('chat_messages', 'isSayHi', "ALTER TABLE chat_messages ADD COLUMN isSayHi TINYINT(1) NOT NULL DEFAULT 0");
        $this->dropIndexIfExists('momo_users', 'unique_momoid_sendmomoid');

        $this->ensureIndex(
            'momo_users',
            'unique_momoid_user',
            'ALTER TABLE momo_users ADD UNIQUE KEY unique_momoid_user (user_id, momoid, send_momoid)'
        );
    }

    /** 按需补充单个表字段。 */
    protected function ensureColumn($table, $column, $sql, $after = null)
    {
        $conn = $this->db->getConn();
        $quotedColumn = $conn->quote($column);
        $stmt = $conn->query("SHOW COLUMNS FROM {$table} LIKE {$quotedColumn}");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$exists) {
            $conn->exec($sql);
            if (is_callable($after)) {
                $after();
            }
        }
    }

    /** 按需补充索引。 */
    protected function ensureIndex($table, $indexName, $sql)
    {
        $conn = $this->db->getConn();
        $quotedIndexName = $conn->quote($indexName);
        $stmt = $conn->query("SHOW INDEX FROM {$table} WHERE Key_name = {$quotedIndexName}");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if (!$exists) {
            $conn->exec($sql);
        }
    }

    /** 如果仍存在老索引则先移除，避免跨用户唯一约束冲突。 */
    protected function dropIndexIfExists($table, $indexName)
    {
        $conn = $this->db->getConn();
        $quotedIndexName = $conn->quote($indexName);
        $stmt = $conn->query("SHOW INDEX FROM {$table} WHERE Key_name = {$quotedIndexName}");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($exists) {
            $conn->exec("ALTER TABLE {$table} DROP INDEX {$indexName}");
        }
    }

    /** 查询陌陌会话用户，不存在时自动创建。 */
    public function search($momoid, $sendMomoid, array $user)
    {
        $matched = $this->findSearchTarget($momoid, $sendMomoid, $user);

        if ($matched) {
            return array('code' => 200, 'message' => '用户存在', 'data' => $matched);
        }

        $createdId = $this->createMomoUser($momoid, $sendMomoid, $user, array());

        $created = $this->fetchUserById($createdId);
        return array('code' => 201, 'message' => '用户不存在，已自动创建', 'data' => $created);
    }

    /** 更新陌陌会话与消息摘要。 */
    public function update(array $data, array $user)
    {
        $momoid = $data['momoid'];
        $sendMomoid = $data['send_momoid'];
        $targetId = $data['id'] ?? null;
        $content = $data['content'] ?? '';
        $isSend = ((int) ($data['is_send'] ?? 0) === 1) ? 1 : 0;
        $isFriend = ((int) ($data['is_friend'] ?? 0) === 1) ? 1 : 0;
        $messageTime = !empty($data['message_time']) ? (int) $data['message_time'] : round(microtime(true) * 1000);
        $mType = (int) ($data['m_type'] ?? 0);
        $isSayHi = (int) ($data['isSayHi'] ?? 0) === 1 ? 1 : 0;

        $record = $this->findOwnedMomoUser($momoid, $sendMomoid, $user, $targetId);
        if (!$record) {
            $createdId = $this->createMomoUser($momoid, $sendMomoid, $user, array(
                'is_friend' => $isFriend,
                'is_send' => $isSend,
                'send_num' => ($isSend === 0 && $content !== '') ? 1 : 0,
                'is_block' => (int) ($data['is_block'] ?? 0),
                'last_interaction' => $messageTime,
                'last_message' => ($isSend === 0 && $content !== '') ? $content : '',
            ));
            $record = $this->fetchUserFullById($createdId);
            if ($content !== '') {
                $this->insertChatMessageIfMissing($record['id'], $content, $isSend, $messageTime, $mType, $isSayHi);
            }
        }

        $this->assertOwnership($record, $user, '无权限修改此用户');

        $existing = $this->duplicateUser($momoid, $sendMomoid, $user, $record['id']);
        if ($existing) {
            throw new RuntimeException('该陌陌ID和发送陌陌ID组合已存在', 409);
        }

        $update = $this->buildUpdatePayload($record, $data, $content, $isSend, $isFriend, $mType);

        if ($content !== '') {
            $this->insertChatMessageIfMissing($record['id'], $content, $isSend, $messageTime, $mType, $isSayHi);
        }

        if (!$this->hasChanges($record, $update)) {
            return array('code' => 200, 'message' => '数据无变化，无需更新', 'data' => $this->fetchUserById($record['id']));
        }

        $this->db->update('momo_users', $update, array('id' => $record['id']));
        return array('code' => 200, 'message' => '更新成功', 'data' => $this->fetchUserById($record['id']));
    }

    /** 批量导入陌陌消息列表。 */
    public function importMessages($momoid, $sendMomoid, array $messages, array $user)
    {
        $record = $this->findOwnedMomoUser($momoid, $sendMomoid, $user);
        if (!$record) {
            $createdId = $this->createMomoUser($momoid, $sendMomoid, $user, array());
            $record = $this->fetchUserFullById($createdId);
        }

        $this->assertOwnership($record, $user, '无权限操作此用户');

        $stats = $this->importMessageBatch($record['id'], $messages);
        $this->refreshImportedUserState($record['id'], $stats['is_friend']);

        return array(
            'code' => 200,
            'message' => '导入完成，成功导入 ' . $stats['imported'] . ' 条消息，跳过 ' . $stats['skipped'] . ' 条消息',
            'data' => array(
                'user' => $this->fetchUserById($record['id']),
                'imported_count' => $stats['imported'],
                'skipped_count' => $stats['skipped'],
            ),
        );
    }

    /** 对会话执行拉黑或取消拉黑。 */
    public function block($momoid, $sendMomoid, $isBlock, array $user)
    {
        $record = $this->findOwnedMomoUser($momoid, $sendMomoid, $user);
        if (!$record) {
            throw new RuntimeException('用户不存在', 404);
        }

        $this->assertOwnership($record, $user, '无权限操作此用户');

        $this->db->update('momo_users', array('is_block' => $isBlock ? 1 : 0), array('id' => $record['id']));

        return array(
            'code' => 200,
            'message' => $isBlock ? '拉黑成功' : '取消拉黑成功',
            'data' => $this->fetchUserById($record['id']),
        );
    }

    /** 处理批量更新接口。 */
    public function batchUpdate(array $payload, array $user)
    {
        $momoid = $payload['momoid'] ?? '';
        $accounts = $payload['data'] ?? array();

        if ($momoid === '') {
            throw new RuntimeException('外层momoid不能为空', 400);
        }

        if (!is_array($accounts)) {
            throw new RuntimeException('缺少data字段', 400);
        }

        $summary = array(
            'results' => array(),
            'success_count' => 0,
            'failed_count' => 0,
        );

        foreach (array_chunk($accounts, 10) as $batch) {
            foreach ($batch as $account) {
                $summary['results'][] = $this->processBatchAccount($momoid, $account, $user, $summary);
            }

            usleep(100000);
        }

        return array(
            'code' => 200,
            'message' => '批量处理完成，成功处理 ' . $summary['success_count'] . ' 个账号，失败 ' . $summary['failed_count'] . ' 个账号',
            'data' => array(
                'total_accounts' => count($accounts),
                'success_count' => $summary['success_count'],
                'failed_count' => $summary['failed_count'],
                'results' => $summary['results'],
            ),
        );
    }

    /** 上报指定主账号下当前在线的好友列表。 */
    public function reportOnlineUsers($momoid, array $friendIds, array $user)
    {
        if ($momoid === '') {
            throw new RuntimeException('momoid不能为空', 400);
        }

        $normalizedIds = array();
        foreach ($friendIds as $friendId) {
            $friendId = trim((string) $friendId);
            if ($friendId !== '') {
                $normalizedIds[$friendId] = $friendId;
            }
        }

        $userId = (int) ($user['id'] ?? 0);
        MomoUser::resetOnlineStatusByMomoid($momoid, $userId);
        $onlineCount = MomoUser::markOnlineByMomoid($momoid, array_values($normalizedIds), $userId);
        $totalCount = MomoUser::countByMomoid($momoid, $userId);

        return array(
            'code' => 200,
            'message' => '在线好友状态上报成功',
            'data' => array(
                'momoid' => $momoid,
                'reported_online_count' => count($normalizedIds),
                'updated_online_count' => $onlineCount,
                'total_user_count' => $totalCount,
            ),
        );
    }

    /** 查询当前用户可见的会话摘要。 */
    protected function findSearchTarget($momoid, $sendMomoid, array $user)
    {
        $record = MomoUser::findVisibleByPair($momoid, $sendMomoid, $user);
        return $record ? MomoUser::findSummaryById($record['id']) : null;
    }

    /** 创建默认陌陌会话记录。 */
    protected function createMomoUser($momoid, $sendMomoid, array $user, array $overrides)
    {
        return MomoUser::createDefault($momoid, $sendMomoid, array_merge(array('user_id' => $user['id']), $overrides));
    }

    /** 校验记录归属权限。 */
    protected function assertOwnership(array $record, array $user, $message)
    {
        if ((int) $user['role'] !== 1 && (int) $record['user_id'] !== (int) $user['id']) {
            throw new RuntimeException($message, 403);
        }
    }

    /** 检查是否存在重复会话组合。 */
    protected function duplicateUser($momoid, $sendMomoid, array $user, $excludedId)
    {
        return MomoUser::duplicateForUser($momoid, $sendMomoid, $user['id'], $excludedId);
    }

    /** 计算更新后应写回的数据字段。 */
    protected function buildUpdatePayload(array $record, array $data, $content, $isSend, $isFriend, $mType)
    {
        $chatId = ($data['send_momoid'] !== $record['send_momoid']) ? MomoSingle::single($data['send_momoid']) : $record['chat_id'];
        $sendNum = $this->outgoingMessageCount($record['id']);
        if ($isSend === 0 && $content !== '') {
            $sendNum++;
        }

        $lastMessage = $record['last_message'] ?? '';
        if ($isSend === 0 && $content !== '' && in_array($mType, array(0, 5), true)) {
            $lastMessage = $content;
        }

        return array(
            'momoid' => $data['momoid'],
            'send_momoid' => $data['send_momoid'],
            'chat_id' => $chatId,
            'is_friend' => $isFriend,
            'is_send' => $isSend,
            'is_block' => (int) ($data['is_block'] ?? 0),
            'send_num' => $sendNum,
            'last_message' => $lastMessage,
        );
    }

    /** 判断本次更新是否真的发生变化。 */
    protected function hasChanges(array $record, array $update)
    {
        foreach ($update as $key => $value) {
            if (!isset($record[$key]) || (string) $record[$key] !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    /** 批量导入消息并统计成功/跳过数量。 */
    protected function importMessageBatch($momoUserId, array $messages)
    {
        $stats = array('imported' => 0, 'skipped' => 0, 'is_friend' => 0);

        foreach ($messages as $item) {
            if (!$parsed = $this->parseImportMessage($item)) {
                $stats['skipped']++;
                continue;
            }

            if ($parsed['is_friend']) {
                $stats['is_friend'] = 1;
            }

            if ($this->insertChatMessageIfMissing($momoUserId, $parsed['content'], $parsed['is_self'], $parsed['timestamp'], $parsed['m_type'], $parsed['isSayHi'])) {
                $stats['imported']++;
            } else {
                $stats['skipped']++;
            }
        }

        return $stats;
    }

    /** 解析单条导入消息的结构。 */
    protected function parseImportMessage(array $item)
    {
        try {
            $msgInfo = json_decode($item['m_msginfo'] ?? '{}', true);
            $content = is_array($msgInfo) ? ($msgInfo['content'] ?? '') : '';
            if ($content === '') {
                return null;
            }

            return array(
                'content' => $content,
                'is_friend' => $this->containsFriendMarker($content),
                'is_self' => (($item['m_receive'] ?? '1') === '1') ? 1 : 0,
                'isSayHi' => $this->extractSayHiFlag($msgInfo, $item),
                'timestamp' => is_numeric($item['m_time'] ?? null) ? (int) $item['m_time'] : round(microtime(true) * 1000),
                'm_type' => is_numeric($item['m_type'] ?? null) ? (int) $item['m_type'] : 0,
            );
        } catch (Exception $e) {
            return null;
        }
    }

    /** 尝试从消息体中识别是否为招呼消息。 */
    protected function extractSayHiFlag(array $msgInfo, array $item)
    {
        if (isset($msgInfo['sayhi'])) {
            return ((int) $msgInfo['sayhi'] === 1) ? 1 : 0;
        }

        if (isset($item['isSayHi'])) {
            return ((int) $item['isSayHi'] === 1) ? 1 : 0;
        }

        return 0;
    }

    /** 判断消息是否包含“已成为好友”标记。 */
    protected function containsFriendMarker($content)
    {
        $clean = strtolower(preg_replace('/\s+/', '', $content));
        $target = strtolower(preg_replace('/\s+/', '', '可以发送语音消息啦'));
        return strpos($clean, $target) !== false;
    }

    /** 刷新会话摘要字段。 */
    protected function refreshImportedUserState($momoUserId, $isFriend)
    {
        $count = $this->outgoingMessageCount($momoUserId);
        $lastMessage = $this->latestOutgoingText($momoUserId);

        $this->db->update('momo_users', array(
            'send_num' => $count,
            'last_message' => $lastMessage['message'] ?? '',
            'is_friend' => $isFriend,
        ), array('id' => $momoUserId));
    }

    /** 统计当前会话外发消息数。 */
    protected function outgoingMessageCount($momoUserId)
    {
        return ChatMessage::countOutgoing($momoUserId);
    }

    /** 获取最近一条外发文本。 */
    protected function latestOutgoingText($momoUserId)
    {
        return ChatMessage::latestOutgoingText($momoUserId);
    }

    /** 处理批量更新中的单个账号。 */
    protected function processBatchAccount($momoid, array $account, array $user, array &$summary)
    {
        $sendMomoid = trim((string) (
            $account['send_momoid']
            ?? $account['send_id']
            ?? $account['陌陌号']
            ?? ''
        ));
        if ($sendMomoid === '') {
            $summary['failed_count']++;
            return array(
                'momoid' => $momoid,
                'send_momoid' => '',
                'success' => false,
                'message' => '陌陌号不能为空',
                'imported_count' => 0,
                'skipped_count' => 0,
            );
        }

        try {
            $messages = array();
            if (is_array($account['messages'] ?? null)) {
                $messages = $account['messages'];
            } elseif (is_array($account['massage'] ?? null)) {
                $messages = $account['massage'];
            }

            $result = $this->importMessages(
                $momoid,
                $sendMomoid,
                $messages,
                $user
            );

            $summary['success_count']++;
            return array(
                'momoid' => $momoid,
                'send_momoid' => $sendMomoid,
                'success' => true,
                'message' => $result['message'],
                'imported_count' => $result['data']['imported_count'] ?? 0,
                'skipped_count' => $result['data']['skipped_count'] ?? 0,
                'user' => $result['data']['user'] ?? array(),
            );
        } catch (Exception $e) {
            $summary['failed_count']++;
            return array(
                'momoid' => $momoid,
                'send_momoid' => $sendMomoid,
                'success' => false,
                'message' => $e->getMessage(),
                'imported_count' => 0,
                'skipped_count' => 0,
            );
        }
    }

    /** 按条件查询当前用户可见的陌陌会话。 */
    protected function findOwnedMomoUser($momoid, $sendMomoid, array $user, $targetId = null)
    {
        if ($targetId) {
            $record = MomoUser::find($targetId);
            if ($record) {
                return $record;
            }
        }

        return MomoUser::findVisibleByPair($momoid, $sendMomoid, $user);
    }

    /** 插入消息前先做去重判断。 */
    protected function insertChatMessageIfMissing($momoUserId, $content, $isSelf, $timestamp, $mType, $isSayHi = 0)
    {
        if (ChatMessage::existsDuplicate($momoUserId, $content, $isSelf, $timestamp)) {
            return false;
        }

        $conversation = MomoUser::find($momoUserId);
        if (!$conversation) {
            throw new RuntimeException('会话不存在，无法写入消息', 404);
        }

        ChatMessage::create(array(
            'user_id' => (int) ($conversation['user_id'] ?? 0),
            'momo_user_id' => (int) $conversation['id'],
            'message' => $content,
            'is_self' => $isSelf,
            'isSayHi' => (int) $isSayHi === 1 ? 1 : 0,
            'timestamp' => $timestamp,
            'm_type' => $mType,
        ));
        return true;
    }

    /** 查询用于接口返回的会话摘要。 */
    protected function fetchUserById($id)
    {
        return MomoUser::findSummaryById($id);
    }

    /** 查询完整会话记录。 */
    protected function fetchUserFullById($id)
    {
        return MomoUser::find($id);
    }
}
