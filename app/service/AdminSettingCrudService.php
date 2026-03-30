<?php

/** 后台设置管理服务。 */
class AdminSettingCrudService
{
    protected $fields = array(
        'auto_login',
        'add_friend',
        'only_send_to_friends',
        'nearby_like',
        'nearby_like_count',
        'nearby_like_interval',
        'nearby_like_interval_min',
        'nearby_like_interval_max',
        'nearby_like_scroll',
        'nearby_like_scroll_min',
        'nearby_like_scroll_max',
        'feed_like',
        'feed_like_count',
        'feed_like_interval',
        'feed_like_interval_min',
        'feed_like_interval_max',
        'feed_like_scroll',
        'feed_like_scroll_min',
        'feed_like_scroll_max',
        'click_delay_min',
        'click_delay_max',
        'send_delay_min',
        'send_delay_max',
        'reply_delay_min',
        'reply_delay_max',
        'guide_after_messages',
    );

    /** 返回完整设置。 */
    public function get(array $user)
    {
        return (new SettingService())->getSettings((int) $user['id']);
    }

    /** 保存设置。 */
    public function save(array $payload, array $user)
    {
        $userId = (int) $user['id'];
        $data = array();

        foreach ($this->fields as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $value = $payload[$field];
            $data[$field] = is_numeric($value) ? (int) $value : $value;
        }

        if (empty($data)) {
            throw new RuntimeException('没有可保存的设置项', 400);
        }

        $existing = FunctionSetting::findByUserId($userId);
        if ($existing) {
            FunctionSetting::updateBy(array('user_id' => $userId), $data);
        } else {
            $data['user_id'] = $userId;
            FunctionSetting::create($data);
        }

        return $this->get($user);
    }
}
