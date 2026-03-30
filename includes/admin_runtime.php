<?php

require_once __DIR__ . '/../app/bootstrap/app.php';

class AdminPageAuth
{
    protected $auth;
    protected $config;

    public function __construct()
    {
        $this->auth = App::auth();
        $this->config = app('config');
        $this->hydrateUser();
    }

    public function login($username, $password)
    {
        $result = $this->auth->login($username, $password);
        if (!empty($result['success']) && !empty($result['user'])) {
            App::setUser($result['user']);
            $_SESSION['admin_token'] = $result['token'] ?? '';
            return $result;
        }

        $result['error'] = $result['message'] ?? '用户名或密码错误';
        return $result;
    }

    public function logout()
    {
        App::setUser(null);
        unset($_SESSION['user'], $_SESSION['admin_token']);
    }

    public function isLoggedIn()
    {
        return $this->getUser() !== null;
    }

    public function getUser()
    {
        $this->hydrateUser();
        return App::user();
    }

    public function isSuperUser()
    {
        $user = $this->getUser();
        return $user && (int) ($user['role'] ?? 0) === 1;
    }

    public function requireLogin()
    {
        $user = $this->getUser();
        if ($user) {
            return $user;
        }

        header('Location: /login.php');
        exit;
    }

    public function requireSuperUser()
    {
        $user = $this->requireLogin();
        if ((int) ($user['role'] ?? 0) === 1) {
            return $user;
        }

        header('Location: /admin/index.php');
        exit;
    }

    public function generateToken($userId)
    {
        $user = $this->getUser();
        if (!$user || (int) ($user['id'] ?? 0) !== (int) $userId) {
            $user = User::queryOne('SELECT * FROM users WHERE id = :id LIMIT 1', array(':id' => (int) $userId));
            if (!$user) {
                return '';
            }
            unset($user['password']);
        }

        $token = bin2hex(random_bytes(24));
        $ttl = (int) ($this->config['app']['token_ttl'] ?? 86400 * 7);
        $prefix = $this->config['app']['token_prefix'] ?? 'aichat:token:';
        App::redis()->set($prefix . $token, $user, $ttl);

        return $token;
    }

    protected function hydrateUser()
    {
        if (App::user() === null && !empty($_SESSION['user']) && is_array($_SESSION['user'])) {
            App::setUser($_SESSION['user']);
        }
    }
}
