<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('dashboard');

$extraHead = <<<'HTML'
<style>
    .overview-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: var(--spacing-lg);
        margin-bottom: var(--spacing-xl);
    }

    .overview-card,
    .panel-card,
    .quick-link {
        background: #fff;
        border: 1px solid var(--border-color);
        border-radius: var(--border-radius-lg);
        box-shadow: var(--shadow-sm);
    }

    .overview-card {
        padding: var(--spacing-lg);
        position: relative;
        overflow: hidden;
    }

    .overview-card::before {
        content: '';
        position: absolute;
        inset: 0 auto auto 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
    }

    .overview-label {
        font-size: var(--font-size-sm);
        color: var(--gray-dark);
        margin-bottom: var(--spacing-xs);
    }

    .overview-value {
        font-size: 32px;
        font-weight: var(--font-weight-bold);
        color: var(--dark-color);
        line-height: 1.1;
    }

    .overview-hint {
        margin-top: var(--spacing-sm);
        font-size: var(--font-size-sm);
        color: var(--gray-color);
    }

    .panel-grid {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: var(--spacing-lg);
    }

    .panel-card {
        padding: var(--spacing-lg);
    }

    .panel-card h2 {
        margin-bottom: var(--spacing-md);
    }

    .quick-links {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: var(--spacing-md);
    }

    .quick-link {
        display: block;
        padding: var(--spacing-lg);
        color: var(--dark-color);
        text-decoration: none;
        transition: var(--transition);
    }

    .quick-link:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow);
        text-decoration: none;
    }

    .quick-link strong {
        display: block;
        margin-bottom: var(--spacing-xs);
    }

    .quick-link span {
        color: var(--gray-dark);
        font-size: var(--font-size-sm);
    }

    .status-list {
        display: grid;
        gap: var(--spacing-sm);
    }

    .status-item {
        display: flex;
        justify-content: space-between;
        gap: var(--spacing-md);
        padding: var(--spacing-sm) 0;
        border-bottom: 1px solid var(--border-color);
    }

    .status-item:last-child {
        border-bottom: none;
    }

    .state-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        border-radius: 999px;
        background: rgba(74, 222, 128, 0.14);
        color: #15803d;
        font-size: var(--font-size-sm);
        font-weight: var(--font-weight-medium);
    }

    @media (max-width: 960px) {
        .panel-grid {
            grid-template-columns: 1fr;
        }
    }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 首页', '仪表盘', '后台首页已改为前端驱动，统计数据通过当前项目的管理接口加载。', $extraHead);
?>
<section class="overview-grid">
    <article class="overview-card">
        <div class="overview-label">用户总数</div>
        <div class="overview-value" data-metric="users">-</div>
        <div class="overview-hint">仅超级用户可见全量数据</div>
    </article>
    <article class="overview-card">
        <div class="overview-label">话术数量</div>
        <div class="overview-value" data-metric="scripts">-</div>
        <div class="overview-hint">当前用户可管理的话术总数</div>
    </article>
    <article class="overview-card">
        <div class="overview-label">关键词数量</div>
        <div class="overview-value" data-metric="keywords">-</div>
        <div class="overview-hint">当前用户可管理的关键词总数</div>
    </article>
    <article class="overview-card">
        <div class="overview-label">陌陌会话</div>
        <div class="overview-value" data-metric="momo_users">-</div>
        <div class="overview-hint">当前用户可见会话量</div>
    </article>
</section>

<section class="panel-grid">
    <article class="panel-card">
        <h2>快捷入口</h2>
        <div class="quick-links">
            <a class="quick-link" href="script_management.php">
                <strong>话术设置</strong>
                <span>管理 AI 设置与引导话术</span>
            </a>
            <a class="quick-link" href="keyword_management.php">
                <strong>关键词管理</strong>
                <span>维护关键词匹配和自动回复</span>
            </a>
            <a class="quick-link" href="momo_management.php">
                <strong>陌陌用户</strong>
                <span>查看会话和状态信息</span>
            </a>
            <a class="quick-link" href="function_settings.php">
                <strong>功能设置</strong>
                <span>管理自动化参数和节奏</span>
            </a>
        </div>
    </article>

    <article class="panel-card">
        <h2>当前状态</h2>
        <div class="status-list">
            <div class="status-item">
                <span>当前用户</span>
                <strong id="current-user"><?php echo admin_shell_escape($ctx['user']['username'] ?? ''); ?></strong>
            </div>
            <div class="status-item">
                <span>用户角色</span>
                <strong id="current-role"><?php echo $ctx['is_super'] ? '超级用户' : '普通用户'; ?></strong>
            </div>
            <div class="status-item">
                <span>未下载文件</span>
                <strong data-metric="undownloaded_files">-</strong>
            </div>
            <div class="status-item">
                <span>接口状态</span>
                <span class="state-badge" id="api-status">加载中</span>
            </div>
        </div>
    </article>
</section>
<?php

$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const apiStatus = document.getElementById('api-status');

        async function request(path) {
            const response = await fetch(path, {
                headers: {
                    'X-Token': token,
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            });

            const payload = await response.json();
            if (!payload.success) {
                throw new Error(payload.message || '请求失败');
            }

            return payload.data;
        }

        function setMetric(name, value) {
            document.querySelectorAll(`[data-metric="${name}"]`).forEach((node) => {
                node.textContent = String(value ?? 0);
            });
        }

        async function loadDashboard() {
            try {
                const data = await request('../admin/dashboard');
                Object.keys(data).forEach((key) => setMetric(key, data[key]));
                apiStatus.textContent = '正常';
            } catch (error) {
                apiStatus.textContent = '异常';
                console.error(error);
            }
        }

        loadDashboard();
    }());
</script>
HTML;

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
