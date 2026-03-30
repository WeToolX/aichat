<?php

/** remoteid 批量筛选控制器。 */
class RemoteIdCheckController extends BaseController
{
    /** 批量筛选 remoteid 资料。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $service = new RemoteProfileService();
            $requests = $service->parseRemoteIds($this->request()->all());
            $this->requireValue($requests, '输入数据格式错误或为空');

            $results = $service->fetchRemoteIds($requests);
            response(true, $results, '处理完成，共查询' . count($requests) . '个ID，找到' . count($results) . '个有效数据', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new RemoteIdCheckController();
