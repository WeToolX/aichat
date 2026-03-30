<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('scripts');

$extraHead = <<<'HTML'
<style>
    .page-grid {
        display: grid;
        grid-template-columns: minmax(320px, 380px) minmax(0, 1fr);
        gap: 14px;
    }

    .panel {
        overflow: hidden;
    }

    .panel-header,
    .panel-body {
        padding: 14px 16px;
    }

    .panel-header {
        display: grid;
        gap: 4px;
    }

    .stack {
        display: grid;
        gap: 12px;
    }

    .form-row label {
        display: block;
        margin-bottom: 4px;
        font-weight: var(--font-weight-medium);
    }

    .form-row input,
    .form-row textarea {
        font: inherit;
    }

    .form-row textarea {
        min-height: 260px;
        resize: vertical;
    }

    .btn-row {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .info-box {
        padding: 12px 14px;
        border-radius: var(--border-radius);
        background: rgba(255, 255, 255, 0.18);
        border: 1px solid rgba(255, 255, 255, 0.26);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.18);
        color: var(--dark-color);
        font-size: var(--font-size-sm);
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: end;
        margin-bottom: 0;
    }

    .toolbar input {
        width: min(340px, 100%);
    }

    .message-box {
        display: none;
        margin-bottom: var(--spacing-md);
        padding: var(--spacing-md);
        border-radius: var(--border-radius);
        font-size: var(--font-size-sm);
    }

    .message-box.show {
        display: block;
    }

    .message-box.success {
        background: rgba(74, 222, 128, 0.14);
        color: #166534;
    }

    .message-box.error {
        background: rgba(248, 113, 113, 0.14);
        color: #b91c1c;
    }

    .list {
        display: grid;
        gap: 10px;
    }

    .script-item {
        border-radius: var(--border-radius);
        padding: 14px;
        display: grid;
        gap: 10px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.24);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16);
    }

    .script-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: flex-start;
        flex-wrap: wrap;
    }

    .script-name {
        font-weight: var(--font-weight-semibold);
        font-size: 15px;
    }

    .script-body {
        color: var(--gray-dark);
        white-space: pre-wrap;
        word-break: break-word;
        line-height: 1.55;
        font-size: 13px;
        background: rgba(255, 255, 255, 0.14);
        border: 1px solid rgba(255, 255, 255, 0.22);
        border-radius: 10px;
        padding: 10px 12px;
    }

    .item-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }

    .empty-state {
        padding: var(--spacing-xl);
        text-align: center;
        color: var(--gray-dark);
        border: 1px dashed var(--border-color);
        border-radius: var(--border-radius);
    }

    .tag {
        display: inline-flex;
        align-items: center;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(239, 68, 68, 0.14);
        color: #b91c1c;
        font-size: 12px;
        font-weight: var(--font-weight-medium);
    }

    .form-head {
        display: grid;
        gap: 4px;
    }

    .panel-header h2 {
        margin: 0;
    }

    .panel-header p {
        margin: 0;
        font-size: 13px;
    }

    @media (max-width: 960px) {
        .page-grid {
            grid-template-columns: 1fr;
        }

        .toolbar {
            flex-direction: column;
            align-items: stretch;
        }

        .toolbar input {
            width: 100%;
        }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 话术设置', '话术设置', '', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="page-grid">
    <section class="panel">
        <div class="panel-header">
            <h2 id="form-title">新增话术</h2>
        </div>
        <div class="panel-body stack">
            <div class="info-box">
                <div>必备话术：<strong>AI设置</strong>、<strong>引导话术</strong>。</div>
                <div>使用 <code>--</code> 可拆成多段发送。</div>
            </div>

            <form id="script-form" class="stack">
                <input type="hidden" id="script-id" value="">
                <div class="form-row">
                    <label for="script-name">话术名称</label>
                    <input id="script-name" type="text" placeholder="例如：AI设置">
                </div>
                <div class="form-row">
                    <label for="script-content">话术内容</label>
                    <textarea id="script-content" placeholder="输入话术内容"></textarea>
                </div>
                <div class="btn-row">
                    <button type="submit" class="btn btn-primary" id="save-btn">保存</button>
                    <button type="button" class="btn btn-secondary" id="reset-btn">重置</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="toolbar">
                <div>
                    <h2>话术列表</h2>
                </div>
                <input id="search-input" type="search" placeholder="搜索话术名称或内容">
            </div>
        </div>
        <div class="panel-body">
            <div id="script-list" class="list"></div>
        </div>
    </section>
</div>
<?php

$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const requiredNames = new Set(['AI设置', '引导话术']);
        const state = {
            items: [],
            keyword: ''
        };

        const nodes = {
            form: document.getElementById('script-form'),
            id: document.getElementById('script-id'),
            name: document.getElementById('script-name'),
            content: document.getElementById('script-content'),
            title: document.getElementById('form-title'),
            list: document.getElementById('script-list'),
            search: document.getElementById('search-input'),
            message: document.getElementById('message-box'),
            reset: document.getElementById('reset-btn')
        };

        async function request(path, options = {}) {
            const config = {
                method: options.method || 'GET',
                headers: {
                    'X-Token': token,
                    'Accept': 'application/json'
                },
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
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function formatDate(value) {
            if (!value) {
                return '-';
            }
            return String(value).replace('T', ' ').slice(0, 19);
        }

        function resetForm() {
            nodes.id.value = '';
            nodes.name.value = '';
            nodes.content.value = '';
            nodes.title.textContent = '新增话术';
        }

        function fillForm(item) {
            nodes.id.value = item.id;
            nodes.name.value = item.name || '';
            nodes.content.value = item.content || '';
            nodes.title.textContent = `编辑话术 #${item.id}`;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function filteredItems() {
            const keyword = state.keyword.trim().toLowerCase();
            if (!keyword) {
                return state.items;
            }

            return state.items.filter((item) => {
                const haystack = `${item.name || ''} ${item.content || ''}`.toLowerCase();
                return haystack.includes(keyword);
            });
        }

        function renderList() {
            const items = filteredItems();
            if (!items.length) {
                nodes.list.innerHTML = '<div class="empty-state">没有可显示的话术</div>';
                return;
            }

            nodes.list.innerHTML = items.map((item) => {
                const requiredTag = requiredNames.has(item.name) ? '<span class="tag">系统必备</span>' : '';
                const deleteButton = requiredNames.has(item.name)
                    ? ''
                    : `<button type="button" class="btn btn-danger" data-action="delete" data-id="${item.id}">删除</button>`;

                return `
                    <article class="script-item">
                        <div class="script-meta">
                            <div>
                                <div class="script-name">${escapeHtml(item.name || '')}</div>
                                <div class="text-sm text-gray-color">${formatDate(item.updated_at || item.created_at)}</div>
                            </div>
                            ${requiredTag}
                        </div>
                        <div class="script-body">${escapeHtml(item.content || '')}</div>
                        <div class="item-actions">
                            <button type="button" class="btn btn-primary" data-action="edit" data-id="${item.id}">编辑</button>
                            ${deleteButton}
                        </div>
                    </article>
                `;
            }).join('');
        }

        async function loadItems() {
            const data = await request('../admin/scripts');
            state.items = Array.isArray(data) ? data : [];
            renderList();
        }

        async function saveItem(event) {
            event.preventDefault();
            clearMessage();

            const payload = {
                id: nodes.id.value ? Number(nodes.id.value) : undefined,
                name: nodes.name.value.trim(),
                content: nodes.content.value.trim()
            };

            try {
                await request('../admin/scripts/save', {
                    method: 'POST',
                    body: payload
                });
                showMessage('success', '话术保存成功');
                resetForm();
                await loadItems();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        async function handleListClick(event) {
            const button = event.target.closest('button[data-action]');
            if (!button) {
                return;
            }

            const id = Number(button.dataset.id || 0);
            const item = state.items.find((entry) => Number(entry.id) === id);
            if (!item) {
                return;
            }

            if (button.dataset.action === 'edit') {
                clearMessage();
                fillForm(item);
                return;
            }

            if (!confirm(`确定删除话术“${item.name}”吗？`)) {
                return;
            }

            try {
                await request('../admin/scripts/delete', {
                    method: 'POST',
                    body: { id }
                });
                showMessage('success', '话术删除成功');
                if (Number(nodes.id.value) === id) {
                    resetForm();
                }
                await loadItems();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        nodes.form.addEventListener('submit', saveItem);
        nodes.reset.addEventListener('click', () => {
            clearMessage();
            resetForm();
        });
        nodes.search.addEventListener('input', (event) => {
            state.keyword = event.target.value || '';
            renderList();
        });
        nodes.list.addEventListener('click', handleListClick);

        loadItems().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
