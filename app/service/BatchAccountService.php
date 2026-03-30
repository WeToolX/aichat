<?php

/** 批量账号服务，兼容老批量接口与新版批量检测流程。 */
class BatchAccountService
{
    /** 处理老版本批量账号检测接口。 */
    public function handleBatch(array $payload, array $user)
    {
        $momoid = $payload['momoid'] ?? '';
        $accountsData = $payload['data'] ?? array();
        $setting = isset($payload['setting']) ? (int) $payload['setting'] : 0;

        if ($momoid === '') {
            throw new RuntimeException('请提供momoid参数', 400);
        }

        if (empty($accountsData) || !is_array($accountsData)) {
            throw new RuntimeException('请提供账号数据', 400);
        }

        $accounts = array();
        foreach ($accountsData as $item) {
            if (isset($item['id'])) {
                $accounts[] = array(
                    'id' => $item['id'],
                    'nickname' => $item['nickname'] ?? '',
                );
            }
        }

        if (empty($accounts)) {
            throw new RuntimeException('未找到有效的账号数据', 400);
        }

        $results = array();
        $processedCount = 0;
        $validCount = 0;
        foreach (array_chunk($accounts, 10) as $group) {
            foreach ($group as $account) {
                $processedCount++;
                $result = $this->buildLegacyBatchMessage(
                    $momoid,
                    (string) $account['id'],
                    (string) $account['nickname'],
                    $setting,
                    (int) ($user['id'] ?? 0)
                );
                if ($result !== null) {
                    $results[] = $result;
                    $validCount++;
                }
            }
        }

        return array(
            'items' => $results,
            'processed_count' => $processedCount,
            'valid_count' => $validCount,
            'message' => '处理完成，共处理' . $processedCount . '个账号，找到' . $validCount . '个符合条件的账号',
        );
    }

    /** 处理基于远程资料的批量账号检测接口。 */
    public function handleBatchV2(array $payload, array $user)
    {
        $momoid = $payload['momoid'] ?? '';
        $accounts = $payload['accounts'] ?? array();
        $setting = isset($payload['setting']) ? (int) $payload['setting'] : 0;
        $token = (string) ($payload['token'] ?? '');

        if ($momoid === '') {
            throw new RuntimeException('请提供陌陌用户ID', 400);
        }

        if (empty($accounts) || !is_array($accounts)) {
            throw new RuntimeException('请提供账号数据', 400);
        }

        $accountsToProcess = array_slice($accounts, 0, 100);
        $profileService = new RemoteProfileService();
        $sessions = array();
        foreach ($accountsToProcess as $account) {
            if (isset($account['id'])) {
                $sessions[] = array(
                    'session_id' => (string) $account['id'],
                    'momoid' => $momoid,
                );
            }
        }

        $profiles = $profileService->fetchSessionProfiles($sessions);
        $checkService = new AccountCheckService();
        $results = array();

        foreach ($profiles as $profile) {
            $sessionId = (string) ($profile['id'] ?? '');
            if ($sessionId === '') {
                continue;
            }

            $item = array(
                'id' => $sessionId,
                'sex' => $profile['sex'] ?? '',
                'status' => $profile['status'] ?? '',
                'nickname' => $profile['nickname'] ?? '',
            );

            try {
                $checked = $checkService->check(
                    $momoid,
                    $sessionId,
                    (int) ($user['id'] ?? 0),
                    (int) ($user['role'] ?? 0),
                    $setting,
                    $token
                );
                $item['message'] = $checked['generated_message'] ?? '';
                $item['is_guide'] = !empty($checked['is_guide']);
                if (!empty($checked['is_hook'])) {
                    $item['is_hook'] = true;
                }
                if (!empty($checked['is_keyword'])) {
                    $item['is_keyword'] = true;
                }
            } catch (RuntimeException $e) {
                $item['error'] = $e->getMessage();
            } catch (Exception $e) {
                $item['error'] = '处理失败: ' . $e->getMessage();
            }

            $results[] = $item;
        }

        return array(
            'items' => $results,
            'processed_count' => count($accounts),
            'valid_count' => count($results),
            'message' => '处理完成，共查询' . count($accounts) . '个会话，找到' . count($results) . '个有效数据',
        );
    }

    /** 兼容旧批量接口的话术生成逻辑。 */
    protected function buildLegacyBatchMessage($momoid, $sessionId, $nickname, $setting, $userId)
    {
        $user = MomoUser::findUnblockedByPair($momoid, $sessionId);

        if (!$user) {
            MomoUser::createDefault($momoid, $sessionId);
            $user = MomoUser::findUnblockedByPair($momoid, $sessionId);
        }

        if (!$user) {
            return null;
        }

        $lastIncoming = ChatMessage::latestIncomingType($user['id']);
        if ($lastIncoming && (int) ($lastIncoming['m_type'] ?? -1) !== 0) {
            return null;
        }

        $messageCount = ChatMessage::countByUser($user['id']);

        $guideAfterMessages = (int) ((FunctionSetting::findByUserId($userId))['guide_after_messages'] ?? 5);

        if ($setting === 1 && !(int) $user['is_friend'] && $messageCount >= $guideAfterMessages) {
            return null;
        }

        $guideMessage = null;
        if ((int) $user['is_friend'] === 1 && $messageCount >= $guideAfterMessages) {
            $script = Script::findByName('引导话术', $userId);
            if ($script && !empty($script['content'])) {
                $guideMessage = $script['content'];
            }
        }

        $message = $guideMessage ?: $this->defaultMessage($sessionId);

        return array(
            'id' => $sessionId,
            'nickname' => $nickname,
            'message' => $message,
            'is_guide' => $guideMessage !== null,
        );
    }

    /** 生成稳定的默认问候话术。 */
    protected function defaultMessage($seed)
    {
        $messages = array(
            '你好，很高兴认识你！',
            '嗨，最近怎么样？',
            '你好呀，有什么可以帮到你的吗？',
            '哈喽，认识一下吧！',
            '你好，很高兴能和你聊天。',
        );

        $index = abs(crc32((string) $seed)) % count($messages);
        return $messages[$index];
    }
}
