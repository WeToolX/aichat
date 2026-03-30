<?php

/** 老版批量账号检测控制器，保留给旧客户端，后台页面未使用。 */
class BatchCheckAccountController extends BaseController
{
    /** 处理老版批量账号检测入口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $result = (new BatchAccountService())->handleBatch($this->request()->all(), $this->user());
            response(true, $result['items'], $result['message'], 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new BatchCheckAccountController();
