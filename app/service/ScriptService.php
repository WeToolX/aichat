<?php

/** 话术业务服务，对话术模型做业务层包装。 */
class ScriptService
{
    /** 根据名称匹配话术。 */
    public function matchByName($name, $userId, $isSuper)
    {
        return Script::matchByName($name, $userId, $isSuper);
    }
}
