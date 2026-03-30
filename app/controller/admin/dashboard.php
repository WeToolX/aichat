<?php

/** 后台仪表盘控制器。 */
class AdminDashboardController extends BaseController
{
    /** 返回后台首页统计数据。 */
    public function handle()
    {
        response(true, (new AdminDashboardService())->summary($this->user()), '获取成功', 200);
    }
}

return new AdminDashboardController();
