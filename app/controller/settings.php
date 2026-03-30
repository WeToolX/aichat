<?php

/** 设置读取控制器。 */
class SettingsController extends BaseController
{
    /** 设置读取入口，按 action 返回不同配置项。 */
    public function handle()
    {
        $this->requirePost();

        try {
            $this->requireFields(array(
                'action' => '无效的操作类型',
            ));

            $action = $this->input('action');
            $user = $this->user();
            $userId = $user['id'] ?? 0;
            $service = new SettingService();

            switch ($action) {
                case 'get':
                    response(true, $service->getSettings($userId), '获取成功', 200);
                    break;
                case 'auto_login':
                    response(true, array('auto_login' => $service->getBool($userId, 'auto_login')), '获取成功', 200);
                    break;
                case 'nearby_like':
                    response(true, array('nearby_like' => $service->getBool($userId, 'nearby_like')), '获取成功', 200);
                    break;
                case 'nearby_like_count':
                    response(true, array('nearby_like_count' => $service->getInt($userId, 'nearby_like_count', 10)), '获取成功', 200);
                    break;
                case 'nearby_like_interval':
                    response(true, array('interval' => $service->randomInRange($userId, 'nearby_like_interval_min', 'nearby_like_interval_max', 1, 5)), '获取成功', 200);
                    break;
                case 'nearby_like_scroll':
                    response(true, array('scroll' => $service->randomInRange($userId, 'nearby_like_scroll_min', 'nearby_like_scroll_max', 1, 10)), '获取成功', 200);
                    break;
                case 'feed_like':
                    response(true, array('feed_like' => $service->getBool($userId, 'feed_like')), '获取成功', 200);
                    break;
                case 'feed_like_count':
                    response(true, array('feed_like_count' => $service->getInt($userId, 'feed_like_count', 10)), '获取成功', 200);
                    break;
                case 'feed_like_interval':
                    response(true, array('interval' => $service->randomInRange($userId, 'feed_like_interval_min', 'feed_like_interval_max', 1, 5)), '获取成功', 200);
                    break;
                case 'feed_like_scroll':
                    response(true, array('scroll' => $service->randomInRange($userId, 'feed_like_scroll_min', 'feed_like_scroll_max', 1, 10)), '获取成功', 200);
                    break;
                case 'click_delay':
                    response(true, array('delay' => $service->randomInRange($userId, 'click_delay_min', 'click_delay_max', 500, 1000)), '获取成功', 200);
                    break;
                case 'send_delay':
                    response(true, array('delay' => $service->randomInRange($userId, 'send_delay_min', 'send_delay_max', 1000, 2000)), '获取成功', 200);
                    break;
                case 'reply_delay':
                    response(true, array('delay' => $service->randomInRange($userId, 'reply_delay_min', 'reply_delay_max', 1000, 3000)), '获取成功', 200);
                    break;
                case 'guide_after_messages':
                    response(true, array('guide_after_messages' => $service->getInt($userId, 'guide_after_messages', 5)), '获取成功', 200);
                    break;
                default:
                    response(false, array(), '无效的操作类型', 400);
            }
        } catch (Exception $e) {
            response(false, array(), '获取设置失败: ' . $e->getMessage(), 500);
        }
    }
}

return new SettingsController();
