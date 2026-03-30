<?php

/** 账号检测控制器。 */
class CheckAccountController extends BaseController
{
    /** 检查账号是否满足自动回复条件。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'momoid' => '请提供陌陌用户ID和发送者ID',
                'send_id' => '请提供陌陌用户ID和发送者ID',
            ));

            $setting = array_key_exists('setting', $this->request()->all()) ? $this->input('setting') : null;
            $user = $this->user();
            $result = (new AccountCheckService())->check(
                $this->input('momoid'),
                $this->input('send_id'),
                $user['id'] ?? 0,
                $user['role'] ?? 0,
                $setting,
                $this->input('token', '')
            );

            $message = '账号符合条件，已生成话术';
            if (!empty($result['is_hook'])) {
                $message = '账号符合条件，返回钩子话术';
            } elseif (!empty($result['is_guide'])) {
                $message = '账号符合条件，返回引导话术';
            } elseif (!empty($result['is_keyword'])) {
                $message = '账号符合条件，返回关键词回复';
            }

            response(true, $result, $message, 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new CheckAccountController();
