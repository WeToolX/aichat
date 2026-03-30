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
            background-color: #f8f9fa;
            border-radius: var(--border-radius-lg);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            margin-top: var(--spacing-lg);
            transition: all 0.3s ease;
        }
        
        .chat-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--border-color);
            background-color: #fff;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 10;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
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
            margin-top: var(--spacing-xs);
            display: flex;
            align-items: center;
            gap: var(--spacing-md);
        }
        
        .chat-messages {
            flex: 1;
            padding: var(--spacing-lg);
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
            background-color: #f0f2f5;
        }
        
        /* 消息时间分组 */
        .message-group {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }
        
        .message-group-time {
            text-align: center;
            font-size: var(--font-size-xs);
            color: var(--gray-color);
            margin: var(--spacing-sm) 0;
            padding: var(--spacing-xs) var(--spacing-sm);
            background-color: rgba(0, 0, 0, 0.05);
            border-radius: var(--border-radius);
            align-self: center;
        }
        
        .message {
            display: flex;
            max-width: 80%;
            margin-bottom: var(--spacing-sm);
            animation: messageSlideIn 0.3s ease forwards;
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
            padding: var(--spacing-md);
            border-radius: var(--border-radius-lg);
            position: relative;
            word-wrap: break-word;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .message-sent .message-content {
            background-color: var(--primary-color);
            color: #fff;
            border-bottom-right-radius: 4px;
        }
        
        .message-received .message-content {
            background-color: #fff;
            color: var(--dark-color);
            border-bottom-left-radius: 4px;
            border: 1px solid var(--border-color);
        }
        
        .message-time {
            font-size: var(--font-size-xs);
            color: var(--gray-color);
            margin-top: var(--spacing-xs);
            text-align: right;
            opacity: 0.7;
        }
        
        .message-received .message-time {
            text-align: left;
        }
        
        .chat-input {
            padding: var(--spacing-lg);
            border-top: 1px solid var(--border-color);
            background-color: #fff;
            position: sticky;
            bottom: 0;
            z-index: 10;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
        }
        
        .message-form {
            display: flex;
            flex-direction: column;
            gap: var(--spacing-md);
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            font-weight: var(--font-weight-medium);
            color: var(--dark-color);
            margin-bottom: var(--spacing-sm);
            display: block;
            font-size: var(--font-size-sm);
        }
        
        .form-group textarea {
            width: 100%;
            padding: var(--spacing-md);
            border: 2px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            font-size: var(--font-size-md);
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
            font-family: inherit;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .form-row {
            display: flex;
            gap: var(--spacing-md);
            align-items: flex-end;
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
        
        .btn {
            padding: var(--spacing-md) var(--spacing-lg);
            border: none;
            border-radius: var(--border-radius-lg);
            font-size: var(--font-size-md);
            font-weight: var(--font-weight-medium);
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-xs);
            min-width: 100px;
            justify-content: center;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            color: #fff;
            box-shadow: 0 4px 12px rgba(67, 97, 238, 0.3);
        }
        
        .btn-primary:hover {
            background-color: #5b7bfd;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(67, 97, 238, 0.4);
        }
        
        .btn-secondary {
            background-color: var(--secondary-color);
            color: #fff;
            box-shadow: 0 4px 12px rgba(76, 201, 240, 0.3);
        }
        
        .btn-secondary:hover {
            background-color: #38bdf8;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(76, 201, 240, 0.4);
        }
        
        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .status-badge {
            display: inline-block;
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: 20px;
            font-size: var(--font-size-sm);
            font-weight: var(--font-weight-medium);
            transition: all 0.3s ease;
            margin-left: var(--spacing-sm);
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
            border: 3px solid rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
            margin: var(--spacing-xl) auto;
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
            height: 400px;
            text-align: center;
            color: var(--gray-color);
            padding: var(--spacing-xl);
        }
        
        .empty-state-icon {
            font-size: 80px;
            margin-bottom: var(--spacing-lg);
            opacity: 0.3;
            animation: bounce 2s ease-in-out infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-20px);
            }
            60% {
                transform: translateY(-10px);
            }
        }
        
        .empty-state-text {
            font-size: var(--font-size-lg);
            margin-bottom: var(--spacing-sm);
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
            gap: var(--spacing-sm);
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
                margin-top: var(--spacing-md);
                border-radius: var(--border-radius-md);
            }
            
            .chat-header {
                padding: var(--spacing-md);
            }
            
            .chat-title {
                font-size: var(--font-size-md);
            }
            
            .chat-messages {
                padding: var(--spacing-md);
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
                padding: var(--spacing-sm) var(--spacing-md);
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
