<?php

/** 新版批量账号检测控制器。 */
class BatchCheckAccountV2Controller extends BaseController
{
    /** 处理新版批量账号检测入口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $result = (new BatchAccountService())->handleBatchV2($this->request()->all(), $this->user());
            response(true, $result['items'], $result['message'], 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new BatchCheckAccountV2Controller();
