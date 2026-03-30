<?php

/** 最后消息查询控制器。 */
class LastMessageController extends BaseController
{
    /** 批量查询最后消息接口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $messages = (new LastMessageService())->parseMessages($this->request()->all());
            $this->requireValue($messages, '输入数据格式错误或为空');

            $results = (new LastMessageService())->processMessages($messages);
            response(true, $results, '处理完成，共查询' . count($messages) . '条消息，找到' . count($results) . '个有效数据', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new LastMessageController();
