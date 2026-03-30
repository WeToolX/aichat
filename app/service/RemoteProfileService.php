<?php

/** 远程资料服务，负责批量拉取远程用户资料并做筛选转换。 */
class RemoteProfileService
{
    /** 解析 session_message 接口传入的会话列表。 */
    public function parseSessions(array $data)
    {
        $momoid = $data['momoid'] ?? '';
        $remoteIds = array();

        if (isset($data['remoteids']) && is_array($data['remoteids'])) {
            $remoteIds = $data['remoteids'];
        } elseif (isset($data[0]['SESSION_ID'])) {
            $remoteIds = $data;
        }

        $sessions = array();
        foreach ($remoteIds as $item) {
            if (isset($item['SESSION_ID'])) {
                $sessions[] = array(
                    'session_id' => $item['SESSION_ID'],
                    'momoid' => $momoid,
                );
            }
        }

        return $sessions;
    }

    /** 分批拉取远程会话资料。 */
    public function processSessions(array $sessions)
    {
        if (empty($sessions)) {
            return array();
        }

        $results = array();
        foreach (array_chunk($sessions, 20) as $batch) {
            $results = array_merge($results, $this->processBatch($batch));
            usleep(100000);
        }
        return $results;
    }

    /** 兼容命名更明确的调用入口。 */
    public function fetchSessionProfiles(array $sessions)
    {
        return $this->processSessions($sessions);
    }

    /** 解析 remoteid_check 接口参数。 */
    public function parseRemoteIds(array $data)
    {
        if (isset($data['remoteids']) && is_array($data['remoteids'])) {
            $items = $data['remoteids'];
        } else {
            $items = isset($data[0]) ? $data : array();
        }

        $requests = array();
        foreach ($items as $item) {
            if (isset($item['m_remoteid'])) {
                $requests[] = array(
                    'id' => (string) $item['m_remoteid'],
                    'm_msginfo' => $item['m_msginfo'] ?? null,
                );
            }
        }

        return $requests;
    }

    /** 获取 remoteid_check 结果。 */
    public function fetchRemoteIds(array $requests)
    {
        return $this->fetchProfiles($requests, array($this, 'filterRemoteIdResponse'));
    }

    /** 获取 last_message 结果。 */
    public function fetchLastMessageProfiles(array $requests)
    {
        return $this->fetchProfiles($requests, array($this, 'filterLastMessageResponse'));
    }

    /** 处理一批会话资料拉取。 */
    protected function processBatch(array $sessions)
    {
        $requests = array();
        foreach ($sessions as $session) {
            $sessionId = $session['session_id'] ?? null;
            if (!$sessionId || !$this->checkUserConditions($sessionId, $session['momoid'] ?? '')) {
                continue;
            }
            $requests[] = array('id' => (string) $sessionId);
        }

        return $this->fetchProfiles($requests, array($this, 'filterProfileResponse'));
    }

    /** 过滤基础远程资料结果。 */
    protected function filterProfileResponse($response, $httpCode, $sessionId)
    {
        if ($httpCode !== 200 || !$response || strpos($response, '"ec":0') === false || strpos($response, '"profile"') === false) {
            return null;
        }

        $result = json_decode($response, true);
        $profile = $result['data']['profile'] ?? null;
        if (!$profile || ($profile['sex'] ?? null) !== 'M') {
            return null;
        }

        $isOnline = false;
        if (($profile['profile_onlinetag']['type'] ?? null) === 'ONLINE') {
            $isOnline = true;
        } elseif (($profile['online_status'] ?? null) === 1) {
            if (isset($profile['loc_timesec'])) {
                $isOnline = (time() - (int) $profile['loc_timesec']) <= 300;
            } else {
                $isOnline = true;
            }
        }

        if (!$isOnline) {
            return null;
        }

        return array(
            'id' => $sessionId,
            'sex' => $profile['sex'],
            'status' => 'ONLINE',
            'nickname' => $profile['name'] ?? '',
            'chat_id' => MomoSingle::single($sessionId),
        );
    }

    /** 过滤 remoteid_check 的返回结构。 */
    protected function filterRemoteIdResponse($response, $httpCode, $request)
    {
        $result = $this->filterProfileResponse($response, $httpCode, $request['id']);
        if (!$result) {
            return null;
        }

        unset($result['chat_id']);
        if (isset($request['m_msginfo']) && $request['m_msginfo'] !== null) {
            $result['m_msginfo'] = $request['m_msginfo'];
        }

        return $result;
    }

    /** 过滤 last_message 的返回结构。 */
    protected function filterLastMessageResponse($response, $httpCode, $request)
    {
        $result = $this->filterProfileResponse($response, $httpCode, $request['id']);
        if (!$result) {
            return null;
        }

        unset($result['chat_id']);
        $message = $request['message'] ?? array();

        return array(
            'id' => $request['id'],
            'last_interaction' => $message['c5message_timestamp'] ?? '',
            'message_content' => $message['c2mct'] ?? '',
            'sex' => $result['sex'],
            'status' => $result['status'],
            'nickname' => $result['nickname'],
        );
    }

    /** 判断会话是否满足抓取条件。 */
    protected function checkUserConditions($sessionId, $momoid = '')
    {
        try {
            if ($momoid !== '') {
                $user = MomoUser::findUnblockedBySession($sessionId, $momoid);
            } else {
                $user = MomoUser::findUnblockedBySession($sessionId);
            }

            if (!$user) {
                return true;
            }

            $message = ChatMessage::latestIncomingType($user['id']);

            return !$message || (int) ($message['m_type'] ?? -1) === 0;
        } catch (Exception $e) {
            return true;
        }
    }

    /** 生成远程资料接口地址。 */
    protected function remoteUrl($sessionId)
    {
        return 'http://134.122.185.10/ch/c.php?sessionid=0&remoteid=' . $sessionId;
    }

    /** 执行并发请求并交给过滤器统一处理。 */
    protected function fetchProfiles(array $requests, callable $filter)
    {
        if (empty($requests)) {
            return array();
        }

        $mh = curl_multi_init();
        $handles = array();
        $results = array();
        curl_multi_setopt($mh, CURLMOPT_MAXCONNECTS, 10);

        foreach ($requests as $request) {
            $requestId = $request['id'] ?? null;
            if (!$requestId) {
                continue;
            }

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->remoteUrl($requestId));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
            curl_setopt($ch, CURLOPT_ENCODING, '');
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_multi_add_handle($mh, $ch);
            $handles[] = array('handle' => $ch, 'request' => $request);
        }

        $running = null;
        $start = microtime(true);
        do {
            $status = curl_multi_exec($mh, $running);
            if ($status > 0) {
                break;
            }
            $active = curl_multi_select($mh, 0.1);
            if ($active === -1) {
                usleep(100);
            }
            if (microtime(true) - $start > 30) {
                break;
            }
        } while ($running > 0);

        foreach ($handles as $item) {
            $ch = $item['handle'];
            $request = $item['request'];
            $response = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            $filtered = call_user_func($filter, $response, $httpCode, $request);
            if ($filtered) {
                $results[] = $filtered;
            }
        }

        curl_multi_close($mh);
        return $results;
    }
}
