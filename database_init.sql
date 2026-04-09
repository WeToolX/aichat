-- 数据库初始化脚本
-- 创建所有必要的表结构
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- 删除现有表（如果存在）
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS keywords;
DROP TABLE IF EXISTS scripts;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS momo_users;
DROP TABLE IF EXISTS function_settings;
DROP TABLE IF EXISTS users;

-- 创建用户表
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    role TINYINT(1) NOT NULL DEFAULT 0, -- 0=普通用户, 1=管理员
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建功能设置表
CREATE TABLE function_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    auto_login TINYINT(1) NOT NULL DEFAULT 0,
    add_friend TINYINT(1) NOT NULL DEFAULT 0,
    only_send_to_friends TINYINT(1) NOT NULL DEFAULT 0,
    nearby_like TINYINT(1) NOT NULL DEFAULT 0,
    nearby_like_count INT NOT NULL DEFAULT 10,
    nearby_like_interval INT NOT NULL DEFAULT 3,
    nearby_like_scroll INT NOT NULL DEFAULT 5,
    nearby_like_interval_min INT NOT NULL DEFAULT 1,
    nearby_like_interval_max INT NOT NULL DEFAULT 5,
    nearby_like_scroll_min INT NOT NULL DEFAULT 1,
    nearby_like_scroll_max INT NOT NULL DEFAULT 10,
    feed_like TINYINT(1) NOT NULL DEFAULT 0,
    feed_like_count INT NOT NULL DEFAULT 10,
    feed_like_interval INT NOT NULL DEFAULT 3,
    feed_like_scroll INT NOT NULL DEFAULT 5,
    feed_like_interval_min INT NOT NULL DEFAULT 1,
    feed_like_interval_max INT NOT NULL DEFAULT 5,
    feed_like_scroll_min INT NOT NULL DEFAULT 1,
    feed_like_scroll_max INT NOT NULL DEFAULT 10,
    click_delay_min INT NOT NULL DEFAULT 500,
    click_delay_max INT NOT NULL DEFAULT 1000,
    send_delay_min INT NOT NULL DEFAULT 1000,
    send_delay_max INT NOT NULL DEFAULT 2000,
    reply_delay_min INT NOT NULL DEFAULT 1000,
    reply_delay_max INT NOT NULL DEFAULT 3000,
    guide_after_messages INT NOT NULL DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 创建陌陌用户表
CREATE TABLE momo_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 1,
    momoid VARCHAR(20) NOT NULL,
    send_momoid VARCHAR(20) NOT NULL,
    chat_id VARCHAR(100) NOT NULL,
    is_friend TINYINT(1) NOT NULL DEFAULT 0,
    is_online TINYINT(1) NOT NULL DEFAULT 0,
    is_send TINYINT(1) NOT NULL DEFAULT 0,
    send_num INT NOT NULL DEFAULT 0,
    is_block TINYINT(1) NOT NULL DEFAULT 0,
    last_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_momoid_user (user_id, momoid, send_momoid)
);

-- 创建聊天消息表
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    momo_user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_self TINYINT(1) NOT NULL,
    timestamp BIGINT NOT NULL,
    m_type TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (momo_user_id) REFERENCES momo_users(id) ON DELETE CASCADE
);

-- 创建话术表
CREATE TABLE scripts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_script (user_id, name),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 创建关键词表
CREATE TABLE keywords (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    keyword VARCHAR(100) NOT NULL,
    reply TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_keyword (user_id, keyword),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 创建文件表
CREATE TABLE files (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    is_downloaded TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 插入默认管理员用户（密码：password）
INSERT INTO users (username, password, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 1);

-- 为默认管理员创建功能设置
INSERT INTO function_settings (user_id) VALUES (1);

-- 为默认管理员创建默认话术
INSERT INTO scripts (user_id, name, content) VALUES
(1, 'AI设置', '你叫小暖，我是一个家庭主妇，30岁性格温柔体贴，离婚两年了，没有小孩，现在是单身状态，平时能积极跟别人聊天，聊天的时候非常幽默，偶尔还会说一些土味情话。\n\n你的回复规则:\n1.回复简洁明了，像真人聊天一样自然\n2.只回复一到两句话，保持自然对话风格\n3.热情问答，有问有答，了解对方的基本情况\n4.绝对不要提及QQ和微信字样\n5.除了年龄外不要出现英文和数字\n6.回复要真实自然，像一个女孩真实的聊天方式\n7.主动或根据对方情况提出问题了解对方\n8.语气要温柔体贴\n9.如果对方会问你是哪里的，一定要回答，我是本地的\n10.如果对方添加你为好友或者提示消息过于打扰，就随便发送你好一类的打招呼'),
(1, '引导话术', '聊的挺愉快的，换个地方？');

-- 插入默认关键词
INSERT INTO keywords (user_id, keyword, reply) VALUES
(1, '你好', '你好呀！很高兴认识你~'),
(1, '美女', '你好呀，有什么可以帮到你的吗？'),
(1, '在吗', '在呢，有什么事吗？');

-- 创建索引以提高查询性能
CREATE INDEX idx_momo_users_momoid ON momo_users(momoid);
CREATE INDEX idx_momo_users_send_momoid ON momo_users(send_momoid);
CREATE INDEX idx_momo_users_is_online ON momo_users(is_online);
CREATE INDEX idx_chat_messages_momo_user_id ON chat_messages(momo_user_id);
CREATE INDEX idx_chat_messages_timestamp ON chat_messages(timestamp);
CREATE INDEX idx_scripts_user_id ON scripts(user_id);
CREATE INDEX idx_keywords_user_id ON keywords(user_id);
CREATE INDEX idx_files_user_id ON files(user_id);
CREATE INDEX idx_files_is_downloaded ON files(is_downloaded);

-- 优化表结构
OPTIMIZE TABLE users;
OPTIMIZE TABLE function_settings;
OPTIMIZE TABLE momo_users;
OPTIMIZE TABLE chat_messages;
OPTIMIZE TABLE scripts;
OPTIMIZE TABLE keywords;
OPTIMIZE TABLE files;

-- 完成初始化
SELECT '数据库初始化完成' AS message;
