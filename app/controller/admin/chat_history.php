<?php

/** 后台聊天记录控制器。 */
class AdminChatHistoryController extends BaseController
{
    /** 返回当前会话的基础信息。 */
    public function handle()
    {
        try {
            $data = (new AdminChatHistoryService())->conversation($this->user(), (int) $this->request()->query('momo_user_id', 0));
            response(true, $data, '获取成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    /** 手动新增会话消息。 */
    public function send()
    {
        $this->requirePost();

        try {
            $data = (new AdminChatHistoryService())->send($this->user(), $this->request()->all());
            response(true, $data, '发送成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new AdminChatHistoryController();
