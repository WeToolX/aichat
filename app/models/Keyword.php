<?php

/** 关键词模型，负责关键词回复配置的匹配查询。 */
class Keyword extends BaseModel
{
    protected static $table = 'keywords';

    /** 按输入内容匹配关键词回复。 */
    public static function matchByContent($content, $userId, $isSuper)
    {
        $query = "SELECT * FROM keywords WHERE :content LIKE CONCAT('%', keyword, '%')";
        $params = array(':content' => $content);

        if (!$isSuper) {
            $query .= ' AND user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        return static::queryOne($query, $params);
    }
}
