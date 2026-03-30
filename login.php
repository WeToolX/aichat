<?php
require_once __DIR__ . '/includes/admin_runtime.php';
$auth = new AdminPageAuth();

$loggedOut = false;
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $auth->logout();
    $loggedOut = true;
}

if ($auth->isLoggedIn() && !$loggedOut) {
    header('Location: admin/index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>后台管理系统 - 登录</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            padding: 0;
            background: linear-gradient(135deg, #4361ee 0%, #4cc9f0 100%);
            background-size: cover;
            background-position: center;
            position: relative;
        }
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            z-index: 0;
        }
        
        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            padding: var(--spacing-md);
        }
        
        .login-container {
            background-color: #fff;
            padding: var(--spacing-xl);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            transition: var(--transition);
            border: 1px solid var(--border-color);
            position: relative;
            overflow: hidden;
        }
        
        .login-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
        }
        
        .login-container:hover {
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            transform: translateY(-5px);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            padding-bottom: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
        }
        
        .login-logo {
            font-size: 32px;
            font-weight: var(--font-weight-bold);
            color: var(--primary-color);
            margin-bottom: var(--spacing-md);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--spacing-md);
        }
        
        .login-title {
            font-size: var(--font-size-xl);
            font-weight: var(--font-weight-semibold);
            color: var(--dark-color);
            margin-bottom: var(--spacing-sm);
        }
        
        .login-subtitle {
            font-size: var(--font-size-md);
            color: var(--gray-color);
            font-weight: var(--font-weight-normal);
        }
        
        .login-form {
            margin-bottom: var(--spacing-lg);
        }
        
        .form-group {
            margin-bottom: var(--spacing-lg);
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: var(--spacing-sm);
            color: var(--dark-color);
            font-weight: var(--font-weight-medium);
            font-size: var(--font-size-sm);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .form-group input {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius);
            font-size: var(--font-size-md);
            transition: var(--transition);
            background-color: #fff;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            transform: translateY(-1px);
        }
        
        .password-container {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: var(--spacing-md);
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--gray-color);
            transition: var(--transition);
        }
        
        .password-toggle:hover {
            color: var(--primary-color);
            transform: translateY(-50%) scale(1.1);
        }
        
        .login-btn {
            width: 100%;
            padding: var(--spacing-md);
            border: none;
            border-radius: var(--border-radius);
            background-color: var(--primary-color);
            color: #fff;
            font-size: var(--font-size-md);
            font-weight: var(--font-weight-semibold);
            cursor: pointer;
            transition: var(--transition);
            margin-bottom: var(--spacing-lg);
            box-shadow: 0 4px 6px -1px rgba(67, 97, 238, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), rgba(255, 255, 255, 0.3));
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        
        .login-btn:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 8px 16px -3px rgba(67, 97, 238, 0.5);
        }
        
        .login-btn:hover::before {
            transform: scaleX(1);
        }
        
        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 4px 6px -1px rgba(67, 97, 238, 0.4);
        }
        
        .login-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .login-footer {
            text-align: center;
            padding-top: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
        }
        
        .login-footer-links {
            display: flex;
            justify-content: center;
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
        }
        
        .login-footer-link {
            color: var(--gray-color);
            text-decoration: none;
            font-size: var(--font-size-sm);
            transition: var(--transition);
        }
        
        .login-footer-link:hover {
            color: var(--primary-color);
            text-decoration: underline;
        }
        
        .init-db {
            text-align: center;
            margin-top: var(--spacing-lg);
            font-size: var(--font-size-sm);
        }
        
        .init-db a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--border-radius-sm);
            background-color: rgba(67, 97, 238, 0.1);
        }
        
        .init-db a:hover {
            text-decoration: none;
            color: var(--primary-hover);
            background-color: rgba(67, 97, 238, 0.2);
            transform: translateY(-1px);
        }
        
        .alert {
            margin-bottom: var(--spacing-lg);
        }
        
        /* 加载动画样式 */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading {
            display: inline-block;
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
            margin-right: 8px;
        }
        
        @media (max-width: 768px) {
            .login-wrapper {
                padding: var(--spacing-sm);
            }
            
            .login-container {
                padding: var(--spacing-lg);
            }
            
            .login-logo {
                font-size: 24px;
            }
            
            .login-title {
                font-size: var(--font-size-lg);
            }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: var(--spacing-md);
            }
            
            .form-group input {
                padding: var(--spacing-sm);
                font-size: var(--font-size-sm);
            }
            
            .login-btn {
                padding: var(--spacing-sm);
                font-size: var(--font-size-sm);
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <div class="login-header">
                <div class="login-logo">🔐</div>
                <h2 class="login-title">后台管理系统</h2>
                <p class="login-subtitle">通过当前项目的认证接口完成登录</p>
            </div>

            <div id="message-box" class="alert" style="display:none;"></div>

            <?php if ($loggedOut): ?>
                <div class="alert alert-success">
                    <div class="alert-content">
                        <div class="alert-title">已退出登录</div>
                        <div class="alert-message">当前会话已清除，请重新登录。</div>
                    </div>
                </div>
            <?php endif; ?>

            <form id="login-form" class="login-form">
                <div class="form-group">
                    <label for="username">用户名</label>
                    <input type="text" id="username" name="username" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label for="password">密码</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <span class="password-toggle" onclick="togglePassword()">👁️</span>
                    </div>
                </div>
                <button id="login-btn" type="submit" class="login-btn">登录系统</button>
            </form>
            
            <div class="login-footer">
                <div class="login-footer-links">
                    <span class="login-footer-link">请联系管理员重置密码</span>
                </div>
                
                <div class="init-db">
                    <a href="./">首次部署请访问首页初始化</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.querySelector('.password-toggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.textContent = '👁️‍🗨️';
            } else {
                passwordInput.type = 'password';
                passwordToggle.textContent = '👁️';
            }
        }

        function showMessage(type, title, message) {
            const box = document.getElementById('message-box');
            box.style.display = 'block';
            box.className = `alert ${type === 'success' ? 'alert-success' : 'alert-error'}`;
            box.innerHTML = `
                <div class="alert-content">
                    <div class="alert-title">${title}</div>
                    <div class="alert-message">${message}</div>
                </div>
            `;
        }

        async function handleLogin(event) {
            event.preventDefault();

            const button = document.getElementById('login-btn');
            const originalText = button.innerHTML;
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                showMessage('error', '登录失败', '用户名和密码不能为空');
                return;
            }

            button.innerHTML = '<span class="loading"></span> 登录中...';
            button.disabled = true;
            button.style.cursor = 'not-allowed';
            button.classList.add('opacity-75');

            try {
                const response = await fetch('./admin/auth/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ username, password })
                });
                const payload = await response.json();
                if (!payload.success) {
                    throw new Error(payload.message || '用户名或密码错误');
                }

                window.location.href = 'admin/index.php';
            } catch (error) {
                showMessage('error', '登录失败', error.message);
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
                button.style.cursor = 'pointer';
                button.classList.remove('opacity-75');
            }
        }

        document.getElementById('login-form').addEventListener('submit', handleLogin);

        // 添加输入框动画效果
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.classList.remove('focused');
            });
        });
    </script>
</body>
</html>
