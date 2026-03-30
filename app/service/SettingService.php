<?php

/** 设置业务服务，负责读取并格式化用户功能设置。 */
class SettingService
{
    protected $defaults = array(
        'auto_login' => 0,
        'add_friend' => 0,
        'only_send_to_friends' => 0,
        'nearby_like' => 0,
        'nearby_like_count' => 10,
        'nearby_like_interval' => 3,
        'nearby_like_interval_min' => 1,
        'nearby_like_interval_max' => 5,
        'nearby_like_scroll' => 5,
        'nearby_like_scroll_min' => 1,
        'nearby_like_scroll_max' => 10,
        'feed_like' => 0,
        'feed_like_count' => 10,
        'feed_like_interval' => 3,
        'feed_like_interval_min' => 1,
        'feed_like_interval_max' => 5,
        'feed_like_scroll' => 5,
        'feed_like_scroll_min' => 1,
        'feed_like_scroll_max' => 10,
        'click_delay_min' => 500,
        'click_delay_max' => 1000,
        'send_delay_min' => 1000,
        'send_delay_max' => 2000,
        'reply_delay_min' => 1000,
        'reply_delay_max' => 3000,
        'guide_after_messages' => 5,
    );

    /** 获取用户设置并与默认值合并。 */
    public function getSettings($userId)
    {
        $settings = FunctionSetting::findByUserId($userId);

        if (!$settings) {
            return $this->defaults;
        }

        unset($settings['id'], $settings['user_id'], $settings['created_at'], $settings['updated_at']);
        return array_merge($this->defaults, $settings);
    }

    /** 读取布尔型设置。 */
    public function getBool($userId, $field)
    {
        $settings = $this->getSettings($userId);
        return (bool) ($settings[$field] ?? false);
    }

    /** 读取整型设置。 */
    public function getInt($userId, $field, $default = 0)
    {
        $settings = $this->getSettings($userId);
        return (int) ($settings[$field] ?? $default);
    }

    /** 根据最小值和最大值随机生成配置值。 */
    public function randomInRange($userId, $minField, $maxField, $defaultMin, $defaultMax)
    {
        $settings = $this->getSettings($userId);
        $min = (int) ($settings[$minField] ?? $defaultMin);
        $max = (int) ($settings[$maxField] ?? $defaultMax);
        return rand($min, $max);
    }
}
