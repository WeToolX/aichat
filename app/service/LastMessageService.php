<?php

/** 最后消息服务，负责解析并筛选 last_message 接口结果。 */
class LastMessageService
{
    /** 解析最后消息接口入参。 */
    public function parseMessages(array $payload)
    {
        if (isset($payload['remoteids']) && is_array($payload['remoteids'])) {
            return $payload['remoteids'];
        }

        return isset($payload[0]) ? $payload : array();
    }

    /** 批量获取符合条件的最后消息结果。 */
    public function processMessages(array $messages)
    {
        $profileService = new RemoteProfileService();
        $requests = array();

        foreach ($messages as $message) {
            if (isset($message['c4xid'])) {
                $requests[] = array(
                    'id' => (string) $message['c4xid'],
                    'message' => $message,
                );
            }
        }

        if (empty($requests)) {
            return array();
        }

        return $profileService->fetchLastMessageProfiles($requests);
    }
}
