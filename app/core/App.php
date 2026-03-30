<?php

/** 应用容器，负责托管请求、数据库、Redis、认证与当前用户上下文。 */
class App
{
    protected static $container = array();

    /** 初始化应用容器中的基础对象。 */
    public static function bootstrap(array $config)
    {
        static::$container['config'] = $config;
        static::$container['request'] = new Request();
        static::$container['db'] = null;
        static::$container['redis'] = null;
        static::$container['auth'] = null;
        static::$container['user'] = null;
        $GLOBALS['current_user'] = null;
    }

    /** 从容器中读取服务。 */
    public static function get($key, $default = null)
    {
        return array_key_exists($key, static::$container) ? static::$container[$key] : $default;
    }

    /** 向容器中写入服务。 */
    public static function set($key, $value)
    {
        static::$container[$key] = $value;
    }

    /** 返回当前容器中的全部服务。 */
    public static function all()
    {
        return static::$container;
    }

    /** 获取当前请求对象。 */
    public static function request()
    {
        return static::get('request');
    }

    /** 获取认证服务，按需初始化。 */
    public static function auth()
    {
        if (!static::$container['auth']) {
            static::$container['auth'] = new Auth(static::db(), static::redis(), static::get('config'));
        }

        return static::get('auth');
    }

    /** 获取数据库服务，按需初始化。 */
    public static function db()
    {
        if (!static::$container['db']) {
            static::$container['db'] = Database::getInstance(static::get('config')['database']);
        }

        return static::get('db');
    }

    /** 获取 Redis 服务，按需初始化。 */
    public static function redis()
    {
        if (!static::$container['redis']) {
            static::$container['redis'] = RedisStore::getInstance(static::get('config')['redis']);
        }

        return static::get('redis');
    }

    /** 获取当前登录用户。 */
    public static function user()
    {
        return static::get('user');
    }

    /** 将当前用户挂载到全局上下文。 */
    public static function setUser($user)
    {
        static::set('user', $user);
        $GLOBALS['current_user'] = $user;
        if ($user !== null) {
            $_SESSION['user'] = $user;
        }
    }
}
