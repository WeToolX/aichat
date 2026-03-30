<?php

/** 聊天历史控制器。 */
class ChatHistoryController extends BaseController
{
    /** 返回指定会话的聊天历史。 */
    public function handle()
    {
        try {
            $momoUserId = (int) $this->request()->query('momo_user_id', 0);
            $this->requirePositiveInt($momoUserId, '缺少必要参数');
            $messages = (new ChatService())->getHistory($momoUserId, $this->user()['id']);
            response(true, $messages, '获取聊天记录成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 404);
        } catch (Exception $e) {
            response(false, array(), '获取聊天记录失败: ' . $e->getMessage(), 500);
        }
    }
}

return new ChatHistoryController();
