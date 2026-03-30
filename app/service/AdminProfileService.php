<?php

/** 后台个人资料服务。 */
class AdminProfileService
{
    /** 返回当前用户资料。 */
    public function profile(array $user)
    {
        return array(
            'id' => (int) ($user['id'] ?? 0),
            'username' => $user['username'] ?? '',
            'role' => (int) ($user['role'] ?? 0),
            'email' => $user['email'] ?? '',
        );
    }

    /** 修改当前用户密码。 */
    public function changePassword(array $user, array $payload)
    {
        $currentPassword = (string) ($payload['current_password'] ?? '');
        $newPassword = (string) ($payload['new_password'] ?? '');
        $confirmPassword = (string) ($payload['confirm_password'] ?? '');

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            throw new RuntimeException('所有密码字段不能为空', 400);
        }

        if ($newPassword !== $confirmPassword) {
            throw new RuntimeException('两次输入的新密码不一致', 400);
        }

        $currentUser = User::find((int) $user['id']);
        if (!$currentUser || !password_verify($currentPassword, $currentUser['password'])) {
            throw new RuntimeException('当前密码错误', 400);
        }

        User::updateBy(array('id' => (int) $user['id']), array(
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
        ));

        return true;
    }
}
