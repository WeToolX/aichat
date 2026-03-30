<?php

/** 认证服务，负责登录、Token 校验、用户挂载与权限判断。 */
class Auth
{
    protected $db;
    protected $redis;
    protected $config;

    /** 初始化认证组件。 */
    public function __construct(?Database $db = null, ?RedisStore $redis = null, ?array $config = null)
    {
        $this->db = $db ?: App::db();
        $this->redis = $redis ?: App::redis();
        $this->config = $config ?: app('config');
    }

    /** 执行用户名密码登录并下发 token。 */
    public function login($username, $password)
    {
        $user = User::queryOne('SELECT * FROM users WHERE username = :username LIMIT 1', array(':username' => $username));
        if (!$user || !password_verify($password, $user['password'])) {
            return array('success' => false, 'message' => '用户名或密码错误');
        }

        $token = bin2hex(random_bytes(24));
        $ttl = (int) $this->config['app']['token_ttl'];
        $sanitizedUser = $this->sanitizeUser($user);
        $this->redis->set($this->tokenKey($token), $sanitizedUser, $ttl);

        return array(
            'success' => true,
            'token' => $token,
            'user' => $sanitizedUser,
        );
    }

    /** 校验 token 是否有效。 */
    public function validateToken($token)
    {
        if (empty($token)) {
            return array('valid' => false, 'message' => '请提供令牌');
        }

        $payload = $this->redis->get($this->tokenKey($token));
        if (!$payload || !is_array($payload)) {
            return array('valid' => false, 'message' => '令牌无效或已过期');
        }

        $this->redis->expire($this->tokenKey($token), (int) $this->config['app']['token_ttl']);
        App::setUser($payload);

        return array('valid' => true, 'user' => $payload);
    }

    /** 强制要求当前请求已登录。 */
    public function requireUser()
    {
        $token = $this->extractToken();
        $validation = $this->validateToken($token);
        if (!$validation['valid']) {
            response(false, array(), $validation['message'], 401);
        }

        return $validation['user'];
    }

    /** 从 query、body、header 中提取 token。 */
    public function extractToken()
    {
        $request = request();
        $token = $request->query('token');

        if (empty($token)) {
            $token = $request->input('token');
        }

        if (empty($token)) {
            $headers = array(
                'authorization',
                'x-token',
                'token',
                'x-authorization',
            );

            foreach ($headers as $header) {
                $value = $request->header($header);
                if (!empty($value)) {
                    $token = $value;
                    break;
                }
            }
        }

        return trim(str_ireplace('Bearer ', '', (string) $token));
    }

    /** 获取当前用户。 */
    public function user()
    {
        return App::user();
    }

    /** 兼容旧代码的取用户方法。 */
    public function getUser()
    {
        return $this->user();
    }

    /** 判断是否已登录。 */
    public function isLoggedIn()
    {
        return $this->user() !== null;
    }

    /** 判断当前用户是否为超级管理员。 */
    public function isSuperUser()
    {
        $user = $this->user();
        return $user && (int) array_get($user, 'role', 0) === 1;
    }

    /** 生成 Redis 中的 token 键名。 */
    protected function tokenKey($token)
    {
        return $this->config['app']['token_prefix'] . $token;
    }

    /** 去除不应暴露给前端的用户字段。 */
    protected function sanitizeUser(array $user)
    {
        unset($user['password']);
        return $user;
    }
}
