<?php

/** 首次安装初始化器，负责环境检查、SQL 导入与安装页展示。 */
class Installer
{
    /** 判断当前请求是否应进入初始化流程。 */
    public static function shouldHandle(Request $request)
    {
        if (static::isInstalled()) {
            return false;
        }

        return $request->path() !== '/ping';
    }

    /** 处理安装页、状态查询与安装执行请求。 */
    public static function handle(Request $request)
    {
        try {
            static::ensureDirectories();

            $path = $request->path();
            if ($path === '/install/status') {
                Response::json(static::buildStatusPayload($request), 200);
            }

            if ($path === '/install/save-config') {
                Response::json(static::saveConfig($request), 200);
            }

            if ($path === '/install/run') {
                Response::json(static::run($request), 200);
            }

            static::renderPage($request);
        } catch (Throwable $e) {
            static::handleRequestException($request, $e);
        }
    }

    /** 执行安装流程。 */
    public static function run(Request $request)
    {
        static::ensureDirectories();

        if (static::isInstalled()) {
            return static::buildStatusPayload($request, static::saveState(array(
                'installed' => true,
                'running' => false,
                'progress' => 100,
                'status' => 'success',
                'title' => '初始化已完成',
                'logs' => static::readState()['logs'] ?? array(),
            )));
        }

        if (is_file(static::runningFile())) {
            return static::readState();
        }

        touch(static::runningFile());
        @set_time_limit(0);

        try {
            static::bootstrapState();
            static::appendLog('开始执行初始化流程');

            static::ensureEnvFile($request);
            $env = static::readEnvFile();
            static::ensureAppUrl($request, $env);
            $env = static::readEnvFile();

            static::performCheck('php_version', '检查 PHP 版本', 10, function () {
                static::assertPhpVersion();
            });

            static::performCheck('php_extensions', '检查 PHP 扩展', 20, function () {
                static::assertExtensions();
            });

            static::performCheck('storage', '检查目录写权限', 30, function () {
                static::assertWritable(BASE_PATH . '/storage');
                static::assertWritable(dirname(static::stateFile()));
            });

            /** @var PDO|null $serverPdo */
            $serverPdo = null;
            static::performCheck('mysql_server', '检查 MySQL 服务与账号', 40, function () use ($env, &$serverPdo) {
                $serverPdo = static::connectMysqlServer($env, true);
            });

            static::performCheck('mysql_database', '检查数据库是否存在', 50, function () use (&$serverPdo, $env) {
                if (!$serverPdo instanceof PDO) {
                    throw new RuntimeException('MySQL 服务连接对象未初始化');
                }

                static::ensureDatabase($serverPdo, $env);
                static::appendLog('数据库检查完成：' . $env['DB_DATABASE']);
            });

            $dbPdo = null;
            static::performCheck('mysql_init', '检查数据库连接并导入初始化 SQL', 60, function () use ($env, &$dbPdo) {
                $dbPdo = static::connectMysqlDatabase($env, true);
                static::initializeDatabase($dbPdo);
            });

            static::performCheck('redis', '检查 Redis 服务与密码', 75, function () use ($env) {
                static::assertRedis($env);
            });

            static::performCheck('rewrite', '检查伪静态与网站自请求', 90, function () use ($env) {
                static::assertSelfRequest($env['APP_URL']);
            });

            file_put_contents(static::lockFile(), date('Y-m-d H:i:s'));
            static::appendLog('安装完成，已写入安装锁文件');

            return static::buildStatusPayload($request, static::saveState(array(
                'installed' => true,
                'running' => false,
                'progress' => 100,
                'status' => 'success',
                'title' => '初始化完成',
                'logs' => static::readState()['logs'] ?? array(),
            )));
        } catch (Throwable $e) {
            static::appendLog('初始化失败：' . $e->getMessage());
            return static::buildStatusPayload($request, static::saveState(array(
                'installed' => false,
                'running' => false,
                'progress' => static::readState()['progress'] ?? 0,
                'status' => 'waiting',
                'title' => '等待环境就绪',
                'error' => $e->getMessage(),
                'logs' => static::readState()['logs'] ?? array(),
            )));
        } finally {
            if (is_file(static::runningFile())) {
                unlink(static::runningFile());
            }
        }
    }

