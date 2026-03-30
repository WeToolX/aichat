<?php

/**
 * 项目配置文件
 *
 * 说明：
 * - 配置项优先从项目根目录 `.env` 读取
 * - 如果 `.env` 中没有对应值，则回退到当前默认值
 * - 安装页也会基于 `.env` 自动检查环境并执行初始化
 */
return array(
    'app' => array(
        // 项目名称
        'name' => env_value('APP_NAME', 'aichat-api'),
        // 当前运行环境，例如 production / local
        'env' => env_value('APP_ENV', 'production'),
        // 当前网站访问地址，用于安装页自请求检测伪静态
        'url' => env_value('APP_URL', ''),
        // 项目时区
        'timezone' => env_value('APP_TIMEZONE', 'Asia/Shanghai'),
        // 是否开启调试模式
        'debug' => env_bool('APP_DEBUG', false),
        // Token 有效期，单位秒
        'token_ttl' => (int) env_value('APP_TOKEN_TTL', 86400 * 7),
        // Redis 中保存 token 的前缀
        'token_prefix' => env_value('APP_TOKEN_PREFIX', 'aichat:token:'),
        // 安装完成标记文件
        'install_lock' => BASE_PATH . '/storage/install/installed.lock',
        // 安装状态文件
        'install_state' => BASE_PATH . '/storage/install/state.json',
        // 安装日志文件
        'install_log' => BASE_PATH . '/storage/install/install.log',
    ),
    'database' => array(
        // MySQL 主机地址
        'host' => env_value('DB_HOST', '127.0.0.1'),
        // MySQL 端口
        'port' => (int) env_value('DB_PORT', 3306),
        // 数据库名称
        'dbname' => env_value('DB_DATABASE', 'shujuguanli'),
        // 数据库用户名
        'username' => env_value('DB_USERNAME', 'root'),
        // 数据库密码
        'password' => env_value('DB_PASSWORD', ''),
        // 数据库字符集
        'charset' => env_value('DB_CHARSET', 'utf8mb4'),
    ),
    'redis' => array(
        // Redis 主机地址
        'host' => env_value('REDIS_HOST', '127.0.0.1'),
        // Redis 端口
        'port' => (int) env_value('REDIS_PORT', 6379),
        // Redis 密码，没有则留空
        'password' => env_value('REDIS_PASSWORD', null),
        // Redis 库编号
        'database' => (int) env_value('REDIS_DB', 0),
        // Redis 连接超时时间
        'timeout' => (float) env_value('REDIS_TIMEOUT', 2.5),
    ),
    'deepseek' => array(
        // DeepSeek API 密钥
        'api_key' => env_value('DEEPSEEK_API_KEY', ''),
        // DeepSeek 接口地址
        'api_url' => env_value('DEEPSEEK_API_URL', 'https://api.deepseek.com/v1/chat/completions'),
        // 默认模型名称
        'default_model' => env_value('DEEPSEEK_MODEL', 'deepseek-chat'),
        // 最大返回 token 数
        'max_tokens' => (int) env_value('DEEPSEEK_MAX_TOKENS', 1024),
        // 温度参数
        'temperature' => (float) env_value('DEEPSEEK_TEMPERATURE', 0.7),
    ),
    'system' => array(
        // 后台系统展示名称
        'name' => env_value('SYSTEM_NAME', env_value('APP_NAME', '后台管理系统')),
        // 系统版本号，仅用于展示与管理端配置
        'version' => env_value('SYSTEM_VERSION', '1.0.0'),
        // 上传目录，相对项目根目录
        'upload_path' => env_value('SYSTEM_UPLOAD_PATH', 'uploads/'),
        // 最大上传大小，单位字节
        'max_upload_size' => (int) env_value('SYSTEM_MAX_UPLOAD_SIZE', 20 * 1024 * 1024),
        // 允许上传的扩展名列表
        'allowed_extensions' => env_list('SYSTEM_ALLOWED_EXTENSIONS', array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'wb', 'wb2', 'wbk', 'iec')),
    ),
    'roles' => array(
        // 超级用户角色值
        'super' => 1,
        // 普通用户角色值
        'user' => 2,
    ),
);
