<?php

require_once __DIR__ . '/../includes/admin_shell.php';

$ctx = admin_shell_bootstrap('momo');
$initialState = array(
    'momo_user_id' => isset($_GET['momo_user_id']) ? (int) $_GET['momo_user_id'] : 0,
    'momoid' => isset($_GET['momoid']) ? (string) $_GET['momoid'] : '',
    'send_momoid' => isset($_GET['send_momoid']) ? (string) $_GET['send_momoid'] : '',
);
$initialStateJson = json_encode($initialState, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$extraHead = <<<'HTML'
<style>
        .message-box { display: none; margin-bottom: var(--spacing-md); padding: var(--spacing-md); border-radius: var(--border-radius); font-size: var(--font-size-sm); }
        .message-box.show { display: block; }
        .message-box.success { background: rgba(74, 222, 128, 0.14); color: #166534; }
        .message-box.error { background: rgba(248, 113, 113, 0.14); color: #b91c1c; }

        /* 聊天记录特定样式 */
        .chat-container {
            display: flex;
            flex-direction: column;
            height: 85vh;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            margin-top: 12px;
            transition: all 0.2s ease;
        }
        
        .chat-header {
            padding: 14px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .chat-title {
            font-size: var(--font-size-lg);
            font-weight: var(--font-weight-semibold);
            color: var(--dark-color);
            margin: 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .chat-info {
            font-size: var(--font-size-sm);
            color: var(--gray-color);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .chat-messages {
            flex: 1;
            padding: 14px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
            background: rgba(255, 255, 255, 0.12);
        }
        
        /* 消息时间分组 */
        .message-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 12px;
        }
        
        .message-group-time {
            text-align: center;
            font-size: 12px;
            color: var(--gray-color);
            margin: 2px 0;
            padding: 4px 10px;
            background-color: rgba(255, 255, 255, 0.16);
            border-radius: var(--border-radius);
            align-self: center;
            border: 1px solid rgba(255, 255, 255, 0.22);
        }
        
        .message {
            display: flex;
            max-width: 80%;
            margin-bottom: 4px;
            animation: messageSlideIn 0.24s ease forwards;
        }
        
        @keyframes messageSlideIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .message-sent {
            justify-content: flex-end;
        }
        
        .message-received {
            justify-content: flex-start;
        }
        
        .message-content {
            padding: 10px 12px;
            border-radius: var(--border-radius-lg);
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 20px 32px -28px rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(18px) saturate(145%);
            -webkit-backdrop-filter: blur(18px) saturate(145%);
        }
        
        .message-sent .message-content {
            background: linear-gradient(180deg, rgba(36, 87, 255, 0.78), rgba(36, 87, 255, 0.62));
            color: #fff;
            border-bottom-right-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.14);
        }
        
        .message-received .message-content {
            background: rgba(255, 255, 255, 0.28);
            color: var(--dark-color);
            border-bottom-left-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.26);
        }
        
        .message-time {
            font-size: 12px;
            color: inherit;
            margin-top: 4px;
            text-align: right;
            opacity: 0.72;
        }
        
        .message-received .message-time {
            text-align: left;
        }
        
        .chat-input {
            padding: 14px 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.22);
            position: sticky;
            bottom: 0;
            z-index: 10;
            background: rgba(255, 255, 255, 0.18);
            backdrop-filter: blur(20px) saturate(145%);
            -webkit-backdrop-filter: blur(20px) saturate(145%);
        }
        
        .message-form {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            font-weight: var(--font-weight-medium);
            color: var(--dark-color);
            margin-bottom: 4px;
            display: block;
            font-size: var(--font-size-sm);
        }
        
        .form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid rgba(255, 255, 255, 0.32);
            border-radius: 12px;
            font-size: 14px;
            resize: vertical;
            min-height: 88px;
            transition: var(--transition);
            font-family: inherit;
            background: rgba(255, 255, 255, 0.24);
            backdrop-filter: blur(16px) saturate(140%);
            -webkit-backdrop-filter: blur(16px) saturate(140%);
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: rgba(112, 149, 255, 0.68);
            box-shadow: 0 0 0 3px rgba(36, 87, 255, 0.12);
        }
        
        .form-row {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            flex-wrap: wrap;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .form-row .form-actions {
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: var(--font-weight-medium);
            transition: all 0.2s ease;
            margin-left: 8px;
        }
        
        .status-badge-success {
            background-color: rgba(74, 222, 128, 0.2);
            color: var(--success-color);
            border: 1px solid rgba(74, 222, 128, 0.3);
        }
        
        .status-badge-info {
            background-color: rgba(76, 201, 240, 0.2);
            color: var(--secondary-color);
            border: 1px solid rgba(76, 201, 240, 0.3);
        }
        
        .status-badge-warning {
            background-color: rgba(251, 191, 36, 0.2);
            color: #f59e0b;
            border: 1px solid rgba(251, 191, 36, 0.3);
        }
        
        /* 加载动画 */
        .loading {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255, 255, 255, 0.22);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* 空状态 */
        .empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 320px;
            text-align: center;
            color: var(--gray-color);
            padding: 20px;
        }
        
        .empty-state-icon {
            width: 60px;
            height: 60px;
            margin-bottom: 12px;
            opacity: 0.62;
            color: var(--primary-color);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .empty-state-text {
            font-size: var(--font-size-lg);
            margin-bottom: 6px;
            font-weight: var(--font-weight-medium);
            color: var(--dark-color);
        }
        
        .empty-state-subtext {
            font-size: var(--font-size-md);
            color: var(--gray-dark);
            max-width: 300px;
            line-height: 1.5;
        }
        
        /* 消息状态指示 */
        .message-status {
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
            font-size: var(--font-size-xs);
            color: var(--gray-color);
            margin-top: var(--spacing-xs);
        }
        
        .message-status.sent {
            color: var(--secondary-color);
        }
        
        .message-status.delivered {
            color: var(--success-color);
        }
        
        /* 聊天记录操作栏 */
        .chat-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .action-btn {
            background: none;
            border: none;
            color: var(--gray-color);
            font-size: var(--font-size-md);
            cursor: pointer;
            padding: var(--spacing-sm);
            border-radius: 50%;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background-color: var(--gray-light);
            color: var(--primary-color);
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .chat-container {
                height: 80vh;
                margin-top: 10px;
            }
            
            .chat-header {
                padding: 12px 14px;
            }
            
            .chat-title {
                font-size: var(--font-size-md);
            }
            
            .chat-messages {
                padding: 12px;
            }
            
            .message {
                max-width: 90%;
            }
            
            .form-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .form-row .form-actions {
                flex-direction: row;
                justify-content: space-between;
            }
            
            .btn {
                min-width: auto;
            }
        }
        
        @media (max-width: 480px) {
            .chat-container {
                height: 90vh;
                margin-top: var(--spacing-sm);
            }
            
            .chat-info {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-xs);
            }
            
            .chat-actions {
                gap: var(--spacing-xs);
            }
        }
</style>
HTML;

ob_start();
admin_shell_start($ctx, '后台管理系统 - 聊天记录', '聊天记录', '页面只负责展示和发起请求，聊天数据通过接口读取与写入。', $extraHead);
?>
<div id="message-box" class="message-box"></div>

<div class="mb-4">
    <a id="back-link" href="momo_management.php" class="btn btn-secondary">
        <?php echo admin_shell_icon('arrow-left', 'icon-inline'); ?>
        返回发送陌陌ID列表
    </a>
</div>

<div class="chat-container">
    <div class="chat-header">
        <div>
            <h2 class="chat-title">
                聊天记录
                <span class="status-badge status-badge-info ml-2" id="momoid-badge">陌陌ID: <?php echo admin_shell_escape($initialState['momoid']); ?></span>
                <span class="status-badge status-badge-success ml-2" id="send-badge">发送陌陌ID: <?php echo admin_shell_escape($initialState['send_momoid']); ?></span>
            </h2>
            <div class="chat-info">
                <span id="last-interaction">最后交互: -</span>
                <span id="message-count">消息数: 0</span>
            </div>
        </div>
    </div>

    <div class="chat-messages" id="chat-messages">
        <div class="loading" id="loading"></div>
    </div>

    <div class="chat-input">
        <form id="message-form" class="message-form">
            <div class="form-group">
                <label for="content">消息内容</label>
                <textarea id="content" name="content" placeholder="请输入消息内容" required></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="is_send">发送状态</label>
                    <select id="is_send" name="is_send" class="form-control">
                        <option value="0">我发送</option>
                        <option value="1">对方发送</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="message_time">消息时间</label>
                    <input id="message_time" name="message_time" type="datetime-local" value="<?php echo admin_shell_escape(date('Y-m-d\TH:i')); ?>" class="form-control">
                </div>

                <div class="form-actions">
                    <button id="submit-btn" type="submit" class="btn btn-primary">
                        <?php echo admin_shell_icon('send', 'icon-inline'); ?>
                        发送消息
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
$extraScript = <<<'HTML'
<script>
    (function () {
        const boot = window.ADMIN_BOOTSTRAP || {};
        const token = boot.token || '';
        const initial = __INITIAL_STATE__;
        const state = {
            momoUserId: Number(initial.momo_user_id || 0),
            conversation: null,
            messages: []
        };
        const nodes = {
            message: document.getElementById('message-box'),
            back: document.getElementById('back-link'),
            momoid: document.getElementById('momoid-badge'),
            send: document.getElementById('send-badge'),
            lastInteraction: document.getElementById('last-interaction'),
            messageCount: document.getElementById('message-count'),
            list: document.getElementById('chat-messages'),
            loading: document.getElementById('loading'),
            form: document.getElementById('message-form'),
            content: document.getElementById('content'),
            isSend: document.getElementById('is_send'),
            messageTime: document.getElementById('message_time'),
            submit: document.getElementById('submit-btn')
        };

        async function request(path, options = {}) {
            const config = {
                method: options.method || 'GET',
                headers: { 'X-Token': token, 'Accept': 'application/json' },
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
            nodes.message.className = `message-box show ${type === 'success' ? 'success' : 'error'}`;
            nodes.message.textContent = text;
        }

        function clearMessage() {
            nodes.message.className = 'message-box';
            nodes.message.textContent = '';
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function groupMessagesByDate(messages) {
            return messages.reduce((groups, message) => {
                const date = String(message.message_time || '').split(' ')[0] || '未知日期';
                if (!groups[date]) {
                    groups[date] = [];
                }
                groups[date].push(message);
                return groups;
            }, {});
        }

        function formatGroupDate(dateString) {
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const yesterday = new Date(today.getTime() - 86400000);
            const current = new Date(`${dateString}T00:00:00`);

            if (!Number.isNaN(current.getTime())) {
                const currentValue = current.getTime();
                if (currentValue === today.getTime()) {
                    return '今天';
                }
                if (currentValue === yesterday.getTime()) {
                    return '昨天';
                }
            }

            return dateString;
        }

        function renderConversation() {
            const conversation = state.conversation || {};
            const momoid = conversation.momoid || initial.momoid || '-';
            const sendMomoid = conversation.send_momoid || initial.send_momoid || '-';
            const lastInteraction = conversation.last_interaction_text || '无';
            const count = conversation.message_count ?? state.messages.length;

            nodes.momoid.textContent = `陌陌ID: ${momoid}`;
            nodes.send.textContent = `发送陌陌ID: ${sendMomoid}`;
            nodes.lastInteraction.textContent = `最后交互: ${lastInteraction}`;
            nodes.messageCount.textContent = `消息数: ${count}`;
            nodes.back.href = `momo_detail.php?momoid=${encodeURIComponent(conversation.momoid || initial.momoid || '')}`;
        }

        function renderMessages() {
            nodes.loading.style.display = 'none';

            if (!Array.isArray(state.messages) || state.messages.length === 0) {
                nodes.list.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">${__EMPTY_ICON__}</div>
                        <div class="empty-state-text">暂无聊天记录</div>
                        <div class="empty-state-subtext">开始发送消息吧</div>
                    </div>
                `;
                return;
            }

            const groupedMessages = groupMessagesByDate(state.messages);
            nodes.list.innerHTML = Object.entries(groupedMessages).map(([date, messages]) => `
                <div class="message-group">
                    <div class="message-group-time">${escapeHtml(formatGroupDate(date))}</div>
                    ${messages.map((message) => `
                        <div class="message ${Number(message.is_send) === 0 ? 'message-sent' : 'message-received'}">
                            <div class="message-content">
                                <div>${escapeHtml(message.content)}</div>
                                <div class="message-time">${escapeHtml(message.message_time)}</div>
                            </div>
                        </div>
                    `).join('')}
                </div>
            `).join('');

            nodes.list.scrollTop = nodes.list.scrollHeight;
        }

        async function loadConversation() {
            if (!state.momoUserId) {
                throw new Error('缺少必要参数');
            }
            state.conversation = await request(`../admin/chat_history?momo_user_id=${encodeURIComponent(state.momoUserId)}`);
            renderConversation();
        }

        async function loadMessages() {
            if (!state.momoUserId) {
                throw new Error('缺少必要参数');
            }
            nodes.loading.style.display = 'inline-block';
            state.messages = await request(`../chat_history?momo_user_id=${encodeURIComponent(state.momoUserId)}`);
            renderMessages();
            renderConversation();
        }

        async function submitMessage(event) {
            event.preventDefault();
            clearMessage();

            const content = nodes.content.value.trim();
            if (!content) {
                showMessage('error', '请输入消息内容');
                nodes.content.focus();
                return;
            }

            const original = nodes.submit.innerHTML;
            nodes.submit.disabled = true;
            nodes.submit.innerHTML = '<span>发送中...</span>';

            try {
                await request('../admin/chat_history/send', {
                    method: 'POST',
                    body: {
                        momo_user_id: state.momoUserId,
                        content: content,
                        is_send: Number(nodes.isSend.value || 0),
                        message_time: nodes.messageTime.value
                    }
                });

                nodes.content.value = '';
                await Promise.all([loadConversation(), loadMessages()]);
                showMessage('success', '消息发送成功');
            } catch (error) {
                showMessage('error', error.message);
            } finally {
                nodes.submit.disabled = false;
                nodes.submit.innerHTML = original;
            }
        }

        nodes.form.addEventListener('submit', submitMessage);

        Promise.all([loadConversation(), loadMessages()]).catch((error) => {
            nodes.loading.style.display = 'none';
            showMessage('error', error.message);
            nodes.list.innerHTML = '<div class="empty-state"><div class="empty-state-icon">' + __WARNING_ICON__ + '</div><div class="empty-state-text">加载失败</div><div class="empty-state-subtext">请检查会话参数或接口返回。</div></div>';
        });
    }());
</script>
HTML;
$extraScript = str_replace('__INITIAL_STATE__', $initialStateJson, $extraScript);
$extraScript = str_replace('__EMPTY_ICON__', json_encode(admin_shell_icon('empty', 'icon-inline'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $extraScript);
$extraScript = str_replace('__WARNING_ICON__', json_encode(admin_shell_icon('warning', 'icon-inline'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $extraScript);

admin_shell_end($ctx, $extraScript);
echo ob_get_clean();
