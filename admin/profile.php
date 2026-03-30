<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('profile');

$extraHead = <<<'HTML'
<style>
    .message-box { display: none; margin-bottom: var(--spacing-md); padding: var(--spacing-md); border-radius: var(--border-radius); font-size: var(--font-size-sm); }
    .message-box.show { display: block; }
    .message-box.success { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .message-box.error { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }

    .profile-grid { display: grid; grid-template-columns: minmax(0, 1fr) 420px; gap: var(--spacing-lg); }
    .panel { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); }
    .panel-header, .panel-body { padding: var(--spacing-lg); }
    .panel-header { border-bottom: 1px solid var(--border-color); }
    .stack { display: grid; gap: var(--spacing-md); }
    .info-row { display: flex; justify-content: space-between; gap: var(--spacing-md); padding: var(--spacing-sm) 0; border-bottom: 1px solid var(--border-color); }
    .info-row:last-child { border-bottom: none; }
    .field label { display: block; margin-bottom: var(--spacing-xs); font-weight: var(--font-weight-medium); }
    .field input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font: inherit; }
    .actions { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }

    @media (max-width: 960px) {
        .profile-grid { grid-template-columns: 1fr; }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 个人设置', '👤 个人设置', '资料与密码修改都通过后台接口处理，页面不再直接访问数据库。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="profile-grid">
    <section class="panel">
        <div class="panel-header">
            <h2>账户信息</h2>
        </div>
        <div class="panel-body stack">
            <div class="info-row"><span>用户名</span><strong id="profile-username">-</strong></div>
            <div class="info-row"><span>角色</span><strong id="profile-role">-</strong></div>
            <div class="info-row"><span>邮箱</span><strong id="profile-email">-</strong></div>
            <div class="info-row"><span>用户ID</span><strong id="profile-id">-</strong></div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>修改密码</h2>
        </div>
        <div class="panel-body">
            <form id="password-form" class="stack">
                <div class="field">
                    <label for="current_password">当前密码</label>
                    <input id="current_password" type="password" autocomplete="current-password">
                </div>
                <div class="field">
                    <label for="new_password">新密码</label>
                    <input id="new_password" type="password" autocomplete="new-password">
                </div>
                <div class="field">
                    <label for="confirm_password">确认新密码</label>
                    <input id="confirm_password" type="password" autocomplete="new-password">
                </div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">修改密码</button>
                    <button type="button" class="btn btn-secondary" id="reset-btn">清空</button>
                </div>
            </form>
        </div>
    </section>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const nodes = {
            message: document.getElementById('message-box'),
            form: document.getElementById('password-form'),
            reset: document.getElementById('reset-btn'),
            current: document.getElementById('current_password'),
            next: document.getElementById('new_password'),
            confirm: document.getElementById('confirm_password'),
            username: document.getElementById('profile-username'),
            role: document.getElementById('profile-role'),
            email: document.getElementById('profile-email'),
            id: document.getElementById('profile-id')
        };

        async function request(path, options = {}) {
            const config = {
                method: options.method || 'GET',
                headers: { 'X-Token': token, 'Accept': 'application/json' },
                credentials: 'same-origin'
            };

            if (options.body) {
                config.headers['Content-Type'] = 'application/json';
                config.body = JSON.stringify(options.body);
            }

            const response = await fetch(path, config);
            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || '请求失败');
            }
            return payload.data;
        }

        function showMessage(type, text) {
            nodes.message.className = `message-box show ${type}`;
            nodes.message.textContent = text;
        }

        function clearMessage() {
            nodes.message.className = 'message-box';
            nodes.message.textContent = '';
        }

        function resetForm() {
            nodes.current.value = '';
            nodes.next.value = '';
            nodes.confirm.value = '';
        }

        async function loadProfile() {
            const data = await request('../admin/profile');
            nodes.username.textContent = data.username || '-';
            nodes.role.textContent = Number(data.role) === 1 ? '超级用户' : '普通用户';
            nodes.email.textContent = data.email || '-';
            nodes.id.textContent = data.id || '-';
        }

        async function savePassword(event) {
            event.preventDefault();
            clearMessage();

            try {
                await request('../admin/profile/password', {
                    method: 'POST',
                    body: {
                        current_password: nodes.current.value,
                        new_password: nodes.next.value,
                        confirm_password: nodes.confirm.value
                    }
                });
                resetForm();
                showMessage('success', '密码修改成功');
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        nodes.form.addEventListener('submit', savePassword);
        nodes.reset.addEventListener('click', () => { clearMessage(); resetForm(); });

        loadProfile().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
