<?php

/** 陌陌批量更新控制器。 */
class MomoBatchUpdateController extends BaseController
{
    /** 处理陌陌批量导入更新。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $result = (new MomoService())->batchUpdate($this->request()->all(), $this->user());
            response(true, $result['data'], $result['message'], $result['code']);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new MomoBatchUpdateController();
