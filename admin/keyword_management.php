<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('keywords');

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
        min-height: 220px;
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
        color: var(--dark-color);
        font-size: var(--font-size-sm);
    }

    .toolbar {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: end;
        margin-bottom: 0;
        flex-wrap: wrap;
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

    .keyword-item {
        border-radius: var(--border-radius);
        padding: 14px;
        display: grid;
        gap: 10px;
        background: rgba(255, 255, 255, 0.16);
        border: 1px solid rgba(255, 255, 255, 0.24);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.16);
    }

    .keyword-top {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        align-items: start;
        flex-wrap: wrap;
    }

    .keyword-word {
        font-weight: var(--font-weight-semibold);
    }

    .keyword-reply {
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
    .panel-header h2 { margin: 0; }
    .toolbar-copy { display: grid; gap: 4px; }
    .toolbar-copy p { margin: 0; font-size: 13px; }

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
admin_shell_start($ctx, '后台管理系统 - 关键词管理', '关键词管理', '页面不再直接读写数据库，所有操作通过 `/admin/keywords` 接口完成。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="page-grid">
    <section class="panel">
        <div class="panel-header">
            <h2 id="form-title">新增关键词</h2>
        </div>
        <div class="panel-body stack">
            <div class="info-box">
                <div>关键词为模糊匹配，命中后返回对应回复内容。</div>
                <div>多条候选可用 <code>--</code> 分隔，分段内容可用 <code>#</code> 分隔。</div>
            </div>

            <form id="keyword-form" class="stack">
                <input type="hidden" id="keyword-id" value="">
                <div class="form-row">
                    <label for="keyword-word">关键词</label>
                    <input id="keyword-word" type="text" placeholder="例如：微信">
                </div>
                <div class="form-row">
                    <label for="keyword-reply">回复内容</label>
                    <textarea id="keyword-reply" placeholder="输入触发后的回复内容"></textarea>
                </div>
                <div class="btn-row">
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
                    <h2>关键词列表</h2>
                    <p>当前列表来自 `/admin/keywords` 接口。</p>
                </div>
                <input id="search-input" type="search" placeholder="搜索关键词或回复内容">
            </div>
        </div>
        <div class="panel-body">
            <div id="keyword-list" class="list"></div>
        </div>
    </section>
</div>
<?php

$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const state = {
            items: [],
            keyword: ''
        };

        const nodes = {
            form: document.getElementById('keyword-form'),
            id: document.getElementById('keyword-id'),
            keyword: document.getElementById('keyword-word'),
            reply: document.getElementById('keyword-reply'),
            title: document.getElementById('form-title'),
            list: document.getElementById('keyword-list'),
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
            nodes.keyword.value = '';
            nodes.reply.value = '';
            nodes.title.textContent = '新增关键词';
        }

        function fillForm(item) {
            nodes.id.value = item.id;
            nodes.keyword.value = item.keyword || '';
            nodes.reply.value = item.reply || '';
            nodes.title.textContent = `编辑关键词 #${item.id}`;
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        function filteredItems() {
            const keyword = state.keyword.trim().toLowerCase();
            if (!keyword) {
                return state.items;
            }

            return state.items.filter((item) => {
                const haystack = `${item.keyword || ''} ${item.reply || ''}`.toLowerCase();
                return haystack.includes(keyword);
            });
        }

        function renderList() {
            const items = filteredItems();
            if (!items.length) {
                nodes.list.innerHTML = '<div class="empty-state">没有可显示的关键词</div>';
                return;
            }

            nodes.list.innerHTML = items.map((item) => `
                <article class="keyword-item">
                    <div class="keyword-top">
                        <div>
                            <div class="keyword-word">${escapeHtml(item.keyword || '')}</div>
                            <div class="text-sm text-gray-color">${formatDate(item.updated_at || item.created_at)}</div>
                        </div>
                    </div>
                    <div class="keyword-reply">${escapeHtml(item.reply || '')}</div>
                    <div class="item-actions">
                        <button type="button" class="btn btn-primary" data-action="edit" data-id="${item.id}">编辑</button>
                        <button type="button" class="btn btn-danger" data-action="delete" data-id="${item.id}">删除</button>
                    </div>
                </article>
            `).join('');
        }

        async function loadItems() {
            const data = await request('../admin/keywords');
            state.items = Array.isArray(data) ? data : [];
            renderList();
        }

        async function saveItem(event) {
            event.preventDefault();
            clearMessage();

            const payload = {
                id: nodes.id.value ? Number(nodes.id.value) : undefined,
                keyword: nodes.keyword.value.trim(),
                reply: nodes.reply.value.trim()
            };

            try {
                await request('../admin/keywords/save', {
                    method: 'POST',
                    body: payload
                });
                showMessage('success', '关键词保存成功');
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

            if (!confirm(`确定删除关键词“${item.keyword}”吗？`)) {
                return;
            }

            try {
                await request('../admin/keywords/delete', {
                    method: 'POST',
                    body: { id }
                });
                showMessage('success', '关键词删除成功');
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
