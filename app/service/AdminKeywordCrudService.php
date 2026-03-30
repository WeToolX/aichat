<?php

/** 后台关键词管理服务。 */
class AdminKeywordCrudService
{
    /** 查询当前用户可见的关键词列表。 */
    public function listing(array $user)
    {
        $isSuper = (int) ($user['role'] ?? 0) === 1;
        $params = array();
        $sql = 'SELECT id, user_id, keyword, reply, created_at, updated_at FROM keywords';

        if (!$isSuper) {
            $sql .= ' WHERE user_id = :user_id';
            $params[':user_id'] = (int) $user['id'];
        }

        $sql .= ' ORDER BY updated_at DESC, id DESC';
        return Keyword::queryAll($sql, $params);
    }

    /** 新增或更新关键词。 */
    public function save(array $payload, array $user)
    {
        $keyword = trim((string) ($payload['keyword'] ?? ''));
        $reply = trim((string) ($payload['reply'] ?? ''));
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;

        if ($keyword === '' || $reply === '') {
            throw new RuntimeException('关键词和回复内容不能为空', 400);
        }

        if ($id > 0) {
            $record = $this->findOwned($id, $user);
            $duplicate = Keyword::findOneBy(array('user_id' => $record['user_id'], 'keyword' => $keyword, 'id !=' => $id));
            if ($duplicate) {
                throw new RuntimeException('同名关键词已存在', 409);
            }

            Keyword::updateBy(array('id' => $id), array(
                'keyword' => $keyword,
                'reply' => $reply,
            ));

            return Keyword::find($id);
        }

        $ownerId = (int) $user['id'];
        $duplicate = Keyword::findOneBy(array('user_id' => $ownerId, 'keyword' => $keyword));
        if ($duplicate) {
            throw new RuntimeException('同名关键词已存在', 409);
        }

        $newId = Keyword::create(array(
            'user_id' => $ownerId,
            'keyword' => $keyword,
            'reply' => $reply,
        ));

        return Keyword::find($newId);
    }

    /** 删除关键词。 */
    public function delete($id, array $user)
    {
        $this->findOwned((int) $id, $user);
        Keyword::db()->query('DELETE FROM keywords WHERE id = :id LIMIT 1', array(':id' => (int) $id));
        return true;
    }

    protected function findOwned($id, array $user)
    {
        $record = Keyword::find($id);
        if (!$record) {
            throw new RuntimeException('关键词不存在', 404);
        }

        if ((int) ($user['role'] ?? 0) !== 1 && (int) $record['user_id'] !== (int) $user['id']) {
            throw new RuntimeException('无权限操作该关键词', 403);
        }

        return $record;
    }
}
