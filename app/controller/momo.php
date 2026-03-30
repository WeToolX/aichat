<?php

/** 陌陌主业务控制器。 */
class MomoController extends BaseController
{
    /** 统一处理 momo 主入口的 action 分发。 */
    public function handle()
    {
        $this->requirePost();

        $service = new MomoService();
        // 进入业务前先确保表结构满足当前代码约定。
        $service->ensureSchema();

        try {
            $this->requireFields(array(
                'action' => '无效的操作类型',
            ));

            $action = $this->input('action');
            $user = $this->user();

            switch ($action) {
                case 'search':
                    $this->requireFields(array(
                        'momoid' => '陌陌ID不能为空',
                        'send_momoid' => '发送陌陌ID不能为空',
                    ));
                    $result = $service->search($this->input('momoid'), $this->input('send_momoid'), $user);
                    response(true, $result['data'], $result['message'], $result['code']);
                    break;

                case 'update':
                    $this->requireFields(array(
                        'momoid' => '陌陌ID不能为空',
                        'send_momoid' => '发送陌陌ID不能为空',
                    ));
                    $result = $service->update($this->request()->all(), $user);
                    response(true, $result['data'], $result['message'], $result['code']);
                    break;

                case 'import_messages':
                    $this->requireFields(array(
                        'momoid' => '陌陌ID不能为空',
                        'send_momoid' => '发送陌陌ID不能为空',
                    ));
                    $this->requireArrayField('messages', '请提供有效的消息数组');
                    $result = $service->importMessages($this->input('momoid'), $this->input('send_momoid'), $this->input('messages'), $user);
                    response(true, $result['data'], $result['message'], $result['code']);
                    break;

                case 'block':
                    $this->requireFields(array(
                        'momoid' => '陌陌ID不能为空',
                        'send_momoid' => '发送陌陌ID不能为空',
                    ));
                    $result = $service->block($this->input('momoid'), $this->input('send_momoid'), ((int) $this->input('is_block', 1) === 1), $user);
                    response(true, $result['data'], $result['message'], $result['code']);
                    break;

                default:
                    response(false, array(), '无效的操作类型', 400);
            }
        } catch (RuntimeException $e) {
            $code = $e->getCode();
            response(false, array(), $e->getMessage(), $code >= 400 ? $code : 500);
        } catch (Exception $e) {
            response(false, array(), '处理失败: ' . $e->getMessage(), 500);
        }
    }
}

return new MomoController();
