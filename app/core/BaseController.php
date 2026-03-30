<?php

/** 控制器基类，封装请求、认证、参数校验等通用能力。 */
abstract class BaseController
{
    /** 获取请求对象。 */
    protected function request()
    {
        return request();
    }

    /** 获取认证服务。 */
    protected function auth()
    {
        return auth();
    }

    /** 获取当前用户。 */
    protected function user()
    {
        return auth_user();
    }

    /** 获取数据库实例。 */
    protected function db()
    {
        return App::db();
    }

    /** 读取请求参数。 */
    protected function input($key = null, $default = null)
    {
        return $this->request()->input($key, $default);
    }

    /** 强制要求请求方法为 POST。 */
    protected function requirePost()
    {
        if ($this->request()->method() !== 'POST') {
            response(false, array(), '只支持POST请求', 405);
        }
    }

    /** 校验必填字段集合。 */
    protected function requireFields(array $rules, $source = null)
    {
        $data = $source === null ? $this->request()->all() : $source;
        Validator::requireFields($data, $rules);
    }

    /** 校验指定字段必须为非空数组。 */
    protected function requireArrayField($field, $message, $source = null)
    {
        $data = $source === null ? $this->request()->all() : $source;
        Validator::requireArray($data, $field, $message);
    }

    /** 校验值必须为正整数。 */
    protected function requirePositiveInt($value, $message)
    {
        Validator::requirePositiveInt($value, $message);
    }

    /** 校验值不能为空。 */
    protected function requireValue($value, $message)
    {
        Validator::requireValue($value, $message);
    }
}
