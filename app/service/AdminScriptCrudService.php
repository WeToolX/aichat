<?php

/** 后台话术管理服务。 */
class AdminScriptCrudService
{
    protected $requiredNames = array('AI设置', '引导话术');

    /** 查询当前用户可见的话术列表。 */
    public function listing(array $user)
    {
        $isSuper = (int) ($user['role'] ?? 0) === 1;
        $params = array();
        $sql = 'SELECT id, user_id, name, content, created_at, updated_at FROM scripts';

        if (!$isSuper) {
            $sql .= ' WHERE user_id = :user_id';
            $params[':user_id'] = (int) $user['id'];
        }

        $sql .= ' ORDER BY updated_at DESC, id DESC';
        return Script::queryAll($sql, $params);
    }

    /** 新增或更新话术。 */
    public function save(array $payload, array $user)
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;

        if ($name === '' || $content === '') {
            throw new RuntimeException('话术名称和内容不能为空', 400);
        }

        if ($id > 0) {
            $record = $this->findOwned($id, $user);
            $duplicate = Script::findOneBy(array('user_id' => $record['user_id'], 'name' => $name, 'id !=' => $id));
            if ($duplicate) {
                throw new RuntimeException('同名话术已存在', 409);
            }

            Script::updateBy(array('id' => $id), array(
                'name' => $name,
                'content' => $content,
            ));

            return Script::find($id);
        }

        $ownerId = (int) $user['id'];
        $duplicate = Script::findOneBy(array('user_id' => $ownerId, 'name' => $name));
        if ($duplicate) {
            throw new RuntimeException('同名话术已存在', 409);
        }

        $newId = Script::create(array(
            'user_id' => $ownerId,
            'name' => $name,
            'content' => $content,
        ));

        return Script::find($newId);
    }

    /** 删除话术。 */
    public function delete($id, array $user)
    {
        $record = $this->findOwned((int) $id, $user);
        if (in_array($record['name'], $this->requiredNames, true)) {
            throw new RuntimeException($record['name'] . ' 为系统必须的基础话术，不能删除', 400);
        }

        Script::db()->query('DELETE FROM scripts WHERE id = :id LIMIT 1', array(':id' => (int) $id));
        return true;
    }

    protected function findOwned($id, array $user)
    {
        $record = Script::find($id);
        if (!$record) {
            throw new RuntimeException('话术不存在', 404);
        }

        if ((int) ($user['role'] ?? 0) !== 1 && (int) $record['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('无权限操作该话术', 403);
        }

        return $record;
    }
}
