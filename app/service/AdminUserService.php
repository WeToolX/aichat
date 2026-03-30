<?php

/** 后台用户管理服务。 */
class AdminUserService
{
    protected $defaultScripts = array(
        array(
            'name' => 'AI设置',
            'content' => "你叫小暖，我是一个家庭主妇，30岁性格温柔体贴，离婚两年了，没有小孩，现在是单身状态，平时能积极跟别人聊天，聊天的时候非常幽默，偶尔还会说一些土味情话。\n\n你的回复规则:\n1.回复简洁明了，像真人聊天一样自然\n2.只回复一到两句话，保持自然对话风格\n3.热情问答，有问有答，了解对方的基本情况\n4.绝对不要提及QQ和微信字样\n5.除了年龄外不要出现英文和数字\n6.回复要真实自然，像一个女孩真实的聊天方式\n7.主动或根据对方情况提出问题了解对方\n8.语气要温柔体贴\n9.如果对方会问你是哪里的，一定要回答，我是本地的\n10.如果对方添加你为好友或者提示消息过于打扰，就随便发送你好一类的打招呼",
        ),
        array(
            'name' => '引导话术',
            'content' => '聊的挺愉快的，换个地方？',
        ),
    );

    /** 列出全部用户。 */
    public function listing()
    {
        return User::queryAll('SELECT id, username, email, role, created_at, updated_at FROM users ORDER BY id DESC');
    }

    /** 新增或更新用户。 */
    public function save(array $payload)
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $username = trim((string) ($payload['username'] ?? ''));
        $role = isset($payload['role']) ? (int) $payload['role'] : 2;
        $password = (string) ($payload['password'] ?? '');
        $email = trim((string) ($payload['email'] ?? ''));

        if ($username === '') {
            throw new RuntimeException('用户名不能为空', 400);
        }

        if ($id > 0) {
            $existing = User::find($id);
            if (!$existing) {
                throw new RuntimeException('用户不存在', 404);
            }

            $duplicate = User::queryOne(
                'SELECT id FROM users WHERE username = :username AND id != :id LIMIT 1',
                array(':username' => $username, ':id' => $id)
            );
            if ($duplicate) {
                throw new RuntimeException('用户名已存在', 409);
            }

            $data = array('username' => $username, 'role' => $role);
            if ($email !== '') {
                $data['email'] = $email;
            }
            if ($password !== '') {
                $data['password'] = password_hash($password, PASSWORD_DEFAULT);
            }

            User::updateBy(array('id' => $id), $data);
            return User::queryOne('SELECT id, username, email, role, created_at, updated_at FROM users WHERE id = :id', array(':id' => $id));
        }

        if ($password === '') {
            throw new RuntimeException('新增用户时密码不能为空', 400);
        }

        $duplicate = User::queryOne('SELECT id FROM users WHERE username = :username LIMIT 1', array(':username' => $username));
        if ($duplicate) {
            throw new RuntimeException('用户名已存在', 409);
        }

        $newId = User::create(array(
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email !== '' ? $email : ($username . '@local.test'),
            'role' => $role,
        ));

        $this->ensureDefaultScripts($newId);

        return User::queryOne('SELECT id, username, email, role, created_at, updated_at FROM users WHERE id = :id', array(':id' => $newId));
    }

    /** 删除用户。 */
    public function delete($id, array $currentUser)
    {
        $id = (int) $id;
        if ($id <= 0) {
            throw new RuntimeException('无效的用户ID', 400);
        }

        if ($id === (int) ($currentUser['id'] ?? 0)) {
            throw new RuntimeException('不能删除当前登录用户', 400);
        }

        $target = User::find($id);
        if (!$target) {
            throw new RuntimeException('用户不存在', 404);
        }

        if ((int) ($target['role'] ?? 0) === 1) {
            throw new RuntimeException('不能删除超级用户', 400);
        }

        User::db()->query('DELETE FROM users WHERE id = :id LIMIT 1', array(':id' => $id));
        return true;
    }

    protected function ensureDefaultScripts($userId)
    {
        foreach ($this->defaultScripts as $script) {
            $exists = Script::findOneBy(array('user_id' => $userId, 'name' => $script['name']));
            if ($exists) {
                continue;
            }

            Script::create(array(
                'user_id' => $userId,
                'name' => $script['name'],
                'content' => $script['content'],
            ));
        }
    }
}
