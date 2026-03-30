<?php

/** 关键词检测控制器。 */
class CheckKeywordController extends BaseController
{
    /** 检查最近回复是否命中关键词。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'momoid' => '请提供陌陌用户ID和发送者ID',
                'send_id' => '请提供陌陌用户ID和发送者ID',
            ));

            $user = $this->user();
            $result = (new ChatService())->matchReplyKeyword(
                $this->input('momoid'),
                $this->input('send_id'),
                $user['id'] ?? 0,
                (int) ($user['role'] ?? 0) === 1
            );

            response(true, $result, $result['matched'] ? '找到匹配的关键词' : '未找到匹配的关键词', 200);
        } catch (RuntimeException $e) {
            response(false, array(), $e->getMessage(), 404);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new CheckKeywordController();
