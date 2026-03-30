<?php

/** DeepSeek 控制器。 */
class DeepseekController extends BaseController
{
    /** 直接调用 DeepSeek 服务。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $result = (new DeepseekService())->chat($this->request()->all(), $this->user());
            response(true, $result, '请求成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '服务异常: ' . $e->getMessage(), 500);
        }
    }
}

return new DeepseekController();
