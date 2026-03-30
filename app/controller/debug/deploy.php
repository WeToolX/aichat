<?php

/** 调试部署控制器。 */
class DebugDeployController extends BaseController
{
    /** 返回调试部署接口状态。 */
    public function handle()
    {
        try {
            $result = (new DebugDeployService())->status($this->request());
            response(true, $result, '调试部署接口可用', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }

    /** 执行固定拉码命令。 */
    public function pull()
    {
        $this->requirePost();

        try {
            $result = (new DebugDeployService())->pull($this->request());
            response(true, $result, '拉取成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new DebugDeployController();
