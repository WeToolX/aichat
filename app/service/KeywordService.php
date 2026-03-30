<?php

/** 关键词业务服务，对关键词模型做业务层包装。 */
class KeywordService
{
    /** 根据内容匹配关键词配置。 */
    public function matchByContent($content, $userId, $isSuper)
    {
        return Keyword::matchByContent($content, $userId, $isSuper);
    }
}
