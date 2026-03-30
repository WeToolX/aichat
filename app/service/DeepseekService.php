<?php

/** DeepSeek 服务，负责组装上下文并调用大模型生成回复。 */
class DeepseekService
{
    protected $config;
    protected $db;

    /** 读取 DeepSeek 配置与数据库服务。 */
    public function __construct()
    {
        $this->config = app('config')['deepseek'];
        $this->db = App::db();
    }

    /** 组装上下文后调用 DeepSeek 对话接口。 */
    public function chat(array $payload, ?array $user = null)
    {
        $prompt = $payload['prompt'] ?? '';
        $history = is_array($payload['history'] ?? null) ? $payload['history'] : array();
        $momoid = $payload['momoid'] ?? '';
        $sendId = $payload['send_id'] ?? '';

        $system = $this->buildSystemPrompt($user, $momoid, $sendId);
        $messages = array();
        if ($system !== '') {
            $messages[] = array('role' => 'system', 'content' => $system);
        }

        list($processedHistory, $prompt) = $this->buildConversation($prompt, $history, $momoid, $sendId);
        foreach ($processedHistory as $message) {
            $messages[] = $message;
        }
        if ($prompt !== '') {
            $messages[] = array('role' => 'user', 'content' => $prompt);
        }

        $requestData = array(
            'model' => $this->config['default_model'] ?: 'deepseek-chat',
            'messages' => $messages,
            'max_tokens' => (int) ($this->config['max_tokens'] ?: 1024),
            'temperature' => (float) ($this->config['temperature'] ?: 0.7),
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['api_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->config['api_key'],
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            $message = 'HTTP错误: ' . $httpCode;
            if ($curlError) {
                $message .= ', CURL错误: ' . $curlError;
            }
            if ($response) {
                $error = json_decode($response, true);
                if (isset($error['error']['message'])) {
                    $message .= ', API错误: ' . $error['error']['message'];
                }
            }
            throw new RuntimeException('API请求失败: ' . $message, 500);
        }

        $result = json_decode($response, true);
        $content = $result['choices'][0]['message']['content'] ?? '';
        if ($content === '') {
            throw new RuntimeException('API返回格式错误', 500);
        }

        $content = $this->normalizeGreeting($content, $user);

        return array(
            'response' => $content,
            'usage' => $result['usage'] ?? array(),
        );
    }

    /** 构造系统提示词，注入 AI 设置与当前时间。 */
    protected function buildSystemPrompt(?array $user = null, $momoid = '', $sendId = '')
    {
        $system = '';
        if ($user && isset($user['id'])) {
            $script = Script::findByName('AI设置', $user['id']);
            if (!empty($script['content'])) {
                $system = $script['content'];
            }
        }

        $datetime = $this->getNetworkTime();
        $currentHour = (int) $datetime->format('H');
        $ampm = $currentHour < 12 ? '上午' : '下午';
        $hour12 = $currentHour % 12;
        $hour12 = $hour12 === 0 ? 12 : $hour12;
        $timePrefix = '你现在的时间是：' . $datetime->format('Y年m月d日 ') . $ampm . $hour12 . '时' . $datetime->format('i分s秒') . "\n\n";

        $idInfo = '';
        if ($momoid !== '') {
            $idInfo .= "当前对话的陌陌用户ID：{$momoid}\n";
        }
        if ($sendId !== '') {
            $idInfo .= "当前对话的发送者ID：{$sendId}\n";
        }
        if ($idInfo !== '') {
            $idInfo .= "\n";
        }

        return $system . $timePrefix . $idInfo;
    }

    /** 尝试从外部时间接口获取更准确的当前时间。 */
    protected function getNetworkTime()
    {
        $timeApis = array(
            'http://worldtimeapi.org/api/timezone/Asia/Shanghai',
            'http://api.timezonedb.com/v2.1/get-time-zone?key=V39E5V7MK4JO&format=json&by=zone&zone=Asia/Shanghai',
            'http://www.timeapi.io/api/Time/current/zone?timeZone=Asia/Shanghai',
        );

        foreach ($timeApis as $url) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                continue;
            }

            $data = json_decode($response, true);
            if (isset($data['datetime'])) {
                return new DateTime($data['datetime']);
            }
            if (isset($data['formatted'])) {
                return new DateTime($data['formatted']);
            }
            if (isset($data['timestamp'])) {
                $datetime = new DateTime('@' . $data['timestamp']);
                $datetime->setTimezone(new DateTimeZone('Asia/Shanghai'));
                return $datetime;
            }
            if (isset($data['dateTime'])) {
                return new DateTime($data['dateTime']);
            }
        }

