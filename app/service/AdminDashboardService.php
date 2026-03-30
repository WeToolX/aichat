<?php

/** 后台仪表盘服务，负责聚合管理页面所需的统计摘要。 */
class AdminDashboardService
{
    /** 返回当前用户可见的仪表盘统计数据。 */
    public function summary(array $user)
    {
        $isSuper = (int) ($user['role'] ?? 0) === 1;
        $userId = (int) ($user['id'] ?? 0);

        return array(
            'users' => $isSuper ? User::countBy() : 1,
            'scripts' => Script::countBy($isSuper ? array() : array('user_id' => $userId)),
            'keywords' => Keyword::countBy($isSuper ? array() : array('user_id' => $userId)),
            'files' => FileModel::countBy($isSuper ? array() : array('user_id' => $userId)),
            'momo_users' => MomoUser::countBy($isSuper ? array() : array('user_id' => $userId)),
            'undownloaded_files' => FileModel::countBy($isSuper ? array('is_downloaded' => 0) : array('user_id' => $userId, 'is_downloaded' => 0)),
        );
    }
}
