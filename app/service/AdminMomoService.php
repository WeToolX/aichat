<?php

/** 后台陌陌会话管理服务。 */
class AdminMomoService
{
    public function __construct()
    {
        (new MomoService())->ensureSchema();
    }

    /** 返回陌陌会话汇总与列表。 */
    public function listing(array $user, array $filters = array())
    {
        $userId = (int) $user['id'];
        $momoid = trim((string) ($filters['momoid'] ?? ''));
        $search = trim((string) ($filters['search'] ?? ''));

        $where = array('user_id = :user_id');
        $params = array(':user_id' => $userId);

        if ($momoid !== '') {
            $where[] = 'momoid = :momoid';
            $params[':momoid'] = $momoid;
        }

        if ($search !== '') {
            $where[] = '(momoid LIKE :search_momoid OR send_momoid LIKE :search_send_momoid)';
            $params[':search_momoid'] = '%' . $search . '%';
            $params[':search_send_momoid'] = '%' . $search . '%';
        }

        $items = MomoUser::queryAll(
            'SELECT * FROM momo_users WHERE ' . implode(' AND ', $where) . ' ORDER BY updated_at DESC, id DESC',
            $params
        );

        $groups = MomoUser::queryAll(
            'SELECT momoid, COUNT(*) AS total_count, SUM(is_block = 1) AS blocked_count, SUM(is_friend = 1) AS friend_count, SUM(is_online = 1) AS online_count FROM momo_users WHERE user_id = :user_id GROUP BY momoid ORDER BY MAX(updated_at) DESC',
            array(':user_id' => $userId)
        );

        return array(
            'summary' => array(
                'total' => MomoUser::countBy(array('user_id' => $userId)),
                'blocked' => MomoUser::countBy(array('user_id' => $userId, 'is_block' => 1)),
                'friends' => MomoUser::countBy(array('user_id' => $userId, 'is_friend' => 1)),
                'online' => MomoUser::countBy(array('user_id' => $userId, 'is_online' => 1)),
            ),
            'groups' => $groups,
            'items' => $items,
        );
    }

    /** 新增或更新会话。 */
    public function save(array $payload, array $user)
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $momoid = trim((string) ($payload['momoid'] ?? ''));
        $sendMomoid = trim((string) ($payload['send_momoid'] ?? ''));
        $data = array(
            'momoid' => $momoid,
            'send_momoid' => $sendMomoid,
            'is_send' => (int) ($payload['is_send'] ?? 0),
            'send_num' => (int) ($payload['send_num'] ?? 0),
            'is_block' => (int) ($payload['is_block'] ?? 0),
            'is_friend' => (int) ($payload['is_friend'] ?? 0),
        );

        if ($momoid === '' || $sendMomoid === '') {
            throw new RuntimeException('陌陌ID和发送陌陌ID不能为空', 400);
        }

        $duplicate = MomoUser::findOneBy(array(
            'user_id' => (int) $user['id'],
            'momoid' => $momoid,
            'send_momoid' => $sendMomoid,
            'id !=' => $id,
        ));
        if ($duplicate) {
            throw new RuntimeException('该陌陌ID和发送陌陌ID组合已存在', 409);
        }

        if ($id > 0) {
            $record = MomoUser::findOneBy(array('id' => $id, 'user_id' => (int) $user['id']));
            if (!$record) {
                throw new RuntimeException('会话不存在或无权限', 404);
            }
            MomoUser::updateBy(array('id' => $id, 'user_id' => (int) $user['id']), $data);
            return MomoUser::find($id);
        }

        $data['user_id'] = (int) $user['id'];
        $data['chat_id'] = MomoSingle::single($sendMomoid);
        $data['is_online'] = 0;
        $data['last_message'] = '';
        $data['last_interaction'] = round(microtime(true) * 1000);
        $newId = MomoUser::create($data);
        return MomoUser::find($newId);
    }

    /** 删除单个会话。 */
    public function delete($id, array $user)
    {
        $record = MomoUser::findOneBy(array('id' => (int) $id, 'user_id' => (int) $user['id']));
        if (!$record) {
            throw new RuntimeException('会话不存在或无权限', 404);
        }

        MomoUser::db()->query('DELETE FROM momo_users WHERE id = :id AND user_id = :user_id LIMIT 1', array(
            ':id' => (int) $id,
            ':user_id' => (int) $user['id'],
        ));

        return true;
    }

    /** 删除指定主账号下的全部会话。 */
    public function deleteByMomoid($momoid, array $user)
    {
        if (trim((string) $momoid) === '') {
            throw new RuntimeException('momoid不能为空', 400);
        }

        MomoUser::db()->query('DELETE FROM momo_users WHERE user_id = :user_id AND momoid = :momoid', array(
            ':user_id' => (int) $user['id'],
            ':momoid' => $momoid,
        ));

        return true;
    }
}