        return new DateTime('now', new DateTimeZone('Asia/Shanghai'));
    }

    /** 优先使用数据库会话记录构造对话上下文。 */
    protected function buildConversation($prompt, array $history, $momoid, $sendId)
    {
        $dbHistory = $this->loadDbHistory($momoid, $sendId);
        $processedHistory = array();
        $lastSelfMessageIndex = -1;

        if (!empty($dbHistory)) {
            foreach ($dbHistory as $index => $message) {
                if ((int) $message['sender_type'] === 1) {
                    $lastSelfMessageIndex = $index;
                }
            }

            for ($i = 0; $i <= $lastSelfMessageIndex; $i++) {
                if (!isset($dbHistory[$i])) {
                    continue;
                }
                $message = $dbHistory[$i];
                $processedHistory[] = array(
                    'role' => ((int) $message['sender_type'] === 1) ? 'assistant' : 'user',
                    'content' => $message['message'],
                );
            }

            $newPrompt = '';
            for ($i = $lastSelfMessageIndex + 1; $i < count($dbHistory); $i++) {
                if ((int) $dbHistory[$i]['sender_type'] === 0) {
                    $newPrompt .= $dbHistory[$i]['message'] . "\n";
                }
            }

            if (trim($newPrompt) !== '') {
                $prompt = trim($newPrompt);
            } elseif ($prompt === '') {
                $last = end($dbHistory);
                if ($last && (int) $last['sender_type'] === 0) {
                    $prompt = $last['message'];
                }
            }

            return array($processedHistory, $prompt);
        }

        if (!empty($history)) {
            foreach ($history as $index => $message) {
                if (!empty($message['is_self'])) {
                    $lastSelfMessageIndex = $index;
                }
            }

            for ($i = 0; $i <= $lastSelfMessageIndex; $i++) {
                if (!isset($history[$i])) {
                    continue;
                }
                $message = $history[$i];
                $processedHistory[] = array(
                    'role' => !empty($message['is_self']) ? 'assistant' : 'user',
                    'content' => $message['content'],
                );
            }

            $newPrompt = '';
            for ($i = $lastSelfMessageIndex + 1; $i < count($history); $i++) {
                if (empty($history[$i]['is_self'])) {
                    $newPrompt .= $history[$i]['content'] . "\n";
                }
            }

            if (trim($newPrompt) !== '') {
                $prompt = trim($newPrompt);
            } elseif ($prompt === '') {
                $last = end($history);
                if ($last && empty($last['is_self'])) {
                    $prompt = $last['content'];
                }
            }
        }

        return array($processedHistory, $prompt);
    }

    /** 从数据库读取指定会话的对话历史。 */
    protected function loadDbHistory($momoid, $sendId)
    {
        if ($momoid === '' || $sendId === '') {
            return array();
        }

        try {
            $userResult = MomoUser::findByMomoidPair($momoid, $sendId);
            if (!$userResult) {
                return array();
            }

            $history = ChatMessage::recentConversation($userResult['id'], 20);

            $processed = array();
            $isFirst = true;
            foreach ($history as $message) {
                if ($isFirst) {
                    if (in_array((int) $message['m_type'], array(0, 5), true)) {
                        $processed[] = $message;
                    }
                    $isFirst = false;
                } elseif ((int) $message['m_type'] === 0) {
                    $processed[] = $message;
                }
            }
            return $processed;
        } catch (Exception $e) {
            return array();
        }
    }

    /** 将默认问候语替换成业务配置中的问候回复。 */
    protected function normalizeGreeting($content, ?array $user = null)
    {
        $defaultGreetings = array(
            '你好！很高兴认识你，有什么我可以帮助你的吗？',
            '你好，很高兴认识你！有什么我可以帮助你的吗？',
            '你好，有什么我可以帮助你的吗？',
            '很高兴认识你，有什么我可以帮助你的吗？',
            '你好！有什么我可以帮助你的吗？',
        );

        foreach ($defaultGreetings as $greeting) {
            if (strpos($content, $greeting) !== false) {
                if ($user && isset($user['id'])) {
                    $result = Script::findByName('问候回复', $user['id']);
                    if (!empty($result['content'])) {
                        return $result['content'];
                    }
                }
                return '你好！';
            }
        }

        return $content;
    }
}
