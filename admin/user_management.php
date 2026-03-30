<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('users');
if (!$ctx['is_super']) {
    header('Location: index.php');
    exit;
}

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
    .stack { display: grid; gap: 12px; }
    .field label { display: block; margin-bottom: 4px; font-weight: var(--font-weight-medium); }
    .field input, .field select { font: inherit; }
    .actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .toolbar { display: flex; justify-content: space-between; gap: 12px; align-items: end; margin-bottom: 0; flex-wrap: wrap; }
    .toolbar input { width: min(340px, 100%); }
    .user-list { display: grid; gap: 10px; }
    .user-item { border-radius: var(--border-radius); padding: 14px; display: grid; gap: 10px; background: rgba(255, 255, 255, 0.16); border: 1px solid rgba(255, 255, 255, 0.24); box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16); }
    .user-top { display: flex; justify-content: space-between; gap: 12px; align-items: start; flex-wrap: wrap; }
    .user-name { font-weight: var(--font-weight-semibold); }
    .user-meta { color: var(--gray-dark); font-size: var(--font-size-sm); }
    .role-tag { display: inline-flex; padding: 2px 10px; border-radius: 999px; font-size: 12px; font-weight: var(--font-weight-medium); }
    .role-tag.super { background: rgba(67, 97, 238, 0.12); color: #1d4ed8; }
    .role-tag.user { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .empty-state { padding: var(--spacing-xl); text-align: center; color: var(--gray-dark); border: 1px dashed var(--border-color); border-radius: var(--border-radius); }
    .panel-header h2 { margin: 0; }
    .toolbar-copy { display: grid; gap: 4px; }
    .toolbar-copy p { margin: 0; font-size: 13px; }

    @media (max-width: 960px) {
        .page-grid { grid-template-columns: 1fr; }
        .toolbar { flex-direction: column; align-items: stretch; }
        .toolbar input { width: 100%; }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 用户管理', '用户管理', '', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="page-grid">
    <section class="panel">
        <div class="panel-header">
            <h2 id="form-title">新增用户</h2>
        </div>
        <div class="panel-body">
            <form id="user-form" class="stack">
                <input type="hidden" id="user-id" value="">
                <div class="field">
                    <label for="username">用户名</label>
                    <input id="username" type="text" placeholder="输入用户名">
                </div>
                <div class="field">
                    <label for="password">密码</label>
                    <input id="password" type="password" placeholder="新增用户必填，编辑用户可留空">
                </div>
                <div class="field">
                    <label for="email">邮箱</label>
                    <input id="email" type="text" placeholder="可留空，系统会自动补默认邮箱">
                </div>
                <div class="field">
                    <label for="role">角色</label>
                    <select id="role">
                        <option value="2">普通用户</option>
                        <option value="1">超级用户</option>
                    </select>
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
                <div class="toolbar-copy">
                    <h2>用户列表</h2>
                </div>
                <input id="search-input" type="search" placeholder="搜索用户名或邮箱">
            </div>
        </div>
        <div class="panel-body">
            <div id="user-list" class="user-list"></div>
        </div>
    </section>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const state = { items: [], keyword: '' };
        const nodes = {
            form: document.getElementById('user-form'),
            id: document.getElementById('user-id'),
            username: document.getElementById('username'),
            password: document.getElementById('password'),
            email: document.getElementById('email'),
            role: document.getElementById('role'),
            title: document.getElementById('form-title'),
            list: document.getElementById('user-list'),
            search: document.getElementById('search-input'),
            message: document.getElementById('message-box'),
            reset: document.getElementById('reset-btn')
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

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function resetForm() {
            nodes.id.value = '';
            nodes.username.value = '';
            nodes.password.value = '';
            nodes.email.value = '';
            nodes.role.value = '2';
            nodes.title.textContent = '新增用户';
        }

        function fillForm(item) {
            nodes.id.value = item.id;
            nodes.username.value = item.username || '';
            nodes.password.value = '';
            nodes.email.value = item.email || '';
            nodes.role.value = String(item.role || 2);
            nodes.title.textContent = `编辑用户 #${item.id}`;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function filteredItems() {
            const keyword = state.keyword.trim().toLowerCase();
            if (!keyword) return state.items;
            return state.items.filter((item) => `${item.username || ''} ${item.email || ''}`.toLowerCase().includes(keyword));
        }

        function renderList() {
            const items = filteredItems();
            if (!items.length) {
                nodes.list.innerHTML = '<div class="empty-state">没有可显示的用户</div>';
                return;
            }

            nodes.list.innerHTML = items.map((item) => {
                const superRole = Number(item.role) === 1;
                const deleteButton = item.id === boot.user.id || superRole
                    ? ''
                    : `<button type="button" class="btn btn-danger" data-action="delete" data-id="${item.id}">删除</button>`;

                return `
                    <article class="user-item">
                        <div class="user-top">
                            <div>
                                <div class="user-name">${escapeHtml(item.username)}</div>
                                <div class="user-meta">${escapeHtml(item.email || '')}</div>
                            </div>
                            <span class="role-tag ${superRole ? 'super' : 'user'}">${superRole ? '超级用户' : '普通用户'}</span>
                        </div>
                        <div class="user-meta">ID #${item.id} · 创建于 ${escapeHtml(item.created_at || '')}</div>
                        <div class="actions">
                            <button type="button" class="btn btn-primary" data-action="edit" data-id="${item.id}">编辑</button>
                            ${deleteButton}
                        </div>
                    </article>
                `;
            }).join('');
        }

        async function loadUsers() {
            const data = await request('../admin/users');
            state.items = Array.isArray(data) ? data : [];
            renderList();
        }

        async function saveUser(event) {
            event.preventDefault();
            clearMessage();

            const payload = {
                id: nodes.id.value ? Number(nodes.id.value) : undefined,
                username: nodes.username.value.trim(),
                password: nodes.password.value,
                email: nodes.email.value.trim(),
                role: Number(nodes.role.value || 2)
            };

            try {
                await request('../admin/users/save', { method: 'POST', body: payload });
                showMessage('success', '用户保存成功');
                resetForm();
                await loadUsers();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        async function handleListClick(event) {
            const button = event.target.closest('button[data-action]');
            if (!button) return;

            const id = Number(button.dataset.id || 0);
            const item = state.items.find((entry) => Number(entry.id) === id);
            if (!item) return;

            if (button.dataset.action === 'edit') {
                clearMessage();
                fillForm(item);
                return;
            }

            if (!confirm(`确定删除用户“${item.username}”吗？`)) return;

            try {
                await request('../admin/users/delete', { method: 'POST', body: { id } });
                showMessage('success', '用户删除成功');
                if (Number(nodes.id.value) === id) resetForm();
                await loadUsers();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        nodes.form.addEventListener('submit', saveUser);
        nodes.reset.addEventListener('click', () => { clearMessage(); resetForm(); });
        nodes.search.addEventListener('input', (event) => { state.keyword = event.target.value || ''; renderList(); });
        nodes.list.addEventListener('click', handleListClick);

        loadUsers().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