    /** 渲染安装页。 */
    protected static function renderPage(Request $request)
    {
        $state = static::readState();
        $currentUrl = static::inferAppUrl($request);

        header('Content-Type: text/html; charset=utf-8');
        $title = htmlspecialchars($state['title'] ?? '准备中', ENT_QUOTES, 'UTF-8');
        $currentUrl = htmlspecialchars($currentUrl, ENT_QUOTES, 'UTF-8');
        $envPath = htmlspecialchars(BASE_PATH . '/.env', ENT_QUOTES, 'UTF-8');
        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>网站初始化</title>
<style>
body{margin:0;font-family:-apple-system,BlinkMacSystemFont,"PingFang SC","Microsoft YaHei",sans-serif;background:linear-gradient(180deg,#f1f5f9 0%,#e2e8f0 100%);color:#0f172a}
.wrap{max-width:1280px;margin:24px auto;padding:0 16px}
.hero,.panel{background:rgba(255,255,255,.92);backdrop-filter:blur(8px);border-radius:22px;box-shadow:0 20px 50px rgba(15,23,42,.08);border:1px solid rgba(148,163,184,.18)}
.hero{padding:22px}
.hero h1{margin:0 0 12px;font-size:32px}
.hero p{margin:0;color:#475569;max-width:900px}
.meta{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px;margin-top:14px}
.meta-card{padding:12px 14px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}
.meta-label{font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.06em}
.meta-value{margin-top:6px;font-size:15px;word-break:break-all}
.layout{display:grid;grid-template-columns:1fr;gap:14px;margin-top:14px}
.stack{display:grid;gap:14px}
.panel{padding:16px}
.panel-head{display:flex;justify-content:space-between;gap:10px;align-items:center;margin-bottom:10px}
.panel-head h2{margin:0;font-size:22px}
.panel-tip{margin:0;color:#64748b;font-size:14px}
.status{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.badge{display:inline-flex;align-items:center;justify-content:center;padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;line-height:1}
.badge.success{background:#dcfce7;color:#166534}
.badge.waiting{background:#fef3c7;color:#92400e}
.badge.running{background:#dbeafe;color:#1d4ed8}
.badge.error{background:#fee2e2;color:#b91c1c}
.badge.warning{background:#ffedd5;color:#9a3412}
.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;margin-top:12px}
.summary-card{padding:10px 12px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}
.summary-card strong{display:block;font-size:24px}
.summary-card span{font-size:13px;color:#64748b}
.progress{height:12px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:12px}
.progress-bar{height:100%;width:0;background:linear-gradient(90deg,#0f766e,#14b8a6);transition:width .3s ease}
.env-grid{display:grid;gap:12px}
.env-section{border:1px solid #e2e8f0;border-radius:16px;padding:14px;background:#fcfdff}
.env-section h3{margin:0 0 4px;font-size:17px}
.env-section p{margin:0 0 10px;color:#64748b;font-size:12px}
.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.field{display:grid;gap:6px;padding:10px 12px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}
.field label{font-weight:600;font-size:14px}
.field input,.field select{width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:10px;padding:8px 10px;font-size:13px;line-height:1.35;background:#fff;color:#0f172a}
.field input:focus,.field select:focus{outline:none;border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12)}
.field-top{display:flex;justify-content:space-between;gap:8px;align-items:center}
.field-tip{font-size:11px;color:#64748b;white-space:pre-wrap;word-break:break-word}
.checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.check{background:#f8fafc;border-radius:14px;padding:10px 12px;border:1px solid #e2e8f0}
.check-head{display:flex;justify-content:space-between;gap:10px;align-items:center}
.check-name{font-weight:600}
.check-tip{margin-top:6px;color:#64748b;font-size:12px;white-space:pre-wrap;word-break:break-word}
.error{color:#b91c1c;background:#fee2e2;padding:10px 12px;border-radius:12px;display:none;white-space:pre-wrap;word-break:break-word}
.logs{background:#0f172a;color:#e2e8f0;border-radius:16px;padding:14px;height:380px;overflow:auto;white-space:pre-wrap;font-family:Menlo,Monaco,monospace;font-size:12px}
.actions{display:flex;gap:8px;flex-wrap:wrap}
button{border:0;background:#0f766e;color:#fff;padding:10px 14px;border-radius:10px;cursor:pointer;font-size:13px}
button.secondary{background:#334155}
button.light{background:#e2e8f0;color:#0f172a}
button:disabled{opacity:.6;cursor:not-allowed}
.muted{color:#64748b;font-size:12px}
@media (max-width: 1100px){.layout{grid-template-columns:1fr}.meta,.summary,.checks,.field-grid{grid-template-columns:1fr}}
</style>
</head>
<body>
<div class="wrap">
  <div class="hero">
    <h1>网站初始化向导</h1>
    <p>这个页面会直接展示当前 .env 配置、逐项校验结果、环境检测结果和安装日志。先把配置修正到通过，再手动启动初始化，避免只看到笼统的“等待初始化”。</p>
    <div class="meta">
      <div class="meta-card"><div class="meta-label">当前访问地址</div><div id="current-url" class="meta-value">__CURRENT_URL__</div></div>
      <div class="meta-card"><div class="meta-label">.env 文件位置</div><div id="env-path" class="meta-value">__ENV_PATH__</div></div>
      <div class="meta-card"><div class="meta-label">当前阶段</div><div class="meta-value"><span id="badge" class="badge waiting">等待中</span> <strong id="title">__TITLE__</strong></div></div>
    </div>
    <div class="summary" id="summary"></div>
    <div class="progress"><div id="progress-bar" class="progress-bar"></div></div>
  </div>

  <div class="layout">
    <div class="stack">
      <div class="panel">
        <div class="panel-head">
          <div>
            <h2>配置面板</h2>
            <p class="panel-tip">所有安装相关配置都可以直接在这里修改并写入 .env。</p>
          </div>
          <div class="actions">
            <button type="button" onclick="saveConfig()">保存配置</button>
            <button type="button" class="light" onclick="fillCurrentUrl()">APP_URL 填当前地址</button>
          </div>
        </div>
        <div id="save-message" class="muted"></div>
        <div id="error" class="error"></div>
        <form id="config-form" class="env-grid"></form>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h2>环境检查</h2>
            <p class="panel-tip">这里展示实际环境连通性与安装条件，不只是配置文件有没有填。</p>
          </div>
          <div class="actions">
            <button type="button" class="secondary" onclick="refreshStatus(true)">重新检测</button>
            <button type="button" onclick="runInstaller()">开始初始化</button>
          </div>
        </div>
        <div id="checks" class="checks"></div>
      </div>

      <div class="panel">
        <div class="panel-head">
          <div>
            <h2>运行日志</h2>
            <p class="panel-tip">初始化和检测过程中的关键输出会持续追加在这里。</p>
          </div>
        </div>
        <div id="logs" class="logs"></div>
      </div>
    </div>
  </div>
</div>

<script>
let completed = false;
let formDirty = false;
let formHydrated = false;
let pollTimer = null;
let lastState = null;

function escapeHtml(value) {
  return String(value == null ? "" : value)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

async function fetchJson(url, options) {
  const res = await fetch(url, options || {});
  const data = await res.json();
  if (!res.ok) {
    throw new Error(data.message || "请求失败");
  }
  return data;
}

function statusLabel(status) {
  if (status === "success") return "正常";
  if (status === "error") return "异常";
  if (status === "warning") return "待处理";
  if (status === "running") return "执行中";
  return "等待中";
}

function renderSummary(summary) {
  const items = [
    {label: "正常项", value: summary.success || 0},
    {label: "待处理项", value: summary.warning || 0},
    {label: "异常项", value: summary.error || 0},
    {label: "已完成", value: summary.installed ? 1 : 0}
  ];
  document.getElementById("summary").innerHTML = items.map((item) => `
    <div class="summary-card">
      <strong>${item.value}</strong>
      <span>${item.label}</span>
    </div>
  `).join("");
}

function renderChecks(checks) {
  const items = Object.keys(checks || {}).map((key) => {
    const item = checks[key] || {};
    const status = item.status || "waiting";
    return `
      <div class="check">
        <div class="check-head">
          <span class="check-name">${escapeHtml(item.title || key)}</span>
          <span class="badge ${escapeHtml(status)}">${statusLabel(status)}</span>
        </div>
        <div class="check-tip">${escapeHtml(item.message || "等待检测")}</div>
      </div>
    `;
  });
  document.getElementById("checks").innerHTML = items.join("");
}

function renderConfig(sections, force) {
  if (formDirty && !force) {
    return;
  }

  const html = (sections || []).map((section) => `
    <div class="env-section">
      <h3>${escapeHtml(section.title || "")}</h3>
      <p>${escapeHtml(section.description || "")}</p>
      <div class="field-grid">
        ${(section.fields || []).map((field) => {
          const status = field.status || "waiting";
          const input = field.options
            ? `<select name="${escapeHtml(field.key)}">${field.options.map((option) => `<option value="${escapeHtml(option.value)}" ${String(option.value) === String(field.value) ? "selected" : ""}>${escapeHtml(option.label)}</option>`).join("")}</select>`
            : `<input type="${escapeHtml(field.type || "text")}" name="${escapeHtml(field.key)}" value="${escapeHtml(field.value || "")}" placeholder="${escapeHtml(field.placeholder || "")}" />`;
          return `
            <div class="field">
              <div class="field-top">
                <label for="${escapeHtml(field.key)}">${escapeHtml(field.label || field.key)}</label>
                <span class="badge ${escapeHtml(status)}">${statusLabel(status)}</span>
              </div>
              ${input}
              <div class="field-tip">${escapeHtml(field.message || "")}</div>
            </div>
          `;
        }).join("")}
      </div>
    </div>
  `).join("");

  const form = document.getElementById("config-form");
  form.innerHTML = html;
  Array.from(form.elements).forEach((element) => {
    element.addEventListener("input", () => {
      formDirty = true;
      document.getElementById("save-message").textContent = "表单已修改，记得先保存配置。";
    });
    element.addEventListener("change", () => {
      formDirty = true;
      document.getElementById("save-message").textContent = "表单已修改，记得先保存配置。";
    });
  });
  formHydrated = true;
  formDirty = false;
}

function render(state, forceConfig) {
  lastState = state;
  document.getElementById("title").textContent = state.title || "准备中";
  document.getElementById("progress-bar").style.width = (state.progress || 0) + "%";
  const badge = document.getElementById("badge");
  const badgeStatus = state.status || "waiting";
  badge.className = "badge " + badgeStatus;
  badge.textContent = state.installed ? "已完成" : (state.running ? "执行中" : statusLabel(badgeStatus));

  renderSummary(state.summary || {});
  renderChecks(state.checks || {});
  renderConfig(state.config_sections || [], forceConfig || !formHydrated);

  document.getElementById("current-url").textContent = state.current_url || "";
  document.getElementById("env-path").textContent = state.env_path || "";

  const logs = Array.isArray(state.logs) ? state.logs.join("\n") : "";
  document.getElementById("logs").textContent = logs || "等待开始...";

  const error = document.getElementById("error");
  if (state.error) {
    error.style.display = "block";
    error.textContent = state.error;
  } else {
    error.style.display = "none";
    error.textContent = "";
  }

  if (state.installed && !completed) {
    completed = true;
    document.getElementById("save-message").textContent = "初始化已完成。";
  }
}

function collectConfig() {
  const form = document.getElementById("config-form");
  const data = {};
  Array.from(form.elements).forEach((element) => {
    if (!element.name) return;
    data[element.name] = element.value;
  });
  return data;
}

async function refreshStatus(forceConfig) {
  const state = await fetchJson("/install/status?_=" + Date.now());
  render(state, forceConfig);
}

async function saveConfig(silent) {
  const payload = {config: collectConfig()};
  const state = await fetchJson("/install/save-config", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: JSON.stringify(payload)
  });
  render(state, true);
  formDirty = false;
  document.getElementById("save-message").textContent = silent ? "配置已同步。" : "配置已保存到 .env。";
  return state;
}

async function runInstaller() {
  if (completed) {
    return;
  }

  if (formDirty) {
    await saveConfig(true);
  }

  const state = await fetchJson("/install/run", {
    method: "POST",
    headers: {"Content-Type": "application/json"},
    body: "{}"
  });
  render(state, false);
}

function fillCurrentUrl() {
  if (!lastState) {
    return;
  }
  const input = document.querySelector('[name="APP_URL"]');
  if (!input) {
    return;
  }
  input.value = lastState.current_url || "";
  formDirty = true;
  document.getElementById("save-message").textContent = "已填入当前访问地址，保存后生效。";
}

async function boot() {
  await refreshStatus(true);
  pollTimer = setInterval(async () => {
    try {
      await refreshStatus(false);
    } catch (error) {
    }
  }, 3000);
}

boot();
</script>
</body>
</html>
HTML;
        echo strtr($html, array(
            '__CURRENT_URL__' => $currentUrl,
            '__ENV_PATH__' => $envPath,
            '__TITLE__' => $title,
        ));
        exit;
    }

    /** 安装入口异常兜底，避免接口直接返回裸 500。 */
    protected static function handleRequestException(Request $request, Throwable $e)
    {
        static::safeRecordException($e);

        $path = $request->path();
        $statusCode = (int) $e->getCode();
        if ($statusCode < 400 || $statusCode >= 600) {
            $statusCode = 500;
        }

        if (in_array($path, array('/install/status', '/install/save-config', '/install/run'), true)) {
            Response::json(array(
                'success' => false,
                'code' => $statusCode,
                'data' => array(
                    'exception' => get_class($e),
                    'state' => static::safeFallbackStatus($request),
                ),
                'message' => $e->getMessage(),
            ), $statusCode);
        }

        ErrorHandler::handleException($e);
    }

    /** 检查 PHP 版本。 */
    protected static function assertPhpVersion()
    {
        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            throw new RuntimeException('PHP 版本过低，当前为 ' . PHP_VERSION . '，至少需要 7.4');
        }

        static::appendLog('PHP 版本检查通过：' . PHP_VERSION);
    }

    /** 检查必需扩展。 */
    protected static function assertExtensions()
    {
        $required = array('pdo', 'pdo_mysql', 'redis', 'curl', 'json', 'zlib');
        $missing = array();
        foreach ($required as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }

        if (!empty($missing)) {
            throw new RuntimeException('缺少 PHP 扩展：' . implode(', ', $missing));
        }

        static::appendLog('PHP 扩展检查通过');
    }

    /** 检查目录可写。 */
    protected static function assertWritable($path)
    {
        if (!is_dir($path) && !mkdir($path, 0777, true)) {
            throw new RuntimeException('目录无法创建：' . $path);
        }
        if (!is_writable($path)) {
            throw new RuntimeException('目录不可写：' . $path);
        }

        static::appendLog('目录可写：' . $path);
    }

    /** 连接 MySQL 服务但不指定数据库。 */
    protected static function connectMysqlServer(array $env, $withLog = false)
    {
        try {
            $pdo = new PDO(static::mysqlServerDsn($env), $env['DB_USERNAME'], $env['DB_PASSWORD'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ));
        } catch (PDOException $e) {
            throw new RuntimeException(static::normalizeMysqlException($e, $env));
        }

        if ($withLog) {
            static::appendLog('MySQL 服务连接成功');
        }

        return $pdo;
    }

    /** 连接指定数据库。 */
    protected static function connectMysqlDatabase(array $env, $withLog = false)
    {
        try {
            $pdo = new PDO(static::mysqlDatabaseDsn($env), $env['DB_USERNAME'], $env['DB_PASSWORD'], array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            ));
        } catch (PDOException $e) {
            throw new RuntimeException(static::normalizeMysqlException($e, $env));
        }

        if ($withLog) {
            static::appendLog('数据库连接成功：' . $env['DB_DATABASE']);
        }
        return $pdo;
    }

    /** 数据库不存在时自动创建。 */
    protected static function ensureDatabase(PDO $pdo, array $env)
    {
        $databaseName = str_replace('`', '``', $env['DB_DATABASE']);
        $charset = preg_replace('/[^a-zA-Z0-9_]/', '', $env['DB_CHARSET']);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $databaseName . '` CHARACTER SET ' . $charset . ' COLLATE ' . $charset . '_general_ci');
    }

    /** 执行单行检测查询并确保游标关闭。 */
    protected static function fetchProbe(PDO $pdo, $sql)
    {
        $stmt = $pdo->query($sql);
        if (!$stmt instanceof PDOStatement) {
            return false;
        }

        try {
            return $stmt->fetch();
        } finally {
            $stmt->closeCursor();
        }
    }

    /** 自动执行 SQL 初始化文件。 */
    protected static function initializeDatabase(PDO $pdo)
    {
        $exists = static::fetchProbe($pdo, "SHOW TABLES LIKE 'users'");
        if ($exists) {
            static::appendLog('检测到 users 表已存在，跳过 SQL 初始化');
            return;
        }

        $sqlFile = BASE_PATH . '/database_init.sql';
        if (!is_file($sqlFile)) {
            throw new RuntimeException('未找到 SQL 初始化文件：' . $sqlFile);
        }

        $sql = file_get_contents($sqlFile);
        if ($sql === false) {
            throw new RuntimeException('SQL 初始化文件读取失败');
        }

        $sql = preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = preg_replace('/^\s*#.*$/m', '', $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        $total = 0;
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }

            $pdo->exec($statement);
            $total++;
        }

        static::appendLog('SQL 初始化完成，共执行 ' . $total . ' 条语句');
    }

    /** 检查 Redis 连接、密码与可用性。 */
    protected static function assertRedis(array $env)
    {
        $redis = new Redis();
        $connected = $redis->connect($env['REDIS_HOST'], (int) $env['REDIS_PORT'], (float) $env['REDIS_TIMEOUT']);
        if (!$connected) {
            throw new RuntimeException('Redis 连接失败');
        }

        if ($env['REDIS_PASSWORD'] !== '') {
            if (!$redis->auth($env['REDIS_PASSWORD'])) {
                throw new RuntimeException('Redis 密码错误');
            }
        }

        if (isset($env['REDIS_DB'])) {
            $redis->select((int) $env['REDIS_DB']);
        }

        if (!static::isRedisPingSuccessful($redis->ping())) {
            throw new RuntimeException('Redis Ping 失败');
        }

        static::appendLog('Redis 连接检查通过');
    }

    /** 通过 HTTP 自请求检测伪静态与站点访问。 */
    protected static function assertSelfRequest($appUrl)
    {
        if ($appUrl === '') {
            throw new RuntimeException('APP_URL 未配置，无法执行站点自请求检测');
        }

        $target = rtrim($appUrl, '/') . '/ping?installer_probe=1&_=' . time();
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $target);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new RuntimeException('站点自请求失败：' . $error);
        }

        if ($httpCode === 404) {
            throw new RuntimeException('站点自请求返回 404，请检查伪静态配置');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || empty($decoded['success'])) {
            throw new RuntimeException('站点自请求返回异常：' . $response);
        }

        static::appendLog('站点自请求检测通过：' . $target);
    }

    /** 初始化状态文件。 */
    protected static function bootstrapState()
    {
        static::saveState(array(
            'installed' => false,
            'running' => true,
            'progress' => 0,
            'status' => 'running',
            'title' => '开始初始化',
            'error' => '',
            'logs' => array(),
            'checks' => static::defaultChecks(),
            'updated_at' => date('Y-m-d H:i:s'),
        ));
    }

    /** 设置进度与标题。 */
    protected static function setProgress($progress, $title)
    {
        $state = static::readState();
        $state['running'] = true;
        $state['status'] = 'running';
        $state['progress'] = (int) $progress;
        $state['title'] = $title;
        static::saveState($state);
        static::appendLog($title);
    }

    /** 追加一条安装日志。 */
    protected static function appendLog($message)
    {
        $state = static::readState();
        $logs = is_array($state['logs'] ?? null) ? $state['logs'] : array();
        $logs[] = '[' . date('H:i:s') . '] ' . $message;
        $logs = array_slice($logs, -200);
        $state['logs'] = $logs;
        $state['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents(static::logFile(), end($logs) . PHP_EOL, FILE_APPEND | LOCK_EX);
        static::saveState($state);
    }

    /** 保存安装状态。 */
    protected static function saveState(array $state)
    {
        $state['updated_at'] = date('Y-m-d H:i:s');
        file_put_contents(static::stateFile(), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        return $state;
    }

    /** 读取安装状态。 */
    protected static function readState()
    {
        if (!is_file(static::stateFile())) {
            return array(
                'installed' => false,
                'running' => false,
                'progress' => 0,
                'status' => 'waiting',
                'title' => '等待初始化',
                'error' => '',
                'logs' => array(),
                'checks' => static::defaultChecks(),
                'updated_at' => date('Y-m-d H:i:s'),
            );
        }

        $content = file_get_contents(static::stateFile());
        $decoded = json_decode((string) $content, true);
        return is_array($decoded) ? $decoded : array();
    }

    /** 若不存在 .env，则根据模板自动生成。 */
    protected static function ensureEnvFile(Request $request)
    {
        $envPath = BASE_PATH . '/.env';
        if (is_file($envPath)) {
            return;
        }

        $example = BASE_PATH . '/.env.example';
        if (!is_file($example)) {
            throw new RuntimeException('.env.example 不存在，无法自动生成 .env');
        }

        $content = file_get_contents($example);
        if ($content === false) {
            throw new RuntimeException('.env.example 读取失败');
        }

        $currentUrl = static::inferAppUrl($request);
        $content = preg_replace('/^APP_URL=.*$/m', 'APP_URL=' . $currentUrl, $content);
        file_put_contents($envPath, $content);
        static::appendLog('已自动生成 .env 文件');
    }

    /** 若 APP_URL 为空，则自动写入当前访问地址。 */
    protected static function ensureAppUrl(Request $request, array $env)
    {
        if (!empty($env['APP_URL'])) {
            return;
        }

        static::updateEnvValue('APP_URL', static::inferAppUrl($request));
        static::appendLog('已自动写入 APP_URL');
    }

    /** 从 .env 读取安装所需配置。 */
    protected static function readEnvFile()
    {
        $keys = static::envKeys();

        $values = array();
        foreach ($keys as $key) {
            $values[$key] = (string) env_value($key, static::envDefault($key));
        }

        foreach (static::envSchema() as $section) {
            foreach ($section['fields'] as $field) {
                $key = $field['key'];
                if ($values[$key] === '' && isset($field['default'])) {
                    $values[$key] = (string) $field['default'];
                }
            }
        }

        return $values;
    }

    /** 构建安装页状态载荷。 */
    protected static function buildStatusPayload(Request $request = null, array $state = null)
    {
        $state = $state ?: static::readState();
        $request = $request ?: request();
        $env = static::readEnvFile();
        $checks = $state['running'] ? (is_array($state['checks'] ?? null) ? $state['checks'] : static::defaultChecks()) : static::inspectEnvironment($env);
        $sections = static::buildConfigSections($env, $checks, $request);
        $summary = static::buildSummary($sections, $checks, !empty($state['installed']));

        $state['checks'] = $checks;
        $state['config_sections'] = $sections;
        $state['summary'] = $summary;
        $state['current_url'] = $request ? static::inferAppUrl($request) : '';
        $state['env_path'] = BASE_PATH . '/.env';
        return $state;
    }

    /** 状态构建失败时返回兜底数据，保证安装页仍可渲染。 */
    protected static function safeFallbackStatus(Request $request = null)
    {
        try {
            return static::buildStatusPayload($request);
        } catch (Throwable $ignored) {
            return array(
                'installed' => false,
                'running' => false,
                'progress' => 0,
                'status' => 'error',
                'title' => '安装状态读取失败',
                'error' => $ignored->getMessage(),
                'logs' => array(),
                'checks' => static::defaultChecks(),
                'config_sections' => array(),
                'summary' => array(
                    'success' => 0,
                    'warning' => 0,
                    'error' => 1,
                    'installed' => 0,
                ),
                'current_url' => $request ? static::inferAppUrl($request) : '',
                'env_path' => BASE_PATH . '/.env',
                'updated_at' => date('Y-m-d H:i:s'),
            );
        }
    }

    /** 保存安装页提交的配置。 */
    protected static function saveConfig(Request $request)
    {
        static::ensureEnvFile($request);

        $config = $request->input('config', array());
        if (!is_array($config)) {
            throw new RuntimeException('配置提交格式错误');
        }

        $allowed = array_flip(static::envKeys());
        $values = array();
        foreach ($config as $key => $value) {
            if (!isset($allowed[$key])) {
                continue;
            }

            $values[$key] = static::sanitizeEnvValue($value);
        }

        if (!isset($values['APP_URL']) || $values['APP_URL'] === '') {
            $values['APP_URL'] = static::inferAppUrl($request);
        }

        static::writeEnvValues($values);
        static::appendLog('已保存 .env 配置');

        $state = static::readState();
        $state['error'] = '';
        return static::buildStatusPayload($request, $state);
    }

    /** 安全记录安装异常，避免二次异常导致页面完全失效。 */
    protected static function safeRecordException(Throwable $e)
    {
        try {
            static::ensureDirectories();

            $state = static::readState();
            $logs = is_array($state['logs'] ?? null) ? $state['logs'] : array();
            $logs[] = '[' . date('H:i:s') . '] 安装器异常：' . $e->getMessage();
            $state['installed'] = false;
            $state['running'] = false;
            $state['status'] = 'error';
            $state['title'] = '安装器发生异常';
            $state['error'] = $e->getMessage();
            $state['logs'] = array_slice($logs, -200);
            $state['checks'] = is_array($state['checks'] ?? null) ? $state['checks'] : static::defaultChecks();
            $state['updated_at'] = date('Y-m-d H:i:s');
            @file_put_contents(static::stateFile(), json_encode($state, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            @file_put_contents(static::logFile(), end($state['logs']) . PHP_EOL, FILE_APPEND | LOCK_EX);
        } catch (Throwable $ignored) {
            error_log('[installer] ' . $e->getMessage());
        }
    }

    /** 配置项结构定义。 */
    protected static function envSchema()
    {
        return array(
            array(
                'title' => '应用基础配置',
                'description' => '站点基础行为、环境模式和令牌设置。',
                'fields' => array(
                    array('key' => 'APP_NAME', 'label' => '项目名称', 'type' => 'text', 'required' => true, 'default' => 'aichat-api', 'placeholder' => 'aichat-api'),
                    array('key' => 'APP_ENV', 'label' => '运行环境', 'type' => 'select', 'required' => true, 'default' => 'production', 'options' => array(
                        array('value' => 'production', 'label' => 'production'),
                        array('value' => 'local', 'label' => 'local'),
                    )),
                    array('key' => 'APP_URL', 'label' => '站点访问地址', 'type' => 'url', 'required' => true, 'default' => '', 'placeholder' => 'http://example.com:8899'),
                    array('key' => 'APP_TIMEZONE', 'label' => '时区', 'type' => 'text', 'required' => true, 'default' => 'Asia/Shanghai', 'placeholder' => 'Asia/Shanghai'),
                    array('key' => 'APP_DEBUG', 'label' => '调试模式', 'type' => 'select', 'required' => true, 'default' => 'false', 'options' => array(
                        array('value' => 'false', 'label' => 'false'),
                        array('value' => 'true', 'label' => 'true'),
                    )),
                    array('key' => 'APP_TOKEN_TTL', 'label' => 'Token 有效期(秒)', 'type' => 'number', 'required' => true, 'default' => '604800', 'placeholder' => '604800'),
                    array('key' => 'APP_TOKEN_PREFIX', 'label' => 'Token 前缀', 'type' => 'text', 'required' => true, 'default' => 'aichat:token:', 'placeholder' => 'aichat:token:'),
                ),
            ),
            array(
                'title' => 'MySQL 配置',
                'description' => '初始化会检查连接、自动建库，并导入 database_init.sql。',
                'fields' => array(
                    array('key' => 'DB_HOST', 'label' => '数据库主机', 'type' => 'text', 'required' => true, 'default' => '127.0.0.1', 'placeholder' => '127.0.0.1'),
                    array('key' => 'DB_PORT', 'label' => '数据库端口', 'type' => 'number', 'required' => true, 'default' => '3306', 'placeholder' => '3306'),
                    array('key' => 'DB_DATABASE', 'label' => '数据库名称', 'type' => 'text', 'required' => true, 'default' => 'shujuguanli', 'placeholder' => 'shujuguanli'),
                    array('key' => 'DB_USERNAME', 'label' => '数据库账号', 'type' => 'text', 'required' => true, 'default' => 'root', 'placeholder' => 'root'),
                    array('key' => 'DB_PASSWORD', 'label' => '数据库密码', 'type' => 'password', 'required' => false, 'default' => '', 'placeholder' => '留空表示无密码'),
                    array('key' => 'DB_CHARSET', 'label' => '数据库字符集', 'type' => 'text', 'required' => true, 'default' => 'utf8mb4', 'placeholder' => 'utf8mb4'),
                ),
            ),
            array(
                'title' => 'Redis 配置',
                'description' => '登录态依赖 Redis，初始化时会直接连通测试。',
                'fields' => array(
                    array('key' => 'REDIS_HOST', 'label' => 'Redis 主机', 'type' => 'text', 'required' => true, 'default' => '127.0.0.1', 'placeholder' => '127.0.0.1'),
                    array('key' => 'REDIS_PORT', 'label' => 'Redis 端口', 'type' => 'number', 'required' => true, 'default' => '6379', 'placeholder' => '6379'),
                    array('key' => 'REDIS_PASSWORD', 'label' => 'Redis 密码', 'type' => 'password', 'required' => false, 'default' => '', 'placeholder' => '无密码可留空'),
                    array('key' => 'REDIS_DB', 'label' => 'Redis 库编号', 'type' => 'number', 'required' => true, 'default' => '0', 'placeholder' => '0'),
                    array('key' => 'REDIS_TIMEOUT', 'label' => 'Redis 超时(秒)', 'type' => 'number', 'required' => true, 'default' => '2.5', 'placeholder' => '2.5'),
                ),
            ),
            array(
                'title' => 'DeepSeek 配置',
                'description' => '不影响基础安装，但相关接口依赖这些参数。',
                'fields' => array(
                    array('key' => 'DEEPSEEK_API_KEY', 'label' => 'DeepSeek API Key', 'type' => 'password', 'required' => false, 'default' => '', 'placeholder' => 'sk-...'),
                    array('key' => 'DEEPSEEK_API_URL', 'label' => 'DeepSeek 接口地址', 'type' => 'url', 'required' => true, 'default' => 'https://api.deepseek.com/v1/chat/completions', 'placeholder' => 'https://api.deepseek.com/v1/chat/completions'),
                    array('key' => 'DEEPSEEK_MODEL', 'label' => 'DeepSeek 模型', 'type' => 'text', 'required' => true, 'default' => 'deepseek-chat', 'placeholder' => 'deepseek-chat'),
                    array('key' => 'DEEPSEEK_MAX_TOKENS', 'label' => '最大 Token 数', 'type' => 'number', 'required' => true, 'default' => '1024', 'placeholder' => '1024'),
                    array('key' => 'DEEPSEEK_TEMPERATURE', 'label' => 'Temperature', 'type' => 'number', 'required' => true, 'default' => '0.7', 'placeholder' => '0.7'),
                ),
            ),
            array(
                'title' => '系统附加配置',
                'description' => '老后台页面合并后仍会使用这些系统级配置，主要影响上传和展示。',
                'fields' => array(
                    array('key' => 'SYSTEM_NAME', 'label' => '系统名称', 'type' => 'text', 'required' => true, 'default' => '后台管理系统', 'placeholder' => '后台管理系统'),
                    array('key' => 'SYSTEM_VERSION', 'label' => '系统版本', 'type' => 'text', 'required' => true, 'default' => '1.0.0', 'placeholder' => '1.0.0'),
                    array('key' => 'SYSTEM_UPLOAD_PATH', 'label' => '上传目录', 'type' => 'text', 'required' => true, 'default' => 'uploads/', 'placeholder' => 'uploads/'),
                    array('key' => 'SYSTEM_MAX_UPLOAD_SIZE', 'label' => '最大上传大小(字节)', 'type' => 'number', 'required' => true, 'default' => '20971520', 'placeholder' => '20971520'),
                    array('key' => 'SYSTEM_ALLOWED_EXTENSIONS', 'label' => '允许上传扩展名', 'type' => 'text', 'required' => true, 'default' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt,wb,wb2,wbk,iec', 'placeholder' => 'jpg,jpeg,png,gif,pdf,doc,docx,txt,wb,wb2,wbk,iec'),
                ),
            ),
        );
    }

    /** 全部可编辑的 .env 键。 */
    protected static function envKeys()
    {
        $keys = array();
        foreach (static::envSchema() as $section) {
            foreach ($section['fields'] as $field) {
                $keys[] = $field['key'];
            }
        }
        return $keys;
    }

    /** 返回配置项默认值。 */
    protected static function envDefault($key)
    {
        foreach (static::envSchema() as $section) {
            foreach ($section['fields'] as $field) {
                if ($field['key'] === $key) {
                    return $field['default'] ?? '';
                }
            }
        }

        return '';
    }

    /** 构建前端展示用的配置分组。 */
    protected static function buildConfigSections(array $env, array $checks, Request $request = null)
    {
        $sections = array();
        foreach (static::envSchema() as $section) {
            $fields = array();
            foreach ($section['fields'] as $field) {
                $result = static::validateEnvField($field, $env[$field['key']] ?? '', $env, $request);
                $result = static::mergeFieldCheckStatus($field['key'], $result, $checks);
                $field['value'] = (string) ($env[$field['key']] ?? ($field['default'] ?? ''));
                $field['status'] = $result['status'];
                $field['message'] = $result['message'];
                $fields[] = $field;
            }

            $sections[] = array(
                'title' => $section['title'],
                'description' => $section['description'],
                'fields' => $fields,
            );
        }

        return $sections;
    }

    /** 将环境检查结果映射到具体配置项，避免“填了值就显示正常”。 */
    protected static function mergeFieldCheckStatus($key, array $result, array $checks)
    {
        $checkKey = null;

        if (in_array($key, array('DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD'), true)) {
            $checkKey = 'mysql_server';
        } elseif ($key === 'DB_DATABASE') {
            $databaseStatus = $checks['mysql_database']['status'] ?? null;
            $initStatus = $checks['mysql_init']['status'] ?? null;

            if ($databaseStatus === 'error') {
                return array(
                    'status' => 'error',
                    'message' => $checks['mysql_database']['message'] ?? $result['message'],
                );
            }

            if ($databaseStatus === 'warning') {
                return array(
                    'status' => 'warning',
                    'message' => $checks['mysql_database']['message'] ?? '数据库不存在，初始化时会尝试自动创建',
                );
            }

            if ($initStatus === 'warning') {
                return array(
                    'status' => 'warning',
                    'message' => $checks['mysql_init']['message'] ?? '数据库尚未初始化',
                );
            }

            if ($initStatus === 'success') {
                return array(
                    'status' => 'success',
                    'message' => $checks['mysql_init']['message'] ?? '数据库已初始化',
                );
            }

            return $result;
        } elseif (in_array($key, array('REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_DB', 'REDIS_TIMEOUT'), true)) {
            $checkKey = 'redis';
        } elseif ($key === 'APP_URL') {
            $checkKey = 'rewrite';
        }

        if ($checkKey === null || !isset($checks[$checkKey])) {
            return $result;
        }

        $check = $checks[$checkKey];
        $status = $check['status'] ?? null;
        $message = $check['message'] ?? '';

        if ($status === 'error') {
            return array(
                'status' => 'error',
                'message' => $message !== '' ? $message : $result['message'],
            );
        }

        if ($status === 'warning') {
            return array(
                'status' => 'warning',
                'message' => $message !== '' ? $message : $result['message'],
            );
        }

        if ($status === 'success') {
            return array(
                'status' => 'success',
                'message' => $message !== '' ? $message : $result['message'],
            );
        }

        return $result;
    }

    /** 校验配置字段。 */
    protected static function validateEnvField(array $field, $value, array $env, Request $request = null)
    {
        $key = $field['key'];
        $value = trim((string) $value);

        if (!empty($field['required']) && $value === '') {
            return array('status' => 'error', 'message' => '必填项，当前为空');
        }

        if ($value === '') {
            if ($key === 'DEEPSEEK_API_KEY') {
                return array('status' => 'warning', 'message' => '可留空，但相关 AI 接口会不可用');
            }

            return array('status' => 'success', 'message' => '可选项，当前留空');
        }

        switch ($key) {
            case 'APP_URL':
            case 'DEEPSEEK_API_URL':
                if (!filter_var($value, FILTER_VALIDATE_URL)) {
                    return array('status' => 'error', 'message' => 'URL 格式不正确');
                }
                if ($key === 'APP_URL' && $request) {
                    $currentUrl = static::inferAppUrl($request);
                    if (rtrim($value, '/') !== rtrim($currentUrl, '/')) {
                        return array('status' => 'warning', 'message' => '当前访问地址是 ' . $currentUrl . '，不一致会导致自请求检测失败');
                    }
                }
                return array('status' => 'success', 'message' => '格式正确');
            case 'APP_ENV':
                return in_array($value, array('production', 'local'), true)
                    ? array('status' => 'success', 'message' => '环境值有效')
                    : array('status' => 'error', 'message' => '只支持 production 或 local');
            case 'APP_TIMEZONE':
                return in_array($value, timezone_identifiers_list(), true)
                    ? array('status' => 'success', 'message' => '时区有效')
                    : array('status' => 'error', 'message' => '无效时区，例如 Asia/Shanghai');
            case 'APP_DEBUG':
                return in_array(strtolower($value), array('true', 'false', '1', '0'), true)
                    ? array('status' => 'success', 'message' => '布尔值有效')
                    : array('status' => 'error', 'message' => '请填写 true 或 false');
            case 'APP_TOKEN_TTL':
            case 'DB_PORT':
            case 'REDIS_PORT':
            case 'DEEPSEEK_MAX_TOKENS':
                return ctype_digit($value) && (int) $value > 0
                    ? array('status' => 'success', 'message' => '数值有效')
                    : array('status' => 'error', 'message' => '请填写大于 0 的整数');
            case 'REDIS_DB':
                return ctype_digit($value)
                    ? array('status' => 'success', 'message' => '数值有效')
                    : array('status' => 'error', 'message' => '请填写大于等于 0 的整数');
            case 'REDIS_TIMEOUT':
            case 'DEEPSEEK_TEMPERATURE':
                return is_numeric($value) && (float) $value >= 0
                    ? array('status' => 'success', 'message' => '数值有效')
                    : array('status' => 'error', 'message' => '请填写有效数字');
            case 'SYSTEM_MAX_UPLOAD_SIZE':
                return ctype_digit($value) && (int) $value > 0
                    ? array('status' => 'success', 'message' => '数值有效')
                    : array('status' => 'error', 'message' => '请填写大于 0 的整数');
            case 'SYSTEM_ALLOWED_EXTENSIONS':
                return preg_match('/^[A-Za-z0-9_,]+$/', $value)
                    ? array('status' => 'success', 'message' => '格式正确，使用逗号分隔')
                    : array('status' => 'error', 'message' => '只支持字母、数字、下划线和逗号');
            default:
                return array('status' => 'success', 'message' => '已填写');
        }
    }

    /** 运行实时环境检查。 */
    protected static function inspectEnvironment(array $env)
    {
        $checks = static::defaultChecks();

        if (version_compare(PHP_VERSION, '7.4.0', '<')) {
            $checks['php_version'] = array('title' => 'PHP 版本', 'status' => 'error', 'message' => 'PHP 版本过低，当前为 ' . PHP_VERSION . '，至少需要 7.4');
        } else {
            $checks['php_version'] = array('title' => 'PHP 版本', 'status' => 'success', 'message' => '当前版本：' . PHP_VERSION);
        }

        $required = array('pdo', 'pdo_mysql', 'redis', 'curl', 'json', 'zlib');
        $missing = array();
        foreach ($required as $extension) {
            if (!extension_loaded($extension)) {
                $missing[] = $extension;
            }
        }
        $checks['php_extensions'] = empty($missing)
            ? array('title' => 'PHP 扩展', 'status' => 'success', 'message' => '已加载：' . implode(', ', $required))
            : array('title' => 'PHP 扩展', 'status' => 'error', 'message' => '缺少扩展：' . implode(', ', $missing));

        $storageDir = BASE_PATH . '/storage';
        $stateDir = dirname(static::stateFile());
        if ((!is_dir($storageDir) && !@mkdir($storageDir, 0777, true)) || !is_writable($storageDir)) {
            $checks['storage'] = array('title' => '目录权限', 'status' => 'error', 'message' => 'storage 目录不可写：' . $storageDir);
        } elseif ((!is_dir($stateDir) && !@mkdir($stateDir, 0777, true)) || !is_writable($stateDir)) {
            $checks['storage'] = array('title' => '目录权限', 'status' => 'error', 'message' => '安装状态目录不可写：' . $stateDir);
        } else {
            $checks['storage'] = array('title' => '目录权限', 'status' => 'success', 'message' => 'storage 与安装状态目录可写');
        }

        try {
            $serverPdo = static::connectMysqlServer($env);
            $checks['mysql_server'] = array('title' => 'MySQL 服务', 'status' => 'success', 'message' => '已连接到 ' . $env['DB_HOST'] . ':' . $env['DB_PORT']);

            $exists = static::fetchProbe($serverPdo, "SHOW DATABASES LIKE " . $serverPdo->quote($env['DB_DATABASE']));
            $checks['mysql_database'] = $exists
                ? array('title' => '数据库存在性', 'status' => 'success', 'message' => '数据库已存在：' . $env['DB_DATABASE'])
                : array('title' => '数据库存在性', 'status' => 'warning', 'message' => '数据库不存在，初始化时将尝试自动创建');

            try {
                $dbPdo = static::connectMysqlDatabase($env);
                $users = static::fetchProbe($dbPdo, "SHOW TABLES LIKE 'users'");
                $checks['mysql_init'] = $users
                    ? array('title' => '数据库初始化', 'status' => 'success', 'message' => 'users 表已存在，数据库已初始化')
                    : array('title' => '数据库初始化', 'status' => 'warning', 'message' => '数据库可连接，但尚未导入初始化 SQL');
            } catch (Throwable $e) {
                $checks['mysql_init'] = array('title' => '数据库初始化', 'status' => $exists ? 'warning' : 'waiting', 'message' => $exists ? $e->getMessage() : '数据库尚不存在，创建后才会导入 SQL');
            }
        } catch (Throwable $e) {
            $checks['mysql_server'] = array('title' => 'MySQL 服务', 'status' => 'error', 'message' => $e->getMessage());
            $checks['mysql_database'] = array('title' => '数据库存在性', 'status' => 'waiting', 'message' => '等待 MySQL 连接通过');
            $checks['mysql_init'] = array('title' => '数据库初始化', 'status' => 'waiting', 'message' => '等待 MySQL 连接通过');
        }

        try {
            $redis = new Redis();
            $connected = $redis->connect($env['REDIS_HOST'], (int) $env['REDIS_PORT'], (float) $env['REDIS_TIMEOUT']);
            if (!$connected) {
                throw new RuntimeException('Redis 连接失败');
            }
            if ($env['REDIS_PASSWORD'] !== '') {
                if (!$redis->auth($env['REDIS_PASSWORD'])) {
                    throw new RuntimeException('Redis 密码错误');
                }
            }
            $redis->select((int) $env['REDIS_DB']);
            if (!static::isRedisPingSuccessful($redis->ping())) {
                throw new RuntimeException('Redis Ping 失败');
            }
            $checks['redis'] = array('title' => 'Redis 服务', 'status' => 'success', 'message' => 'Redis 连接通过：' . $env['REDIS_HOST'] . ':' . $env['REDIS_PORT']);
        } catch (Throwable $e) {
            $checks['redis'] = array('title' => 'Redis 服务', 'status' => 'error', 'message' => $e->getMessage());
        }

        try {
            if ($env['APP_URL'] === '') {
                throw new RuntimeException('APP_URL 未配置');
            }
            $target = rtrim($env['APP_URL'], '/') . '/ping?installer_probe=1&_=' . time();
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $target);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $response = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($response === false || $error !== '') {
                throw new RuntimeException('站点自请求失败：' . $error);
            }
            if ($httpCode === 404) {
                throw new RuntimeException('站点自请求返回 404，请检查伪静态配置');
            }
            $decoded = json_decode($response, true);
            if (!is_array($decoded) || empty($decoded['success'])) {
                throw new RuntimeException('站点自请求返回异常：' . $response);
            }
            $checks['rewrite'] = array('title' => '站点自请求与伪静态', 'status' => 'success', 'message' => 'APP_URL 自请求通过：' . $env['APP_URL']);
        } catch (Throwable $e) {
            $checks['rewrite'] = array('title' => '站点自请求与伪静态', 'status' => 'error', 'message' => $e->getMessage());
        }

        return $checks;
    }

    /** 计算摘要数据。 */
    protected static function buildSummary(array $sections, array $checks, $installed)
    {
        $summary = array(
            'success' => 0,
            'warning' => 0,
            'error' => 0,
            'installed' => $installed ? 1 : 0,
        );

        foreach ($sections as $section) {
            foreach ($section['fields'] as $field) {
                $status = $field['status'] ?? 'waiting';
                if (isset($summary[$status])) {
                    $summary[$status]++;
                }
            }
        }

        foreach ($checks as $check) {
            $status = $check['status'] ?? 'waiting';
            if (isset($summary[$status])) {
                $summary[$status]++;
            }
        }

        return $summary;
    }

    /** 对输入内容做基础清洗。 */
    protected static function sanitizeEnvValue($value)
    {
        $value = str_replace(array("\r", "\n"), '', (string) $value);
        return trim($value);
    }

    /** 批量写入 .env 配置。 */
    protected static function writeEnvValues(array $values)
    {
        if (empty($values)) {
            return;
        }

        $envPath = BASE_PATH . '/.env';
        $content = is_file($envPath) ? file_get_contents($envPath) : '';
        if ($content === false) {
            throw new RuntimeException('.env 读取失败');
        }

        foreach ($values as $key => $value) {
            $line = $key . '=' . $value;
            $pattern = '/^' . preg_quote($key, '/') . '=.*$/m';

            if (preg_match($pattern, $content)) {
                $content = preg_replace_callback($pattern, function () use ($line) {
                    return $line;
                }, $content, 1);
            } else {
                $content = rtrim($content, "\r\n") . PHP_EOL . $line . PHP_EOL;
            }

            if (function_exists('putenv')) {
                putenv($key . '=' . $value);
            }
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        file_put_contents($envPath, $content);
    }

    /** MySQL 服务级 DSN。 */
    protected static function mysqlServerDsn(array $env)
    {
        return 'mysql:host=' . $env['DB_HOST'] . ';port=' . $env['DB_PORT'] . ';charset=' . $env['DB_CHARSET'];
    }

    /** MySQL 数据库级 DSN。 */
    protected static function mysqlDatabaseDsn(array $env)
    {
        return 'mysql:host=' . $env['DB_HOST'] . ';port=' . $env['DB_PORT'] . ';dbname=' . $env['DB_DATABASE'] . ';charset=' . $env['DB_CHARSET'];
    }

    /** 将常见 MySQL 授权错误转换为更明确的提示。 */
    protected static function normalizeMysqlException(PDOException $e, array $env)
    {
        $message = $e->getMessage();

        if (strpos($message, '[1045]') !== false) {
            if (($env['DB_HOST'] ?? '') === '127.0.0.1') {
                return $message . '；当前使用的是 TCP(127.0.0.1) 连接，如果 MySQL 只允许 localhost/socket 登录，请将 DB_HOST 改为 localhost 或调整授权';
            }

            if (($env['DB_HOST'] ?? '') === 'localhost') {
                return $message . '；当前使用的是 localhost 连接，请检查本地 socket/账号授权是否允许';
            }
        }

        return $message;
    }

    /** 兼容不同 phpredis 版本的 ping 返回值。 */
    protected static function isRedisPingSuccessful($pingResult)
    {
        if ($pingResult === true || $pingResult === 1) {
            return true;
        }

        if (is_string($pingResult)) {
            $normalized = strtoupper(trim($pingResult));
            return $normalized === 'PONG' || $normalized === '+PONG';
        }

        return false;
    }

    /** 更新 .env 中指定项。 */
    protected static function updateEnvValue($key, $value)
    {
        static::writeEnvValues(array(
            $key => static::sanitizeEnvValue($value),
        ));
    }

    /** 推断当前站点访问地址。 */
    protected static function inferAppUrl(Request $request)
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1';
        return $scheme . '://' . $host;
    }

    /** 判断是否已安装完成。 */
    protected static function isInstalled()
    {
        if (is_file(static::lockFile())) {
            return true;
        }

        try {
            $env = static::readEnvFile();
            if ($env['DB_HOST'] === '' || $env['DB_DATABASE'] === '' || $env['DB_USERNAME'] === '') {
                return false;
            }

            $pdo = static::connectMysqlDatabase($env);
            $exists = static::fetchProbe($pdo, "SHOW TABLES LIKE 'users'");
            if ($exists) {
                @file_put_contents(static::lockFile(), date('Y-m-d H:i:s'));
                return true;
            }
        } catch (Throwable $e) {
            return false;
        }

        return false;
    }

    /** 确保安装目录存在。 */
    protected static function ensureDirectories()
    {
        $dir = dirname(static::stateFile());
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
    }

    /** 安装锁文件路径。 */
    protected static function lockFile()
    {
        return app('config')['app']['install_lock'];
    }

    /** 安装状态文件路径。 */
    protected static function stateFile()
    {
        return app('config')['app']['install_state'];
    }

    /** 安装日志文件路径。 */
    protected static function logFile()
    {
        return app('config')['app']['install_log'];
    }

    /** 安装运行锁路径。 */
    protected static function runningFile()
    {
        return dirname(static::stateFile()) . '/running.lock';
    }

    /** 执行单项安装检查。 */
    protected static function performCheck($key, $title, $progress, callable $callback)
    {
        static::setProgress($progress, $title);
        static::setCheck($key, 'running', '正在检查');

        try {
            $callback();
            static::setCheck($key, 'success', '检查通过');
        } catch (Throwable $e) {
            static::setCheck($key, 'error', $e->getMessage());
            throw $e;
        }
    }

    /** 更新单项检查状态。 */
    protected static function setCheck($key, $status, $message)
    {
        $state = static::readState();
        $checks = is_array($state['checks'] ?? null) ? $state['checks'] : static::defaultChecks();
        if (!isset($checks[$key])) {
            $checks[$key] = array('title' => $key, 'status' => 'waiting', 'message' => '');
        }
        $checks[$key]['status'] = $status;
        $checks[$key]['message'] = $message;
        $state['checks'] = $checks;
        static::saveState($state);
    }

    /** 默认检查项列表。 */
    protected static function defaultChecks()
    {
        return array(
            'php_version' => array('title' => 'PHP 版本', 'status' => 'waiting', 'message' => '等待检查'),
            'php_extensions' => array('title' => 'PHP 扩展', 'status' => 'waiting', 'message' => '等待检查'),
            'storage' => array('title' => '目录权限', 'status' => 'waiting', 'message' => '等待检查'),
            'mysql_server' => array('title' => 'MySQL 服务', 'status' => 'waiting', 'message' => '等待检查'),
            'mysql_database' => array('title' => '数据库存在性', 'status' => 'waiting', 'message' => '等待检查'),
            'mysql_init' => array('title' => '数据库初始化', 'status' => 'waiting', 'message' => '等待检查'),
            'redis' => array('title' => 'Redis 服务', 'status' => 'waiting', 'message' => '等待检查'),
            'rewrite' => array('title' => '站点自请求与伪静态', 'status' => 'waiting', 'message' => '等待检查'),
        );
    }
}
