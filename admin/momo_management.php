<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('momo');

$extraHead = <<<'HTML'
<style>
    .message-box { display: none; margin-bottom: var(--spacing-md); padding: var(--spacing-md); border-radius: var(--border-radius); font-size: var(--font-size-sm); }
    .message-box.show { display: block; }
    .message-box.success { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .message-box.error { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }

    .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: var(--spacing-md); margin-bottom: var(--spacing-lg); }
    .summary-card { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--spacing-md); }
    .summary-card strong { display: block; font-size: 28px; line-height: 1; margin-bottom: var(--spacing-xs); }
    .page-grid { display: grid; grid-template-columns: 360px minmax(0, 1fr); gap: var(--spacing-lg); }
    .panel { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); }
    .panel-header, .panel-body { padding: var(--spacing-lg); }
    .panel-header { border-bottom: 1px solid var(--border-color); }
    .panel-header p { margin-bottom: 5px; }
    .stack { display: grid; gap: var(--spacing-md); }
    .field label { display: block; margin-bottom: var(--spacing-xs); font-weight: var(--font-weight-medium); }
    .field input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font: inherit; }
    .switch-row { display: flex; gap: var(--spacing-sm); align-items: center; flex-wrap: wrap; }
    .toolbar { display: flex; justify-content: space-between; gap: var(--spacing-md); align-items: center; margin-bottom: var(--spacing-md); flex-wrap: wrap; }
    .toolbar input { width: min(320px, 100%); padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
    .groups { display: grid; gap: var(--spacing-sm); }
    .group-item { border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--spacing-md); display: grid; gap: var(--spacing-sm); }
    .group-top { display: flex; justify-content: space-between; gap: var(--spacing-md); align-items: start; }
    .momo-name { font-weight: var(--font-weight-semibold); }
    .momo-meta { color: var(--gray-dark); font-size: var(--font-size-sm); }
    .actions { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }
    .empty-state { padding: var(--spacing-xl); text-align: center; color: var(--gray-dark); border: 1px dashed var(--border-color); border-radius: var(--border-radius); }

    @media (max-width: 960px) {
        .summary-grid { grid-template-columns: 1fr 1fr; }
        .page-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .summary-grid { grid-template-columns: 1fr; }
        .actions { width: 100%; }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 陌陌用户管理', '📱 陌陌用户管理', '主页展示主账号分组，会话明细拆分为独立页面。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<section class="summary-grid">
    <div class="summary-card"><strong id="sum-total">-</strong><span>总会话数</span></div>
    <div class="summary-card"><strong id="sum-friends">-</strong><span>好友数</span></div>
    <div class="summary-card"><strong id="sum-blocked">-</strong><span>拉黑数</span></div>
    <div class="summary-card"><strong id="sum-online">-</strong><span>在线数</span></div>
</section>

<div class="page-grid">
    <section class="panel">
        <div class="panel-header">
            <h2 id="form-title">新增会话</h2>
        </div>
        <div class="panel-body">
            <form id="momo-form" class="stack">
                <input type="hidden" id="momo-id" value="">
                <div class="field"><label for="momoid">陌陌ID</label><input id="momoid" type="text" placeholder="外层陌陌ID"></div>
                <div class="field"><label for="send_momoid">发送陌陌ID</label><input id="send_momoid" type="text" placeholder="会话陌陌ID"></div>
                <div class="field"><label for="send_num">发送次数</label><input id="send_num" type="number" min="0" value="0"></div>
                <div class="switch-row"><input id="is_send" type="checkbox"><label for="is_send">已发送</label></div>
                <div class="switch-row"><input id="is_block" type="checkbox"><label for="is_block">已拉黑</label></div>
                <div class="switch-row"><input id="is_friend" type="checkbox"><label for="is_friend">已是好友</label></div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">保存</button>
                    <button type="button" class="btn btn-secondary" id="reset-btn">重置</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="toolbar">
                <div>
                    <h2>会话列表</h2>
                    <p>支持搜索主账号或发送账号，结果按主账号分组展示。</p>
                </div>
                <input id="search-input" type="search" placeholder="搜索主账号 / 发送账号">
            </div>
        </div>
        <div class="panel-body">
            <div id="group-list" class="groups"></div>
        </div>
    </section>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const state = { data: { groups: [], summary: {} }, search: '' };
        const nodes = {
            message: document.getElementById('message-box'),
            form: document.getElementById('momo-form'),
            id: document.getElementById('momo-id'),
            momoid: document.getElementById('momoid'),
            sendMomoid: document.getElementById('send_momoid'),
            sendNum: document.getElementById('send_num'),
            isSend: document.getElementById('is_send'),
            isBlock: document.getElementById('is_block'),
            isFriend: document.getElementById('is_friend'),
            title: document.getElementById('form-title'),
            search: document.getElementById('search-input'),
            groups: document.getElementById('group-list'),
            total: document.getElementById('sum-total'),
            friends: document.getElementById('sum-friends'),
            blocked: document.getElementById('sum-blocked'),
            online: document.getElementById('sum-online'),
            reset: document.getElementById('reset-btn')
        };

        async function request(path, options = {}) {
            const config = { method: options.method || 'GET', headers: { 'X-Token': token, 'Accept': 'application/json' }, credentials: 'same-origin' };
            if (options.body) {
                config.headers['Content-Type'] = 'application/json';
                config.body = JSON.stringify(options.body);
            }
            const response = await fetch(path, config);
            const payload = await response.json();
            if (!payload.success) throw new Error(payload.message || '请求失败');
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

        function escapeHtml(value) {
            return String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function resetForm() {
            nodes.id.value = '';
            nodes.momoid.value = '';
            nodes.sendMomoid.value = '';
            nodes.sendNum.value = '0';
            nodes.isSend.checked = false;
            nodes.isBlock.checked = false;
            nodes.isFriend.checked = false;
            nodes.title.textContent = '新增会话';
        }

        function render() {
            const summary = state.data.summary || {};
            nodes.total.textContent = summary.total ?? 0;
            nodes.friends.textContent = summary.friends ?? 0;
            nodes.blocked.textContent = summary.blocked ?? 0;
            nodes.online.textContent = summary.online ?? 0;

            const groups = Array.isArray(state.data.groups) ? state.data.groups : [];
            nodes.groups.innerHTML = groups.length ? groups.map((group) => `
                <article class="group-item">
                    <div class="group-top">
                        <div>
                            <div class="momo-name">${escapeHtml(group.momoid)}</div>
                            <div class="momo-meta">会话 ${group.total_count || 0} · 好友 ${group.friend_count || 0} · 拉黑 ${group.blocked_count || 0} · 在线 ${group.online_count || 0}</div>
                        </div>
                        <div class="actions">
                            <button type="button" class="btn btn-secondary" onclick="location.href='momo_detail.php?momoid=${encodeURIComponent(group.momoid)}'">会话明细</button>
                            <button type="button" class="btn btn-danger" data-delete-momoid="${escapeHtml(group.momoid)}">删除该主账号</button>
                        </div>
                    </div>
                </article>
            `).join('') : '<div class="empty-state">暂无主账号分组</div>';
        }

        async function loadData() {
            const params = new URLSearchParams();
            params.set('with_items', '0');
            if (state.search) params.set('group_search', state.search);
            state.data = await request(`../admin/momo?${params.toString()}`);
            render();
        }

        async function saveItem(event) {
            event.preventDefault();
            clearMessage();
            try {
                await request('../admin/momo/save', {
                    method: 'POST',
                    body: {
                        id: nodes.id.value ? Number(nodes.id.value) : undefined,
                        momoid: nodes.momoid.value.trim(),
                        send_momoid: nodes.sendMomoid.value.trim(),
                        send_num: Number(nodes.sendNum.value || 0),
                        is_send: nodes.isSend.checked ? 1 : 0,
                        is_block: nodes.isBlock.checked ? 1 : 0,
                        is_friend: nodes.isFriend.checked ? 1 : 0
                    }
                });
                resetForm();
                showMessage('success', '会话保存成功');
                await loadData();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        async function removeByMomoid(momoid) {
            try {
                await request('../admin/momo/deleteMomoid', { method: 'POST', body: { momoid } });
                showMessage('success', '主账号下会话已删除');
                await loadData();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        nodes.form.addEventListener('submit', saveItem);
        nodes.reset.addEventListener('click', () => { clearMessage(); resetForm(); });
        nodes.search.addEventListener('input', (event) => {
            state.search = event.target.value.trim();
            loadData().catch((error) => showMessage('error', error.message));
        });
        document.addEventListener('click', (event) => {
            const deleteMomoidBtn = event.target.closest('[data-delete-momoid]');
            if (deleteMomoidBtn) {
                if (confirm(`确定删除主账号 ${deleteMomoidBtn.dataset.deleteMomoid} 下的全部会话吗？`)) {
                    removeByMomoid(deleteMomoidBtn.dataset.deleteMomoid);
                }
            }
        });

        loadData().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
