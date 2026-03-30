<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('files');

$extraHead = <<<'HTML'
<style>
    .message-box { display: none; margin-bottom: var(--spacing-md); padding: var(--spacing-md); border-radius: var(--border-radius); font-size: var(--font-size-sm); }
    .message-box.show { display: block; }
    .message-box.success { background: rgba(74, 222, 128, 0.14); color: #166534; }
    .message-box.error { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }

    .page-grid { display: grid; grid-template-columns: 340px minmax(0, 1fr); gap: var(--spacing-lg); }
    .panel { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius-lg); box-shadow: var(--shadow-sm); }
    .panel-header, .panel-body { padding: var(--spacing-lg); }
    .panel-header { border-bottom: 1px solid var(--border-color); }
    .stack { display: grid; gap: var(--spacing-md); }
    .hint { color: var(--gray-dark); font-size: var(--font-size-sm); }
    .toolbar { display: flex; justify-content: space-between; gap: var(--spacing-md); align-items: center; margin-bottom: var(--spacing-md); flex-wrap: wrap; }
    .toolbar select, .toolbar input { padding: 10px 12px; border: 1px solid var(--border-color); border-radius: var(--border-radius); font: inherit; }
    .toolbar input { width: min(320px, 100%); }
    .actions { display: flex; gap: var(--spacing-sm); flex-wrap: wrap; }
    .summary-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--spacing-md); margin-bottom: var(--spacing-md); }
    .summary-card { background: #fff; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--spacing-md); }
    .summary-card strong { display: block; font-size: 28px; line-height: 1; margin-bottom: var(--spacing-xs); }
    .file-list { display: grid; gap: var(--spacing-sm); }
    .file-item { border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--spacing-md); display: grid; gap: var(--spacing-sm); }
    .file-top { display: flex; justify-content: space-between; gap: var(--spacing-md); align-items: start; }
    .file-name { font-weight: var(--font-weight-semibold); word-break: break-word; }
    .file-meta { color: var(--gray-dark); font-size: var(--font-size-sm); }
    .empty-state { padding: var(--spacing-xl); text-align: center; color: var(--gray-dark); border: 1px dashed var(--border-color); border-radius: var(--border-radius); }
    .check-row { display: flex; gap: var(--spacing-sm); align-items: center; }
    .check-row input { width: 16px; height: 16px; }

    @media (max-width: 960px) {
        .page-grid { grid-template-columns: 1fr; }
        .summary-grid { grid-template-columns: 1fr; }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 文件管理', '文件管理', '页面只做上传与展示，文件读写通过 `/admin/files` 接口完成。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="page-grid">
    <section class="panel">
        <div class="panel-header">
            <h2>上传文件</h2>
        </div>
        <div class="panel-body stack">
            <form id="upload-form" class="stack">
                <input id="upload-files" type="file" name="files[]" multiple>
                <div class="hint">支持批量上传。相同文件名会覆盖原记录并重置下载状态。</div>
                <div class="actions">
                    <button type="submit" class="btn btn-primary">上传</button>
                </div>
            </form>
            <hr>
            <div class="stack">
                <h3>批量删除</h3>
                <div class="actions">
                    <button type="button" class="btn btn-danger" data-bulk="selected">删除已选</button>
                    <button type="button" class="btn btn-danger" data-bulk="pending">删除未下载</button>
                    <button type="button" class="btn btn-danger" data-bulk="downloaded">删除已下载</button>
                    <button type="button" class="btn btn-danger" data-bulk="all">删除全部</button>
                </div>
            </div>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <div class="toolbar">
                <div>
                    <h2>文件列表</h2>
                    <p>接口来源：`/admin/files`</p>
                </div>
                <select id="status-filter">
                    <option value="all">全部</option>
                    <option value="pending">未下载</option>
                    <option value="downloaded">已下载</option>
                </select>
                <input id="search-input" type="search" placeholder="搜索文件名">
            </div>
        </div>
        <div class="panel-body">
            <div class="summary-grid">
                <div class="summary-card"><strong id="sum-total">-</strong><span>总文件数</span></div>
                <div class="summary-card"><strong id="sum-pending">-</strong><span>未下载</span></div>
                <div class="summary-card"><strong id="sum-downloaded">-</strong><span>已下载</span></div>
            </div>
            <div id="file-list" class="file-list"></div>
        </div>
    </section>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const state = { items: [], filters: { status: 'all', search: '' }, selected: new Set() };
        const nodes = {
            message: document.getElementById('message-box'),
            uploadForm: document.getElementById('upload-form'),
            uploadFiles: document.getElementById('upload-files'),
            list: document.getElementById('file-list'),
            search: document.getElementById('search-input'),
            status: document.getElementById('status-filter'),
            total: document.getElementById('sum-total'),
            pending: document.getElementById('sum-pending'),
            downloaded: document.getElementById('sum-downloaded')
        };

        async function request(path, options = {}) {
            const config = { method: options.method || 'GET', headers: { 'X-Token': token, 'Accept': 'application/json' }, credentials: 'same-origin' };
            if (options.body instanceof FormData) {
                config.body = options.body;
                delete config.headers['Content-Type'];
            } else if (options.body) {
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

        function formatSize(size) {
            const value = Number(size || 0);
            if (value < 1024) return `${value} B`;
            if (value < 1024 * 1024) return `${(value / 1024).toFixed(2)} KB`;
            return `${(value / 1024 / 1024).toFixed(2)} MB`;
        }

        function renderList() {
            if (!state.items.length) {
                nodes.list.innerHTML = '<div class="empty-state">没有可显示的文件</div>';
                return;
            }

            nodes.list.innerHTML = state.items.map((item) => `
                <article class="file-item">
                    <div class="file-top">
                        <div class="check-row">
                            <input type="checkbox" data-select="${item.id}" ${state.selected.has(String(item.id)) ? 'checked' : ''}>
                            <div>
                                <div class="file-name">${escapeHtml(item.original_name || item.filename)}</div>
                                <div class="file-meta">${escapeHtml(item.filename)} · ${formatSize(item.file_size)} · ${item.is_downloaded ? '已下载' : '未下载'}</div>
                            </div>
                        </div>
                        <div class="actions">
                            <a class="btn btn-secondary" href="../${encodeURI(item.file_path)}" target="_blank" rel="noreferrer">查看</a>
                            <button type="button" class="btn btn-danger" data-delete="${item.id}">删除</button>
                        </div>
                    </div>
                </article>
            `).join('');
        }

        async function loadFiles() {
            const params = new URLSearchParams();
            params.set('status', state.filters.status);
            if (state.filters.search) params.set('search', state.filters.search);
            const data = await request(`../admin/files?${params.toString()}`);
            state.items = Array.isArray(data.items) ? data.items : [];
            state.selected.clear();
            nodes.total.textContent = data.summary?.total ?? 0;
            nodes.pending.textContent = data.summary?.pending ?? 0;
            nodes.downloaded.textContent = data.summary?.downloaded ?? 0;
            renderList();
        }

        async function uploadFiles(event) {
            event.preventDefault();
            clearMessage();
            if (!nodes.uploadFiles.files.length) {
                showMessage('error', '请选择要上传的文件');
                return;
            }
            const formData = new FormData();
            Array.from(nodes.uploadFiles.files).forEach((file) => formData.append('files[]', file));
            try {
                const data = await request('../admin/files/upload', { method: 'POST', body: formData });
                nodes.uploadForm.reset();
                showMessage('success', `上传完成：成功 ${data.success_count} 个，失败 ${data.failed_count} 个`);
                await loadFiles();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        async function removeFile(id) {
            try {
                await request('../admin/files/delete', { method: 'POST', body: { id } });
                showMessage('success', '文件删除成功');
                await loadFiles();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        async function bulkDelete(mode) {
            let body = { mode };
            if (mode === 'selected') {
                body.ids = Array.from(state.selected).map((id) => Number(id));
                if (!body.ids.length) {
                    showMessage('error', '请先勾选要删除的文件');
                    return;
                }
            }
            if (!confirm('确认执行批量删除吗？')) return;
            try {
                const data = await request('../admin/files/bulkDelete', { method: 'POST', body });
                showMessage('success', `删除完成，共处理 ${data.deleted_count} 个文件`);
                await loadFiles();
            } catch (error) {
                showMessage('error', error.message);
            }
        }

        nodes.uploadForm.addEventListener('submit', uploadFiles);
        nodes.search.addEventListener('input', (event) => { state.filters.search = event.target.value.trim(); loadFiles().catch((error) => showMessage('error', error.message)); });
        nodes.status.addEventListener('change', (event) => { state.filters.status = event.target.value; loadFiles().catch((error) => showMessage('error', error.message)); });
        document.querySelectorAll('[data-bulk]').forEach((button) => button.addEventListener('click', () => bulkDelete(button.dataset.bulk)));
        nodes.list.addEventListener('click', (event) => {
            const checkbox = event.target.closest('input[data-select]');
            if (checkbox) {
                if (checkbox.checked) state.selected.add(String(checkbox.dataset.select));
                else state.selected.delete(String(checkbox.dataset.select));
                return;
            }
            const button = event.target.closest('button[data-delete]');
            if (!button) return;
            if (!confirm('确定删除该文件吗？')) return;
            removeFile(Number(button.dataset.delete));
        });

        loadFiles().catch((error) => showMessage('error', error.message));
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
