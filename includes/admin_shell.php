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
        <aside class="sidebar">
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
    <?php echo $extraScript; ?>
</body>
</html>
<?php
}
