<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('momo');
$initialMomoid = trim((string) ($_GET['momoid'] ?? ''));

$extraHead = <<<'HTML'
<style>
    .message-box { display: none; margin-bottom: var(--spacing-md); padding: var(--spacing-md); border-radius: var(--border-radius); font-size: var(--font-size-sm); }
    .message-box.show { display: block; }
    .message-box.success { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .message-box.error { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }
    .page-grid { display: grid; grid-template-columns: minmax(320px, 360px) minmax(0, 1fr); gap: 14px; align-items: start; }
    .panel { overflow: hidden; }
    .panel-header, .panel-body { padding: 14px 16px; }
    .panel-header { display: grid; gap: 4px; }
    .panel-header p { margin-bottom: 5px; }
    .toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: end; margin-bottom: 0; flex-wrap: wrap; }
    .toolbar input { width: min(280px, 100%); }
    .toolbar select { width: auto; min-width: 120px; }
    .toolbar-controls { display: flex; gap: 8px; align-items: end; flex-wrap: wrap; }
    .field label { display: block; margin-bottom: 4px; font-weight: var(--font-weight-medium); }
    .field input { font: inherit; }
    .field input[readonly] { background: rgba(255, 255, 255, 0.2); color: var(--gray-dark); }
    .stack { display: grid; gap: 12px; }
    .switch-row { display: inline-flex; gap: 6px; align-items: center; flex: 0 0 auto; }
    .switch-row label { display: inline; margin: 0; font-weight: var(--font-weight-medium); }
    .switch-inline { display: flex; gap: var(--spacing-md); align-items: center; flex-wrap: wrap; }
    .momo-list { display: grid; gap: 10px; }
    .momo-item { border-radius: var(--border-radius); padding: 14px; display: grid; gap: 10px; background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.24); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16); }
    .momo-top { display: flex; justify-content: space-between; gap: 14px; align-items: start; flex-wrap: wrap; }
    .momo-name { font-weight: var(--font-weight-semibold); }
    .momo-meta { color: var(--gray-dark); font-size: var(--font-size-sm); }
    .momo-side { display: grid; gap: 8px; justify-items: end; min-width: 0; }
    .tags { display: flex; gap: 6px; flex-wrap: wrap; }
    .tag { display: inline-flex; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: var(--font-weight-medium); }
    .tag.online { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .tag.blocked { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }
    .tag.friend { background: rgba(67, 97, 238, 0.12); color: #1d4ed8; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .momo-item .btn { padding: 6px 10px; }
    .pagination-bar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 12px; border-radius: 12px; background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.22); }
    .pagination-actions { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
    .back-row { display: flex; gap: 8px; margin-bottom: 12px; }
    .empty-state { padding: var(--spacing-xl); text-align: center; color: var(--gray-dark); border: 1px dashed var(--border-color); border-radius: var(--border-radius); }
    .detail-heading { display: grid; gap: 4px; }
    .detail-heading h2 { margin: 0; }
    .detail-heading p { margin: 0; font-size: 13px; }

    @media (max-width: 960px) {
        .page-grid { grid-template-columns: 1fr; }
        .momo-top { flex-wrap: wrap; }
        .momo-side { width: 100%; justify-items: start; }
    }

    @media (max-width: 640px) {
        .actions { width: 100%; }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 会话明细', '会话明细', '当前页面仅展示指定主账号下的会话，搜索仅匹配发送陌陌ID。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="back-row">
    <a href="momo_management.php" class="btn btn-secondary">返回主账号列表</a>
</div>

<div class="page-grid">
    <section class="panel">
        <div class="panel-header">
            <h2 id="form-title">新增会话</h2>
        </div>
        <div class="panel-body">
            <form id="momo-form" class="stack">
                <input type="hidden" id="momo-id" value="">
                <div class="field"><label for="momoid">陌陌ID</label><input id="momoid" type="text" value="<?php echo admin_shell_escape($initialMomoid); ?>" readonly></div>
                <div class="field"><label for="send_momoid">发送陌陌ID</label><input id="send_momoid" type="text" placeholder="会话陌陌ID"></div>
                <div class="field"><label for="send_num">发送次数</label><input id="send_num" type="number" min="0" value="0"></div>
                <div class="switch-inline">
                    <div class="switch-row"><input id="is_send" type="checkbox"><label for="is_send">已发送</label></div>
                    <div class="switch-row"><input id="is_block" type="checkbox"><label for="is_block">已拉黑</label></div>
                    <div class="switch-row"><input id="is_friend" type="checkbox"><label for="is_friend">已是好友</label></div>
                </div>
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
                <div class="detail-heading">
                    <h2>会话明细</h2>
                    <p id="momoid-hint">主账号：<?php echo admin_shell_escape($initialMomoid); ?></p>
                </div>
                <div class="toolbar-controls">
                    <select id="type-filter">
                        <option value="all">全部</option>
                        <option value="1">招呼</option>
                        <option value="0">会话</option>
                    </select>
                    <select id="online-filter">
                        <option value="all">全部</option>
                        <option value="online">在线</option>
                        <option value="offline">离线</option>
                    </select>
                    <input id="search-input" type="search" placeholder="搜索 send_momoid">
                </div>
            </div>
        </div>
        <div class="panel-body stack">
            <div class="pagination-bar">
                <div id="pagination-summary" class="momo-meta">第 1 页</div>
                <div class="pagination-actions">
                    <button type="button" class="btn btn-secondary" id="prev-page-btn">上一页</button>
                    <button type="button" class="btn btn-secondary" id="next-page-btn">下一页</button>
                </div>
            </div>
            <div id="momo-list" class="momo-list"></div>
        </div>
    </section>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const initialMomoid = __INITIAL_MOMOID__;
        const state = { data: { items: [], pagination: {} }, search: '', page: 1, perPage: 10, momoid: initialMomoid, isSayHi: 'all', onlineStatus: 'all' };
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
            typeFilter: document.getElementById('type-filter'),
            onlineFilter: document.getElementById('online-filter'),
            list: document.getElementById('momo-list'),
            reset: document.getElementById('reset-btn'),
            paginationSummary: document.getElementById('pagination-summary'),
            prevPage: document.getElementById('prev-page-btn'),
            nextPage: document.getElementById('next-page-btn'),
            momoidHint: document.getElementById('momoid-hint')
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
            nodes.momoid.value = state.momoid;
            nodes.sendMomoid.value = '';
            nodes.sendNum.value = '0';
            nodes.isSend.checked = false;
            nodes.isBlock.checked = false;
            nodes.isFriend.checked = false;
            nodes.title.textContent = '新增会话';
        }

        function fillForm(item) {
            nodes.id.value = item.id;
            nodes.momoid.value = item.momoid || state.momoid || '';
            nodes.sendMomoid.value = item.send_momoid || '';
            nodes.sendNum.value = item.send_num || 0;
            nodes.isSend.checked = Number(item.is_send || 0) === 1;
            nodes.isBlock.checked = Number(item.is_block || 0) === 1;
            nodes.isFriend.checked = Number(item.is_friend || 0) === 1;
            nodes.title.textContent = `编辑会话 #${item.id}`;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function render() {
            nodes.momoidHint.textContent = `主账号：${state.momoid || '-'}`;
            const items = Array.isArray(state.data.items) ? state.data.items : [];
            nodes.list.innerHTML = items.length ? items.map((item) => `
                <article class="momo-item">
                    <div class="momo-top">
                        <div>
                            <div class="momo-name">${escapeHtml(item.momoid)} / ${escapeHtml(item.send_momoid)}</div>
                            <div class="momo-meta">ID #${item.id} · 发送次数 ${item.send_num || 0}</div>
                        </div>
                        <div class="momo-side">
                            <div class="tags">
                                ${Number(item.is_online || 0) === 1 ? '<span class="tag online">在线</span>' : '<span class="tag">离线</span>'}
                                ${Number(item.is_friend || 0) === 1 ? '<span class="tag friend">好友</span>' : ''}
                                ${Number(item.is_block || 0) === 1 ? '<span class="tag blocked">拉黑</span>' : ''}
                                ${Number(item.isSayHi || 0) === 1 ? '<span class="tag">招呼</span>' : '<span class="tag">会话</span>'}
                            </div>
                            <div class="actions">
                                <button type="button" class="btn btn-primary" data-edit="${item.id}">编辑</button>
                                <button type="button" class="btn btn-secondary" onclick="location.href='chat_history.php?momo_user_id=${item.id}&momoid=${encodeURIComponent(item.momoid)}&send_momoid=${encodeURIComponent(item.send_momoid)}'">聊天记录</button>
                                <button type="button" class="btn btn-danger" data-delete="${item.id}">删除</button>
                            </div>
                        </div>
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
            if (!state.momoid) {
                throw new Error('缺少主账号 momoid');
            }
            const params = new URLSearchParams();
            params.set('momoid', state.momoid);
            params.set('page', String(state.page));
            params.set('per_page', String(state.perPage));
            if (state.search) params.set('search', state.search);
            if (state.isSayHi !== 'all') params.set('isSayHi', state.isSayHi);
            if (state.onlineStatus !== 'all') params.set('online_status', state.onlineStatus);
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
                        momoid: state.momoid,
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

        nodes.form.addEventListener('submit', saveItem);
        nodes.reset.addEventListener('click', () => { clearMessage(); resetForm(); });
        nodes.search.addEventListener('input', (event) => {
            state.search = event.target.value.trim();
            state.page = 1;
            loadData().catch((error) => showMessage('error', error.message));
        });
        nodes.typeFilter.addEventListener('change', (event) => {
            state.isSayHi = event.target.value;
            state.page = 1;
            loadData().catch((error) => showMessage('error', error.message));
        });
        nodes.onlineFilter.addEventListener('change', (event) => {
            state.onlineStatus = event.target.value;
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
            }
        });

        resetForm();
        loadData().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

$extraScript = str_replace('__INITIAL_MOMOID__', json_encode($initialMomoid, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $extraScript);
admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
