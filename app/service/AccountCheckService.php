<?php

/** 账号检测服务，负责判断会话是否符合自动回复条件并生成回复结果。 */
class AccountCheckService
{
    /** 校验账号是否符合自动回复条件，并生成最终话术。 */
    public function check($momoid, $sendId, $userId, $role, $setting = null, $token = '')
    {
        $userResult = $this->getOrCreateMomoUser($momoid, $sendId);
        $this->assertUserAvailable($userResult);

        $messageResult = $this->latestIncomingCandidate($userResult['id']);
        $messageCount = $this->messageCount($userResult['id']);
        $settings = $this->resolveSettings($userId, $setting);
        $context = $this->buildContext($messageResult, $messageCount, $settings, $userResult);

        if ((int) $settings['setting'] === 1 && !(int) $userResult['is_friend'] && $messageCount >= $settings['guide_after_messages']) {
            throw new RuntimeException('账号不符合条件，不是好友', 400);
        }

        $hookReply = $this->hookReply($userId, $messageCount, $settings['guide_after_messages']);
        if ($hookReply !== null) {
            return $this->responsePayload($context, array(
                'generated_message' => $hookReply,
                'is_guide' => false,
                'is_hook' => true,
                'should_block' => true,
            ));
        }

        $guideReply = $this->guideReply($userId, $messageCount, $settings['guide_after_messages']);
        if ($guideReply !== null) {
            return $this->responsePayload($context, array(
                'generated_message' => $guideReply,
                'is_guide' => true,
                'should_block' => false,
            ));
        }

        $matchedKeyword = (new KeywordService())->matchByContent(
            $messageResult['message'],
            $userId,
            (int) $role === 1
        );

        if ($matchedKeyword) {
            return $this->responsePayload($context, array(
                'generated_message' => $matchedKeyword['reply'],
                'is_guide' => false,
                'is_keyword' => true,
                'keyword' => $matchedKeyword['keyword'],
                'should_block' => false,
            ));
        }

        return $this->callDeepseek($momoid, $sendId, $context, $token);
    }

    /** 获取会话用户，不存在时自动补建。 */
    protected function getOrCreateMomoUser($momoid, $sendId)
    {
        $userResult = MomoUser::findByMomoidPair($momoid, $sendId);

        if ($userResult) {
            return $userResult;
        }

        MomoUser::createDefault($momoid, $sendId);

        $userResult = MomoUser::findByMomoidPair($momoid, $sendId);

        if (!$userResult) {
            throw new RuntimeException('创建用户失败', 500);
        }

        return $userResult;
    }

    /** 检查账号是否已被拉黑。 */
    protected function assertUserAvailable(array $userResult)
    {
        if ((int) $userResult['is_block'] === 1) {
            throw new RuntimeException('账号已被拉黑', 403);
        }
    }

    /** 获取最近一条可用于判断的消息。 */
    protected function latestIncomingCandidate($momoUserId)
    {
        $messageResult = ChatMessage::latestByUser($momoUserId);

        if (!$messageResult) {
            throw new RuntimeException('没有聊天记录', 404);
        }

        if ((int) $messageResult['is_self'] !== 1) {
            throw new RuntimeException('最后一条消息是自己发送的，不符合条件', 400);
        }

        return $messageResult;
    }

    /** 统计会话消息总数。 */
    protected function messageCount($momoUserId)
    {
        return ChatMessage::countByUser($momoUserId);
    }

    /** 读取并合并账号检测相关设置。 */
    protected function resolveSettings($userId, $setting)
    {
        $allSettings = (new SettingService())->getSettings($userId);
        $resolvedSetting = $setting === null ? (int) ($allSettings['only_send_to_friends'] ?? 0) : (int) $setting;

        return array(
            'guide_after_messages' => (int) ($allSettings['guide_after_messages'] ?? 5),
            'setting' => $resolvedSetting,
            'add_friend' => (int) ($allSettings['add_friend'] ?? 0),
        );
    }

    /** 构造统一的返回上下文。 */
    protected function buildContext(array $messageResult, $messageCount, array $settings, array $userResult)
    {
        return array(
            'eligible' => true,
            'last_message' => $messageResult['message'],
            'message_count' => (int) $messageCount,
            'guide_threshold' => (int) $settings['guide_after_messages'],
            'setting' => (int) $settings['setting'],
            'add_friend' => (int) $settings['add_friend'],
            'send_num' => (int) ($userResult['send_num'] ?? 0),
        );
    }

    /** 当达到钩子阈值时返回钩子话术。 */
    protected function hookReply($userId, $messageCount, $guideAfterMessages)
    {
        if ($messageCount < $guideAfterMessages + 1) {
            return null;
        }

        return $this->scriptContent($userId, '钩子话术');
    }

    /** 当达到引导阈值时返回引导话术。 */
    protected function guideReply($userId, $messageCount, $guideAfterMessages)
    {
        if ($messageCount < $guideAfterMessages) {
            return null;
        }

        return $this->scriptContent($userId, '引导话术');
    }

    /** 读取指定名称的话术内容。 */
    protected function scriptContent($userId, $name)
    {
        $script = Script::findByName($name, $userId);

        if (!$script || empty($script['content'])) {
            return null;
        }

        return $script['content'];
    }

    /** 合并基础上下文与附加业务字段。 */
    protected function responsePayload(array $context, array $extra)
    {
        return array_merge($context, $extra);
    }

    /** 调用 DeepSeek 生成兜底回复。 */
    protected function callDeepseek($momoid, $sendId, array $context, $token)
    {
        $deepseekUrl = 'http://' . ($_SERVER['HTTP_HOST'] ?? 'nginx') . '/api/deepseek';
        $payload = array(
            'prompt' => $context['last_message'],
            'momoid' => $momoid,
            'send_id' => $sendId,
            'token' => $token,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $deepseekUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new RuntimeException('调用deepseek接口失败，HTTP状态码: ' . $httpCode, 500);
        }

        $result = json_decode($response, true);
        if (!isset($result['success']) || !$result['success']) {
            throw new RuntimeException('生成话术失败: ' . ($result['message'] ?? '未知错误'), 500);
        }

        return $this->responsePayload($context, array(
            'generated_message' => $result['data']['response'],
            'is_guide' => false,
            'usage' => $result['data']['usage'] ?? array(),
            'should_block' => false,
        ));
    }
}
