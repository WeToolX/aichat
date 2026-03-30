<?php

/** Redis 封装层，供认证与缓存类逻辑统一使用。 */
class RedisStore
{
    protected static $instance;
    protected $client;

    /** 初始化 Redis 连接。 */
    public function __construct(array $config)
    {
        if (!class_exists('Redis')) {
            throw new RuntimeException('PHP Redis 扩展未安装，无法启用 Redis 鉴权。');
        }

        $this->client = new Redis();
        $this->client->connect($config['host'], $config['port'], $config['timeout']);

        if (!empty($config['password'])) {
            $this->client->auth($config['password']);
        }

        $this->client->select((int) $config['database']);
    }

    /** 获取 Redis 单例。 */
    public static function getInstance(?array $config = null)
    {
        if (!static::$instance) {
            if ($config === null) {
                $config = app('config')['redis'];
            }
            static::$instance = new static($config);
        }

        return static::$instance;
    }

    /** 写入缓存数据，可设置过期时间。 */
    public function set($key, $value, $ttl = 0)
    {
        $payload = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($ttl > 0) {
            return $this->client->setex($key, (int) $ttl, $payload);
        }

        return $this->client->set($key, $payload);
    }

    /** 读取缓存数据，并尝试自动反序列化 JSON。 */
    public function get($key, $assoc = true)
    {
        $value = $this->client->get($key);
        if ($value === false || $value === null) {
            return null;
        }

        $decoded = json_decode($value, $assoc);
        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    /** 删除缓存键。 */
    public function delete($key)
    {
        return $this->client->del($key);
    }

    /** 设置缓存过期时间。 */
    public function expire($key, $ttl)
    {
        return $this->client->expire($key, (int) $ttl);
    }
}
