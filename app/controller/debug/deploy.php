<?php

/** 调试部署控制器。 */
class DebugDeployController extends BaseController
{
    /** 调试部署接口兼容 GET/POST，避免部分代理层改写请求方法。 */
    protected function requireDeployMethod()
    {
        $method = $this->request()->method();
        if ($method !== 'GET' && $method !== 'POST') {
            response(false, array(), '只支持GET或POST请求', 405);
        }
    }

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
        $this->requireDeployMethod();

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
