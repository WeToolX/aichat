<?php

/** 免鉴权健康检查控制器。 */
class PingController extends BaseController
{
    /** 返回健康检查结果。 */
    public function handle()
    {
        response(true, array(
            'pong' => true,
            'time' => date('Y-m-d H:i:s'),
            'path' => $this->request()->path(),
        ), 'pong', 200);
    }
}

return new PingController();
