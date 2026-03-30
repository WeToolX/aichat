<?php

/** chat_id 生成控制器。 */
class ChatIdController extends BaseController
{
    /** 生成指定 send_momoid 的 chat_id。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'send_momoid' => '请提供send_momoid参数',
            ));

            $chatId = (new ChatService())->generateChatId($this->input('send_momoid'));
            response(true, array('chat_id' => $chatId), 'Chat ID生成成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new ChatIdController();
