<?php

require_once __DIR__ . '/admin_runtime.php';

function admin_shell_bootstrap($active)
{
    $auth = new AdminPageAuth();
    $user = $auth->requireLogin();

    return array(
        'auth' => $auth,
        'user' => $user,
        'active' => $active,
        'token' => $auth->generateToken($user['id']),
        'is_super' => (int) ($user['role'] ?? 0) === 1,
    );
}

function admin_shell_escape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function admin_shell_start(array $ctx, $title, $pageTitle, $pageSubtitle, $extraHead = '')
{
    $user = $ctx['user'];
    $isSuper = $ctx['is_super'];
    $active = $ctx['active'];
    ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo admin_shell_escape($title); ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <?php echo $extraHead; ?>
</head>
<body>
    <header class="header">
        <button type="button" class="sidebar-toggle sidebar-toggle-open" id="sidebar-open-btn" aria-label="展开导航">
            <span class="toggle-icon">☰</span>
        </button>
        <a href="index.php" class="header-logo">
            <span>🔧</span>
            <span>后台管理系统</span>
        </a>
        <div class="user-info">
            <span>欢迎，<?php echo admin_shell_escape($user['username'] ?? ''); ?></span>
            <a href="../login.php?action=logout">退出登录</a>
        </div>
    </header>

    <div class="container">
        <aside class="sidebar" id="admin-sidebar">
            <div class="sidebar-head">
                <span class="sidebar-head-title">导航</span>
                <button type="button" class="sidebar-toggle sidebar-toggle-collapse" id="sidebar-collapse-btn" aria-label="收起导航">
                    <span class="toggle-icon toggle-icon-collapse">◀</span>
                    <span class="toggle-icon toggle-icon-expand">▶</span>
                </button>
            </div>
            <ul>
                <li class="<?php echo $active === 'dashboard' ? 'active' : ''; ?>">
                    <a href="index.php">
                        <span class="sidebar-icon">🏠</span>
                        <span class="sidebar-text">首页</span>
                    </a>
                </li>
                <?php if ($isSuper): ?>
                    <li class="<?php echo $active === 'users' ? 'active' : ''; ?>">
                        <a href="user_management.php">
                            <span class="sidebar-icon">👥</span>
                            <span class="sidebar-text">用户管理</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="<?php echo $active === 'files' ? 'active' : ''; ?>">
                    <a href="file_management.php">
                        <span class="sidebar-icon">📁</span>
                        <span class="sidebar-text">文件管理</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'scripts' ? 'active' : ''; ?>">
                    <a href="script_management.php">
                        <span class="sidebar-icon">💬</span>
                        <span class="sidebar-text">话术设置</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'keywords' ? 'active' : ''; ?>">
                    <a href="keyword_management.php">
                        <span class="sidebar-icon">🔑</span>
                        <span class="sidebar-text">关键词管理</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'momo' ? 'active' : ''; ?>">
                    <a href="momo_management.php">
                        <span class="sidebar-icon">📱</span>
                        <span class="sidebar-text">陌陌用户管理</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'settings' ? 'active' : ''; ?>">
                    <a href="function_settings.php">
                        <span class="sidebar-icon">⚙️</span>
                        <span class="sidebar-text">功能设置</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'profile' ? 'active' : ''; ?>">
                    <a href="profile.php">
                        <span class="sidebar-icon">👤</span>
                        <span class="sidebar-text">个人设置</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <h1 class="page-title"><?php echo $pageTitle; ?></h1>
                    <p class="page-subtitle"><?php echo admin_shell_escape($pageSubtitle); ?></p>
                </div>
<?php
}

function admin_shell_end(array $ctx, $extraScript = '')
{
    $payload = array(
        'user' => $ctx['user'],
        'token' => $ctx['token'],
        'isSuper' => $ctx['is_super'],
    );
    ?>
            </div>
        </main>
    </div>
    <script>
        window.ADMIN_BOOTSTRAP = <?php echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    </script>
    <script>
        (function () {
            const storageKey = 'admin-sidebar-collapsed';
            const body = document.body;
            const openBtn = document.getElementById('sidebar-open-btn');
            const collapseBtn = document.getElementById('sidebar-collapse-btn');

            function isMobile() {
                return window.innerWidth <= 768;
            }

            function setCollapsed(collapsed) {
                body.classList.toggle('sidebar-collapsed', collapsed);
                try {
                    localStorage.setItem(storageKey, collapsed ? '1' : '0');
                } catch (error) {}
            }

            function setOpen(open) {
                body.classList.toggle('sidebar-open', open);
            }

            function syncResponsiveState() {
                if (isMobile()) {
                    setOpen(false);
                    body.classList.remove('sidebar-collapsed');
                    return;
                }

                try {
                    setCollapsed(localStorage.getItem(storageKey) === '1');
                } catch (error) {
                    setCollapsed(false);
                }
            }

            openBtn.addEventListener('click', function () {
                if (isMobile()) {
                    setOpen(!body.classList.contains('sidebar-open'));
                    return;
                }

                setCollapsed(!body.classList.contains('sidebar-collapsed'));
            });

            collapseBtn.addEventListener('click', function () {
                if (isMobile()) {
                    setOpen(false);
                    return;
                }

                setCollapsed(!body.classList.contains('sidebar-collapsed'));
            });

            window.addEventListener('resize', syncResponsiveState);
            document.addEventListener('click', function (event) {
                if (!isMobile() || !body.classList.contains('sidebar-open')) {
                    return;
                }

                const sidebar = document.getElementById('admin-sidebar');
                if (!sidebar || sidebar.contains(event.target) || openBtn.contains(event.target)) {
                    return;
                }

                setOpen(false);
            });

            syncResponsiveState();
        }());
    </script>
    <?php echo $extraScript; ?>
</body>
</html>
<?php
}
