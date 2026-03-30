<?php

/** 会话资料查询控制器。 */
class SessionMessageController extends BaseController
{
    /** 批量查询会话资料入口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $payload = $this->request()->all();
            $sessions = (new RemoteProfileService())->parseSessions($payload);
            $this->requireValue($sessions, '输入数据格式错误或为空');

            $results = (new RemoteProfileService())->processSessions($sessions);
            response(true, $results, '处理完成，共查询' . count($sessions) . '个会话，找到' . count($results) . '个有效数据', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new SessionMessageController();
