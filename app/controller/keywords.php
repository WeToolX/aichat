<?php

/** 关键词匹配控制器。 */
class KeywordsController extends BaseController
{
    /** 关键词匹配入口。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'action' => '无效的操作类型',
                'keywords' => '匹配关键词不能为空',
            ));

            if ($this->input('action') !== 'match') {
                response(false, array(), '无效的操作类型', 400);
            }

            $user = $this->user();
            $service = new KeywordService();
            $matched = $service->matchByContent($this->input('keywords'), $user['id'] ?? 0, (int) ($user['role'] ?? 0) === 1);

            if (!$matched) {
                response(false, array(), '未找到匹配的关键词', 404);
            }

            response(true, $matched, '匹配成功', 200);
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        }
    }
}

return new KeywordsController();
