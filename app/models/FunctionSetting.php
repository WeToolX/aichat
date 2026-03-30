<?php

/** 功能设置模型，对应用户的自动化配置表。 */
class FunctionSetting extends BaseModel
{
    protected static $table = 'function_settings';

    /** 获取用户的功能设置。 */
    public static function findByUserId($userId)
    {
        return static::findOneBy(array('user_id' => $userId));
    }
}
