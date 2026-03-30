<?php

/** 在线好友上报控制器。 */
class OnlineUsersController extends BaseController
{
    /** 上报当前主账号下的在线好友列表。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'momoid' => 'momoid不能为空',
            ));
            $this->requireArrayField('data', '请提供在线好友ID数组');

            $service = new MomoService();
            $service->ensureSchema();

            $result = $service->reportOnlineUsers(
                $this->input('momoid'),
                $this->input('data'),
                $this->user()
            );

            response(true, $result['data'], $result['message'], $result['code']);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new OnlineUsersController();
