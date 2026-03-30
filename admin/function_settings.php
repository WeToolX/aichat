<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('settings');

$extraHead = <<<'HTML'
<style>
    .message-box {
        display: none;
        margin-bottom: var(--spacing-md);
        padding: var(--spacing-md);
        border-radius: var(--border-radius);
        font-size: var(--font-size-sm);
    }

    .message-box.show { display: block; }
    .message-box.success { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .message-box.error { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }

    .settings-form {
        display: grid;
        gap: 14px;
    }

    .settings-card {
        overflow: hidden;
    }

    .settings-card h2 {
        padding: 14px 16px;
        margin: 0;
        font-size: 15px;
    }

    .settings-card-body {
        padding: 14px 16px;
        display: grid;
        gap: 12px;
    }

    .switch-flow {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: flex-start;
    }

    .switch-row {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        width: auto;
        max-width: 100%;
        min-height: 32px;
        padding: 8px 10px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.2);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.12);
    }

    .switch-row label {
        margin: 0;
        white-space: nowrap;
    }

    .field-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 12px;
    }

    .field label {
        display: block;
        margin-bottom: 4px;
        font-weight: var(--font-weight-medium);
    }

    .field input {
        font: inherit;
    }
    .conditional-fields.is-hidden { display: none; }

    .hint {
        color: var(--gray-dark);
        font-size: var(--font-size-sm);
        padding: 10px 12px;
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.12);
        border: 1px solid rgba(255, 255, 255, 0.18);
    }

    .actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
        position: sticky;
        bottom: 12px;
        z-index: 2;
        padding: 10px 12px;
        border-radius: 14px;
        background: rgba(255, 255, 255, 0.24);
        border: 1px solid rgba(255, 255, 255, 0.28);
        backdrop-filter: blur(18px) saturate(145%);
        -webkit-backdrop-filter: blur(18px) saturate(145%);
        box-shadow: 0 24px 44px -36px rgba(15, 23, 42, 0.45);
    }

    .section-stack {
        display: grid;
        gap: 10px;
    }

    .settings-card-header {
        display: grid;
        gap: 4px;
    }

    .settings-card-subtitle {
        font-size: 13px;
        color: var(--gray-dark);
    }

    @media (max-width: 640px) {
        .actions {
            position: static;
        }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 功能设置', '功能设置', '表单只负责展示与提交，实际读写通过 `/admin/settings` 接口完成。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<form id="settings-form" class="settings-form">
    <section class="settings-card">
        <h2>基础开关</h2>
        <div class="settings-card-body">
            <div class="switch-flow">
                <div class="switch-row"><input id="auto_login" type="checkbox"><label for="auto_login">自动上号功能</label></div>
                <div class="switch-row"><input id="add_friend" type="checkbox"><label for="add_friend">是否添加好友</label></div>
                <div class="switch-row"><input id="only_send_to_friends" type="checkbox"><label for="only_send_to_friends">只给好友发送</label></div>
            </div>
        </div>
    </section>

    <section class="settings-card">
        <h2>附近人点赞</h2>
        <div class="settings-card-body">
            <div class="switch-row"><input id="nearby_like" type="checkbox"><label for="nearby_like">启用附近人点赞</label></div>
            <div class="field-grid conditional-fields" id="nearby-like-fields">
                <div class="field"><label for="nearby_like_count">点赞次数</label><input id="nearby_like_count" type="number" min="1"></div>
                <div class="field"><label for="nearby_like_interval_min">最小间隔(秒)</label><input id="nearby_like_interval_min" type="number" min="1"></div>
                <div class="field"><label for="nearby_like_interval_max">最大间隔(秒)</label><input id="nearby_like_interval_max" type="number" min="1"></div>
                <div class="field"><label for="nearby_like_scroll_min">最小下滑次数</label><input id="nearby_like_scroll_min" type="number" min="1"></div>
                <div class="field"><label for="nearby_like_scroll_max">最大下滑次数</label><input id="nearby_like_scroll_max" type="number" min="1"></div>
            </div>
        </div>
    </section>

    <section class="settings-card">
        <h2>附近动态点赞</h2>
        <div class="settings-card-body">
            <div class="switch-row"><input id="feed_like" type="checkbox"><label for="feed_like">启用附近动态点赞</label></div>
            <div class="field-grid conditional-fields" id="feed-like-fields">
                <div class="field"><label for="feed_like_count">点赞次数</label><input id="feed_like_count" type="number" min="1"></div>
                <div class="field"><label for="feed_like_interval_min">最小间隔(秒)</label><input id="feed_like_interval_min" type="number" min="1"></div>
                <div class="field"><label for="feed_like_interval_max">最大间隔(秒)</label><input id="feed_like_interval_max" type="number" min="1"></div>
                <div class="field"><label for="feed_like_scroll_min">最小下滑次数</label><input id="feed_like_scroll_min" type="number" min="1"></div>
                <div class="field"><label for="feed_like_scroll_max">最大下滑次数</label><input id="feed_like_scroll_max" type="number" min="1"></div>
            </div>
        </div>
    </section>

    <section class="settings-card">
        <h2>节奏参数</h2>
        <div class="settings-card-body">
            <div class="field-grid">
                <div class="field"><label for="click_delay_min">点击最小延迟(ms)</label><input id="click_delay_min" type="number" min="0"></div>
                <div class="field"><label for="click_delay_max">点击最大延迟(ms)</label><input id="click_delay_max" type="number" min="0"></div>
                <div class="field"><label for="send_delay_min">发送最小延迟(ms)</label><input id="send_delay_min" type="number" min="0"></div>
                <div class="field"><label for="send_delay_max">发送最大延迟(ms)</label><input id="send_delay_max" type="number" min="0"></div>
                <div class="field"><label for="reply_delay_min">回复最小间隔(ms)</label><input id="reply_delay_min" type="number" min="0"></div>
                <div class="field"><label for="reply_delay_max">回复最大间隔(ms)</label><input id="reply_delay_max" type="number" min="0"></div>
                <div class="field"><label for="guide_after_messages">多少条消息后引导</label><input id="guide_after_messages" type="number" min="1"></div>
            </div>
            <div class="hint">保存前会在前端做基础范围校验，最终以后台接口校验为准。</div>
        </div>
    </section>

    <div class="actions">
        <button type="submit" class="btn btn-primary">保存设置</button>
        <button type="button" class="btn btn-secondary" id="reload-btn">重新加载</button>
    </div>
</form>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const fields = [
            'auto_login','add_friend','only_send_to_friends',
            'nearby_like','nearby_like_count','nearby_like_interval_min','nearby_like_interval_max','nearby_like_scroll_min','nearby_like_scroll_max',
            'feed_like','feed_like_count','feed_like_interval_min','feed_like_interval_max','feed_like_scroll_min','feed_like_scroll_max',
            'click_delay_min','click_delay_max','send_delay_min','send_delay_max','reply_delay_min','reply_delay_max','guide_after_messages'
        ];

        const nodes = {
            form: document.getElementById('settings-form'),
            message: document.getElementById('message-box'),
            reload: document.getElementById('reload-btn'),
            nearbyLike: document.getElementById('nearby_like'),
            nearbyFields: document.getElementById('nearby-like-fields'),
            feedLike: document.getElementById('feed_like'),
            feedFields: document.getElementById('feed-like-fields')
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

        function setForm(data) {
            fields.forEach((field) => {
                const node = document.getElementById(field);
                if (!node) return;
                if (node.type === 'checkbox') {
                    node.checked = Number(data[field] || 0) === 1;
                } else {
                    node.value = data[field] ?? '';
                }
            });
            updateVisibility();
        }

        function toggleSection(node, visible) {
            if (!node) return;
            node.classList.toggle('is-hidden', !visible);
        }

        function updateVisibility() {
            toggleSection(nodes.nearbyFields, nodes.nearbyLike && nodes.nearbyLike.checked);
            toggleSection(nodes.feedFields, nodes.feedLike && nodes.feedLike.checked);
        }

        function getPayload() {
            const payload = {};
            fields.forEach((field) => {
                const node = document.getElementById(field);
                if (!node) return;
                payload[field] = node.type === 'checkbox' ? (node.checked ? 1 : 0) : Number(node.value || 0);
            });
            return payload;
        }

        function validate(payload) {
            const pairs = [
                ['nearby_like_interval_min', 'nearby_like_interval_max'],
                ['nearby_like_scroll_min', 'nearby_like_scroll_max'],
                ['feed_like_interval_min', 'feed_like_interval_max'],
                ['feed_like_scroll_min', 'feed_like_scroll_max'],
                ['click_delay_min', 'click_delay_max'],
                ['send_delay_min', 'send_delay_max'],
                ['reply_delay_min', 'reply_delay_max']
            ];

            for (const [minKey, maxKey] of pairs) {
                if (payload[minKey] > payload[maxKey]) {
                    throw new Error(`${minKey} 不能大于 ${maxKey}`);
                }
            }

            if (payload.guide_after_messages < 1) {
                throw new Error('引导消息条数必须大于 0');
            }
        }

        async function loadSettings() {
            clearMessage();
            const data = await request('../admin/settings');
            setForm(data || {});
        }

        async function saveSettings(event) {
            event.preventDefault();
            clearMessage();

            try {
                const payload = getPayload();
                validate(payload);
                const data = await request('../admin/settings/save', {
                    method: 'POST',
                    body: payload
                });
                setForm(data || {});
                showMessage('success', '设置保存成功');
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        nodes.form.addEventListener('submit', saveSettings);
        nodes.reload.addEventListener('click', () => loadSettings().catch((error) => showMessage('error', error.message)));
        nodes.nearbyLike.addEventListener('change', updateVisibility);
        nodes.feedLike.addEventListener('change', updateVisibility);

        loadSettings().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
