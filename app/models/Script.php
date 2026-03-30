<?php

/** 话术模型，负责脚本/提示词类配置的读取与匹配。 */
class Script extends BaseModel
{
    protected static $table = 'scripts';

    /** 按名称查询话术，可选限制用户。 */
    public static function findByName($name, $userId = null)
    {
        if ($userId === null) {
            return static::findOneBy(array('name' => $name));
        }

        return static::findOneBy(array('user_id' => $userId, 'name' => $name));
    }

    /** 匹配话术名称，超管可跨用户读取。 */
    public static function matchByName($name, $userId, $isSuper)
    {
        if ($isSuper) {
            return static::findOneBy(array('name' => $name));
        }

        return static::findOneBy(array('name' => $name, 'user_id' => $userId));
    }
}
