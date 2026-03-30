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
    .stack { display: grid; gap: var(--spacing-md); }
    .field label { display: block; margin-bottom: var(--spacing-xs); font-weight: var(--font-weight-medium); }
    .field input { width: 100%; padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font: inherit; }
    .switch-row { display: flex; gap: var(--spacing-sm); align-items: center; flex-wrap: wrap; }
    .toolbar { display: flex; justify-content: space-between; gap: var(--spacing-md); align-items: center; margin-bottom: var(--spacing-md); flex-wrap: wrap; }
    .toolbar input { width: min(320px, 100%); padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); }
    .groups, .momo-list { display: grid; gap: var(--spacing-sm); }
    .group-item, .momo-item { border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--spacing-md); display: grid; gap: var(--spacing-sm); }
    .group-top, .momo-top { display: flex; justify-content: space-between; gap: var(--spacing-md); align-items: start; }
    .momo-name { font-weight: var(--font-weight-semibold); }
    .momo-meta { color: var(--gray-dark); font-size: var(--font-size-sm); }
    .pagination-bar { display: flex; justify-content: space-between; align-items: center; gap: var(--spacing-sm); flex-wrap: wrap; }
    .pagination-actions { display: flex; gap: var(--spacing-sm); align-items: center; }
    .tags { display: flex; gap: 6px; flex-wrap: wrap; }
    .tag { display: inline-flex; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: var(--font-weight-medium); }
    .tag.online { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .tag.blocked { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }
    .tag.friend { background: rgba(67, 97, 238, 0.12); color: #1d4ed8; }
    .actions { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }
    .empty-state { padding: var(--spacing-xl); text-align: center; color: var(--gray-dark); border: 1px dashed var(--border-color); border-radius: var(--border-radius); }

    @media (max-width: 960px) {
        .summary-grid { grid-template-columns: 1fr 1fr; }
        .page-grid { grid-template-columns: 1fr; }
    }

    @media (max-width: 640px) {
        .summary-grid { grid-template-columns: 1fr; }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 陌陌用户管理', '📱 陌陌用户管理', '表单和列表已前后端分离，数据通过 `/admin/momo` 接口驱动。', $extraHead);
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
                    <p>支持按 `momoid` 分组查看和批量删除。</p>
                </div>
                <input id="search-input" type="search" placeholder="搜索 momoid / send_momoid">
            </div>
        </div>
        <div class="panel-body stack">
            <div>
                <h3>主账号分组</h3>
                <div id="group-list" class="groups"></div>
            </div>
            <div>
                <h3>会话明细</h3>
                <div class="pagination-bar">
                    <div id="pagination-summary" class="momo-meta">第 1 页</div>
                    <div class="pagination-actions">
                        <button type="button" class="btn btn-secondary" id="prev-page-btn">上一页</button>
                        <button type="button" class="btn btn-secondary" id="next-page-btn">下一页</button>
                    </div>
                </div>
                <div id="momo-list" class="momo-list"></div>
            </div>
        </div>
    </section>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const state = { data: { groups: [], items: [], summary: {}, pagination: {} }, search: '', page: 1, perPage: 20 };
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
            list: document.getElementById('momo-list'),
            total: document.getElementById('sum-total'),
            friends: document.getElementById('sum-friends'),
            blocked: document.getElementById('sum-blocked'),
            online: document.getElementById('sum-online'),
            reset: document.getElementById('reset-btn'),
            paginationSummary: document.getElementById('pagination-summary'),
            prevPage: document.getElementById('prev-page-btn'),
            nextPage: document.getElementById('next-page-btn')
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

        function fillForm(item) {
            nodes.id.value = item.id;
            nodes.momoid.value = item.momoid || '';
            nodes.sendMomoid.value = item.send_momoid || '';
            nodes.sendNum.value = item.send_num || 0;
            nodes.isSend.checked = Number(item.is_send || 0) === 1;
            nodes.isBlock.checked = Number(item.is_block || 0) === 1;
            nodes.isFriend.checked = Number(item.is_friend || 0) === 1;
            nodes.title.textContent = `编辑会话 #${item.id}`;
            window.scrollTo({ top: 0, behavior: 'smooth' });
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
                            <button type="button" class="btn btn-danger" data-delete-momoid="${escapeHtml(group.momoid)}">删除该主账号</button>
                        </div>
                    </div>
                </article>
            `).join('') : '<div class="empty-state">暂无主账号分组</div>';

            const items = Array.isArray(state.data.items) ? state.data.items : [];
            nodes.list.innerHTML = items.length ? items.map((item) => `
                <article class="momo-item">
                    <div class="momo-top">
                        <div>
                            <div class="momo-name">${escapeHtml(item.momoid)} / ${escapeHtml(item.send_momoid)}</div>
                            <div class="momo-meta">ID #${item.id} · 发送次数 ${item.send_num || 0}</div>
                        </div>
                        <div class="tags">
                            ${Number(item.is_online || 0) === 1 ? '<span class="tag online">在线</span>' : ''}
                            ${Number(item.is_friend || 0) === 1 ? '<span class="tag friend">好友</span>' : ''}
                            ${Number(item.is_block || 0) === 1 ? '<span class="tag blocked">拉黑</span>' : ''}
                        </div>
                    </div>
                    <div class="actions">
                        <button type="button" class="btn btn-primary" data-edit="${item.id}">编辑</button>
                        <button type="button" class="btn btn-secondary" onclick="location.href='chat_history.php?momo_user_id=${item.id}&momoid=${encodeURIComponent(item.momoid)}&send_momoid=${encodeURIComponent(item.send_momoid)}'">聊天记录</button>
                        <button type="button" class="btn btn-danger" data-delete="${item.id}">删除</button>
                    </div>
                </article>
            `).join('') : '<div class="empty-state">暂无会话数据</div>';

            const pagination = state.data.pagination || {};
            const page = Number(pagination.page || 1);
            const totalPages = Number(pagination.total_pages || 1);
            const total = Number(pagination.total || 0);
            nodes.paginationSummary.textContent = `第 ${page} / ${totalPages} 页，共 ${total} 条`;
            nodes.prevPage.disabled = page <= 1;
            nodes.nextPage.disabled = page >= totalPages;
        }

        async function loadData() {
            const params = new URLSearchParams();
            if (state.search) params.set('search', state.search);
            params.set('page', String(state.page));
            params.set('per_page', String(state.perPage));
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

        async function removeById(id) {
            try {
                await request('../admin/momo/delete', { method: 'POST', body: { id } });
                showMessage('success', '会话删除成功');
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
            state.page = 1;
            loadData().catch((error) => showMessage('error', error.message));
        });
        nodes.prevPage.addEventListener('click', () => {
            if (state.page <= 1) return;
            state.page -= 1;
            loadData().catch((error) => showMessage('error', error.message));
        });
        nodes.nextPage.addEventListener('click', () => {
            const totalPages = Number((state.data.pagination || {}).total_pages || 1);
            if (state.page >= totalPages) return;
            state.page += 1;
            loadData().catch((error) => showMessage('error', error.message));
        });
        document.addEventListener('click', (event) => {
            const editBtn = event.target.closest('[data-edit]');
            if (editBtn) {
                const id = Number(editBtn.dataset.edit);
                const item = (state.data.items || []).find((entry) => Number(entry.id) === id);
                if (item) {
                    clearMessage();
                    fillForm(item);
                }
                return;
            }

            const deleteBtn = event.target.closest('[data-delete]');
            if (deleteBtn) {
                if (confirm('确定删除该会话吗？')) {
                    removeById(Number(deleteBtn.dataset.delete));
                }
                return;
            }

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
