<?php

/** 话术匹配控制器。 */
class ScriptsController extends BaseController
{
    /** 话术匹配入口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'action' => '无效的操作类型',
                'massage' => '匹配内容不能为空',
            ));

            if ($this->input('action') !== 'match') {
                response(false, array(), '无效的操作类型', 400);
            }

            $user = $this->user();
            $service = new ScriptService();
            $matched = $service->matchByName($this->input('massage'), $user['id'] ?? 0, (int) ($user['role'] ?? 0) === 1);

            if (!$matched) {
                response(false, array(), '未找到匹配的话术', 404);
            }

            response(true, $matched, '匹配成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new ScriptsController();
