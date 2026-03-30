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

function admin_shell_icon_markup($name)
{
    switch ($name) {
        case 'brand':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5h14v14H5z" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 9h8M8 12h8M8 15h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'menu':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'collapse':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        case 'expand':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        case 'dashboard':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="13" y="4" width="7" height="5" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="13" y="11" width="7" height="9" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/><rect x="4" y="13" width="7" height="7" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
        case 'users':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6zM17 12a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5zM4.5 19a4.5 4.5 0 0 1 9 0M13.5 19a3.5 3.5 0 0 1 7 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'files':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H10l2 2h5.5A2.5 2.5 0 0 1 20 9.5v8A2.5 2.5 0 0 1 17.5 20h-11A2.5 2.5 0 0 1 4 17.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
        case 'scripts':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6.5h12A1.5 1.5 0 0 1 19.5 8v8A1.5 1.5 0 0 1 18 17.5H9l-4.5 3V8A1.5 1.5 0 0 1 6 6.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M8 10.5h8M8 13.5h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'keywords':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="8.5" cy="10.5" r="3.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M11 13l6 6M15 15l1.5-1.5M17 16.5l1.5-1.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'momo':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="3.5" width="10" height="17" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M10 6.5h4M11 17.5h2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'settings':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h8M5 17h14M15 7h4M5 12h14" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="11" cy="7" r="2" fill="#fff" stroke="currentColor" stroke-width="1.8"/><circle cx="15" cy="12" r="2" fill="#fff" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="17" r="2" fill="#fff" stroke="currentColor" stroke-width="1.8"/></svg>';
        case 'profile':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5zM5 19a7 7 0 0 1 14 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'chat':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 6.5h12A1.5 1.5 0 0 1 19.5 8v7A1.5 1.5 0 0 1 18 16.5H10l-4.5 3V8A1.5 1.5 0 0 1 6 6.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
        case 'detail':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 7h10M8 12h10M8 17h10M4.5 7h.01M4.5 12h.01M4.5 17h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'arrow-left':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 12H5M11 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        case 'send':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 11.5 20 4l-5.5 16-2.5-6L4 11.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M11.5 14 20 4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'empty':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="6" width="14" height="12" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10h8M8 14h5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'warning':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4 3.5 19h17z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><path d="M12 9v4M12 16h.01" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'lock':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="10" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8 10V7.5a4 4 0 1 1 8 0V10" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';
        case 'eye':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12S6 6.5 12 6.5 21.5 12 21.5 12 18 17.5 12 17.5 2.5 12 2.5 12z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="12" cy="12" r="2.5" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
        case 'eye-off':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 3l18 18M10.7 6.7A10.4 10.4 0 0 1 12 6.5c6 0 9.5 5.5 9.5 5.5a18 18 0 0 1-4 4.4M6.1 6.1A17.7 17.7 0 0 0 2.5 12S6 17.5 12 17.5c1.3 0 2.5-.2 3.6-.6M9.9 9.9A3 3 0 0 0 14.1 14.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>';
    }
}

function admin_shell_icon($name, $class = '')
{
    $classes = trim('icon ' . $class);
    return '<span class="' . admin_shell_escape($classes) . '">' . admin_shell_icon_markup($name) . '</span>';
}

function admin_shell_clean_title($title)
{
    $title = trim((string) $title);
    $clean = preg_replace('/^[^\p{L}\p{N}]+/u', '', $title);
    return $clean !== null && $clean !== '' ? $clean : $title;
}

function admin_shell_page_icon_name(array $ctx, $pageTitle)
{
    $cleanTitle = admin_shell_clean_title($pageTitle);
    if (strpos($cleanTitle, '聊天') !== false) {
        return 'chat';
    }
    if (strpos($cleanTitle, '明细') !== false) {
        return 'detail';
    }

    switch ((string) ($ctx['active'] ?? '')) {
        case 'dashboard':
            return 'dashboard';
        case 'users':
            return 'users';
        case 'files':
            return 'files';
        case 'scripts':
            return 'scripts';
        case 'keywords':
            return 'keywords';
        case 'momo':
            return 'momo';
        case 'settings':
            return 'settings';
        case 'profile':
            return 'profile';
        default:
            return 'dashboard';
    }
}

function admin_shell_start(array $ctx, $title, $pageTitle, $pageSubtitle, $extraHead = '')
{
    $user = $ctx['user'];
    $isSuper = $ctx['is_super'];
    $active = $ctx['active'];
    $cleanPageTitle = admin_shell_clean_title($pageTitle);
    $pageIconName = admin_shell_page_icon_name($ctx, $pageTitle);
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
            <?php echo admin_shell_icon('menu', 'toggle-icon'); ?>
        </button>
        <a href="index.php" class="header-logo">
            <span class="header-logo-mark"><?php echo admin_shell_icon('brand'); ?></span>
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
                    <?php echo admin_shell_icon('collapse', 'toggle-icon toggle-icon-collapse'); ?>
                    <?php echo admin_shell_icon('expand', 'toggle-icon toggle-icon-expand'); ?>
                </button>
            </div>
            <ul>
                <li class="<?php echo $active === 'dashboard' ? 'active' : ''; ?>">
                    <a href="index.php">
                        <?php echo admin_shell_icon('dashboard', 'sidebar-icon'); ?>
                        <span class="sidebar-text">首页</span>
                    </a>
                </li>
                <?php if ($isSuper): ?>
                    <li class="<?php echo $active === 'users' ? 'active' : ''; ?>">
                        <a href="user_management.php">
                            <?php echo admin_shell_icon('users', 'sidebar-icon'); ?>
                            <span class="sidebar-text">用户管理</span>
                        </a>
                    </li>
                <?php endif; ?>
                <li class="<?php echo $active === 'files' ? 'active' : ''; ?>">
                    <a href="file_management.php">
                        <?php echo admin_shell_icon('files', 'sidebar-icon'); ?>
                        <span class="sidebar-text">文件管理</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'scripts' ? 'active' : ''; ?>">
                    <a href="script_management.php">
                        <?php echo admin_shell_icon('scripts', 'sidebar-icon'); ?>
                        <span class="sidebar-text">话术设置</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'keywords' ? 'active' : ''; ?>">
                    <a href="keyword_management.php">
                        <?php echo admin_shell_icon('keywords', 'sidebar-icon'); ?>
                        <span class="sidebar-text">关键词管理</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'momo' ? 'active' : ''; ?>">
                    <a href="momo_management.php">
                        <?php echo admin_shell_icon('momo', 'sidebar-icon'); ?>
                        <span class="sidebar-text">陌陌用户管理</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'settings' ? 'active' : ''; ?>">
                    <a href="function_settings.php">
                        <?php echo admin_shell_icon('settings', 'sidebar-icon'); ?>
                        <span class="sidebar-text">功能设置</span>
                    </a>
                </li>
                <li class="<?php echo $active === 'profile' ? 'active' : ''; ?>">
                    <a href="profile.php">
                        <?php echo admin_shell_icon('profile', 'sidebar-icon'); ?>
                        <span class="sidebar-text">个人设置</span>
                    </a>
                </li>
            </ul>
        </aside>

        <main class="main-content">
            <div class="content-wrapper">
                <div class="page-header">
                    <h1 class="page-title">
                        <?php echo admin_shell_icon($pageIconName, 'page-icon'); ?>
                        <span><?php echo admin_shell_escape($cleanPageTitle); ?></span>
                    </h1>
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
