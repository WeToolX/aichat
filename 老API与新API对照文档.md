# 老 API 与新 API 对照文档

## 1. 文档说明


文档重点说明：

- 老接口路由
- 新接口路由
- 接口用途
- 请求方式
- 请求参数与参数说明
- 响应参数说明
- 新旧接口差异

说明约定：

- 旧接口主要通过 `/api/index.php/<path>` 或 `/api/<path>` 进入。
- 新接口统一通过伪静态单入口 `/api/...` 进入，并由目录控制器自动路由。
- 新接口的统一响应结构为：

```json
{
  "success": true,
  "code": 200,
  "data": {},
  "message": "ok"
}
```

- 文件下载类接口返回二进制文件流，不返回标准 JSON。

## 2. 总体路由映射

| 旧接口 | 新接口 | 说明 |
| --- | --- | --- |
| `/login` | `/api/login` | 登录接口 |
| `/scripts` | `/api/scripts` | 话术匹配 |
| `/keywords` | `/api/keywords` | 关键词匹配 |
| `/settings` | `/api/settings` | 功能配置读取 |
| `/momo` | `/api/momo` | 陌陌主业务入口 |
| `/momo/batch_update` | `/api/momo/batch_update` | 批量导入陌陌消息 |
| `/deepseek` | `/api/deepseek` | AI 话术生成 |
| `/chatid` | `/api/chatid` | 生成 `chat_id` |
| `/check_account` | `/api/check_account` | 单账号回复资格检测 |
| `/check_keyword` | `/api/check_keyword` | 最近会话关键词检测 |
| `/chat_history` | `/api/chat_history` | 会话聊天记录读取 |
| `/session_message` | `/api/session_message` | 批量会话资料筛选 |
| `/last_message` | `/api/last_message` | 最后一条消息资料筛选 |
| `/remoteid_check` | `/api/remoteid_check` | remoteid 资料筛选 |
| `/batch_check_account` | `/api/batch_check_account` | 旧版批量账号检测 |
| `/batch_check_account_v2` | `/api/batch_check_account_v2` | 新版批量账号检测 |
| `/download/list` | `/api/download/list` | 获取一个待下载文件 |
| `/download/file` | `/api/download/file` | 下载指定文件 |
| `/download/status` | `/api/download/status` | 更新下载状态 |
| `/download/iec/list` | `/api/download/iec/list` | 获取 IEC 文件信息 |
| `/download/iec/file` | `/api/download/iec/file` | 下载 IEC 文件 |
| 无 | `/api/online_users` | 上报在线好友状态 |

补充说明：

- 旧目录中的 `auth.php` 是认证辅助文件，不是对外 API。
- 旧目录中的 `download.php` 是历史遗留脚本，不在旧 `api/index.php` 主路由分发表中，当前已由 `/download/list`、`/download/file`、`/download/status` 三个接口替代。

## 3. 鉴权方式对照

### 3.1 旧接口

- 旧项目主要在 `api/index.php` 中手工解析 token。
- token 来源可能是：
  - Query 参数 `token`
  - Body 参数 `token`
  - `Authorization`
  - `X-Token`
  - `Token`
  - `X-Authorization`
- 旧项目依赖 `session_start()` 和 `$_SESSION['user']`。
- 旧项目不同脚本中鉴权实现不完全一致，存在重复逻辑。

### 3.2 新接口

- 新项目统一在单入口做认证拦截。
- token 存储迁移到 Redis。
- 认证成功后用户挂载到：
  - `App::user()`
  - `$GLOBALS['current_user']`
  - `$_SESSION['user']`
- 除 `/api/login` 外，其余接口默认都需要鉴权。

### 3.3 主要区别

- 旧项目是每个脚本各自处理认证，新项目是入口统一处理。
- 旧项目 session 依赖重，新项目 Redis token 更适合接口服务。
- 新项目控制器不再重复写 token 解析和认证逻辑。

## 4. 接口详细对照

### 4.1 登录接口

#### 老接口

- 路由：`/login`
- 典型访问形式：`POST /api/index.php/login`
- 接口含义：校验用户名密码并返回登录 token
- 是否鉴权：否

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `username` | string | 是 | 登录用户名 |
| `password` | string | 是 | 登录密码 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.token` | string | 登录成功后的访问令牌 |
| `data.user.id` | int | 用户 ID |
| `data.user.username` | string | 用户名 |
| `data.user.role` | int | 角色，`1` 通常表示管理员 |
| `data.user.email` | string | 邮箱 |

#### 新接口

- 路由：`/api/login`
- 控制器：`app/controller/login.php`
- 接口含义：登录并签发 Redis token

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `username` | string | 是 | 登录用户名 |
| `password` | string | 是 | 登录密码 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.token` | string | Redis 中保存的 token |
| `data.user` | object | 当前登录用户信息 |

与老接口的区别：

- 新接口路由统一加上 `/api` 前缀。
- 新接口 token 存储改为 Redis。
- 新接口登录逻辑在控制器 + 认证核心类中统一实现，不再混在旧入口脚本内。

### 4.2 话术匹配接口

#### 老接口

- 路由：`/scripts`
- 典型访问形式：`POST /api/index.php/scripts`
- 接口含义：根据话术名称精确匹配 `scripts` 表中的记录

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 固定为 `match` |
| `massage` | string | 是 | 要匹配的话术名称 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.id` | int | 话术记录 ID |
| `data.user_id` | int | 所属用户 ID |
| `data.name` | string | 话术名称 |
| `data.content` | string | 话术内容 |
| `data.created_at` | string | 创建时间 |
| `data.updated_at` | string | 更新时间 |

#### 新接口

- 路由：`/api/scripts`
- 控制器：`app/controller/scripts.php`
- 服务：`app/service/ScriptService.php`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 固定为 `match` |
| `massage` | string | 是 | 要匹配的话术名称 |

响应参数：

- 返回字段与旧接口保持基本兼容，核心仍为匹配到的 `scripts` 记录。

与老接口的区别：

- 新接口只保留控制器入口，查询逻辑下沉到 `ScriptService + Script model`。
- 新接口不在控制器中直接写 SQL。
- 新接口认证由入口统一完成。

### 4.3 关键词匹配接口

#### 老接口

- 路由：`/keywords`
- 典型访问形式：`POST /api/index.php/keywords`
- 接口含义：检查输入内容是否命中 `keywords` 表中的关键词

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 固定为 `match` |
| `keywords` | string | 是 | 需要进行包含匹配的内容 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.id` | int | 关键词记录 ID |
| `data.keyword` | string | 命中的关键词 |
| `data.reply` | string | 对应回复内容 |
| `data.user_id` | int | 所属用户 |

#### 新接口

- 路由：`/api/keywords`
- 控制器：`app/controller/keywords.php`
- 服务：`app/service/KeywordService.php`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 固定为 `match` |
| `keywords` | string | 是 | 被检测内容 |

响应参数：

- 返回字段与旧接口兼容，主要返回命中的关键词记录。

与老接口的区别：

- 新接口保留原有调用方式，便于旧客户端继续接入。
- 新接口的模糊匹配逻辑已封装到 `Keyword model`。
- 新接口统一了错误码与异常处理。

### 4.4 设置读取接口

#### 老接口

- 路由：`/settings`
- 典型访问形式：`POST /api/index.php/settings`
- 接口含义：按 `action` 返回用户功能设置或随机配置值

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 读取动作 |

支持的 `action`：

- `get`
- `auto_login`
- `nearby_like`
- `nearby_like_count`
- `nearby_like_interval`
- `nearby_like_scroll`
- `feed_like`
- `feed_like_count`
- `feed_like_interval`
- `feed_like_scroll`
- `click_delay`
- `send_delay`
- `reply_delay`
- `guide_after_messages`

响应参数：

- `action=get` 时返回完整设置对象。
- 单项读取时返回对应字段，例如：
  - `auto_login`
  - `nearby_like`
  - `nearby_like_count`
  - `interval`
  - `scroll`
  - `delay`
  - `guide_after_messages`

#### 新接口

- 路由：`/api/settings`
- 控制器：`app/controller/settings.php`
- 服务：`app/service/SettingService.php`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 设置读取动作，与旧接口兼容 |

响应参数：

- 与旧接口保持兼容。
- `get` 返回完整配置对象。
- 各单项 action 返回对应字段值。

与老接口的区别：

- 新接口保留了所有旧 action 名称，调用方基本不用改参数。
- 新接口默认值逻辑被封装在 `SettingService` 中。
- 新接口不再在控制器中直接查询 `function_settings` 表。

### 4.5 陌陌主业务接口

#### 老接口

- 路由：`/momo`
- 典型访问形式：`POST /api/index.php/momo`
- 接口含义：陌陌用户搜索、状态更新、消息导入、拉黑处理的统一入口

请求参数：

公共参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `action` | string | 是 | 子动作 |

`action=search`：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 当前会话中的目标陌陌号 |
| `send_momoid` | string | 是 | 当前发送端陌陌号 |

`action=update`：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 目标陌陌号 |
| `send_momoid` | string | 是 | 发送端陌陌号 |
| `id` | int | 否 | 目标记录 ID |
| `content` | string | 否 | 消息内容 |
| `is_send` | int | 否 | 是否发送过，`0/1` |
| `is_friend` | int | 否 | 是否好友，`0/1` |
| `message_time` | int | 否 | 消息时间戳，毫秒 |
| 其他业务字段 | mixed | 否 | 旧脚本会根据存在字段更新用户状态 |

`action=import_messages`：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 外层陌陌号 |
| `send_momoid` | string | 是 | 会话对端陌陌号 |
| `messages` | array | 是 | 消息数组 |

`action=block`：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 外层陌陌号 |
| `send_momoid` | string | 是 | 会话对端陌陌号 |
| `is_block` | int | 否 | 是否拉黑，默认 `1` |

响应参数：

- `search` 返回陌陌会话摘要：
  - `id`
  - `momoid`
  - `send_momoid`
  - `chat_id`
  - `is_friend`
  - `is_send`
  - `send_num`
  - `is_block`
  - `last_message`
- `update` 返回更新后的会话数据或状态结果。
- `import_messages` 返回导入统计，例如 `imported_count`、`skipped_count`。
- `block` 返回拉黑状态更新结果。

#### 新接口

- 路由：`/api/momo`
- 控制器：`app/controller/momo.php`
- 服务：`app/service/MomoService.php`

请求参数：

- 仍然使用 `action` 做子动作分发。
- `search`、`update`、`import_messages`、`block` 四类动作全部兼容保留。

响应参数：

- 返回结构与旧接口语义一致。
- 由 `MomoService` 统一组装返回，核心字段保持兼容。

与老接口的区别：

- 新接口仍兼容旧 `action` 形式，便于平滑迁移。
- 新接口将动态补字段、导入消息、去重、状态刷新统一收敛到 `MomoService`。
- 新接口移除了控制器中的大量直接 SQL 和表结构操作散落问题。
- 新接口通过 model 层访问 `momo_users`、`chat_messages`。

### 4.6 陌陌批量更新接口

#### 老接口

- 路由：`/momo/batch_update`
- 典型访问形式：`POST /api/index.php/momo/batch_update`
- 接口含义：批量导入多个陌陌号的消息数据

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 外层主账号陌陌号 |
| `data` | array | 是 | 批量账号数组 |

`data` 数组每项常见字段：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `陌陌号` | string | 是 | 内层会话账号，映射到 `send_momoid` |
| `massage` | array | 否 | 消息数组 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.total_accounts` | int | 总账号数 |
| `data.success_count` | int | 成功数 |
| `data.failed_count` | int | 失败数 |
| `data.results` | array | 每个账号的导入结果 |

#### 新接口

- 主路由：`/api/momo/batch_update`
- 控制器：`app/controller/momo/batch_update.php`
- 服务：`app/service/MomoService.php`

请求参数：

- 与旧接口兼容，仍接收外层 `momoid` 和内层 `data`。

响应参数：

- 继续返回导入统计与逐账号结果。
- 典型字段包括：
  - `total_accounts`
  - `success_count`
  - `failed_count`
  - `results`

与老接口的区别：

- 新接口不再通过控制器内 `curl` 回调自己。
- 新接口直接复用 `MomoService::batchUpdate()`。
- 新接口增加了目录式路由能力，同一逻辑既可走文件路由，也可走文件 + 方法路由。

### 4.7 DeepSeek 话术生成接口

#### 老接口

- 路由：`/deepseek`
- 典型访问形式：`POST /api/index.php/deepseek`
- 接口含义：基于 AI 设置、时间信息、会话上下文和聊天历史生成回复话术

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `prompt` | string | 否 | 当前提示词，可为空 |
| `history` | array | 否 | 前端传入的聊天历史 |
| `momoid` | string | 否 | 会话目标陌陌号 |
| `send_id` | string | 否 | 当前发送端 ID |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.response` | string | AI 生成的回复 |
| `data.usage` | object | 模型 token 使用情况 |

#### 新接口

- 路由：`/api/deepseek`
- 控制器：`app/controller/deepseek.php`
- 服务：`app/service/DeepseekService.php`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `prompt` | string | 否 | 当前待回复内容 |
| `history` | array | 否 | 历史消息数组 |
| `momoid` | string | 否 | 陌陌目标号 |
| `send_id` | string | 否 | 发送端 ID |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.response` | string | 生成的回复内容 |
| `data.usage` | object | 大模型接口返回的用量信息 |

与老接口的区别：

- 新接口的上下文构造逻辑收敛到 `DeepseekService`。
- 新接口仍保留旧参数名，兼容现有调用方。
- 新接口会优先从数据库读取对话上下文，再回退到前端 `history`。

### 4.8 chat_id 生成接口

#### 老接口

- 路由：`/chatid`
- 典型访问形式：`POST /api/index.php/chatid`
- 接口含义：根据 `send_momoid` 生成会话 `chat_id`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `send_momoid` | string | 是 | 发送端陌陌号 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.chat_id` | string | 生成出的 chat_id |

#### 新接口

- 路由：`/api/chatid`
- 控制器：`app/controller/chatid.php`
- 服务：`app/service/ChatService.php`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `send_momoid` | string | 是 | 发送端陌陌号 |

响应参数：

- 与旧接口保持一致，仍返回 `data.chat_id`。

与老接口的区别：

- 新接口已经纳入统一鉴权、统一校验、统一响应规范。

### 4.9 单账号检测接口

#### 老接口

- 路由：`/check_account`
- 典型访问形式：`POST /api/index.php/check_account`
- 接口含义：判断一个会话账号当前是否应该自动回复，以及返回哪种回复内容

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 目标陌陌号 |
| `send_id` | string | 是 | 发送端陌陌号 |
| `setting` | int | 否 | `0` 默认模式，`1` 严格模式 |
| `token` | string | 否 | 旧逻辑中转调 deepseek 时会透传 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.eligible` | bool | 是否符合回复条件 |
| `data.last_message` | string | 最近一条对方消息 |
| `data.generated_message` | string | 生成出的回复或命中脚本 |
| `data.is_guide` | bool | 是否为引导话术 |
| `data.is_hook` | bool | 是否为钩子话术 |
| `data.is_keyword` | bool | 是否为关键词回复 |
| `data.keyword` | string | 命中的关键词 |
| `data.message_count` | int | 当前消息条数 |
| `data.guide_threshold` | int | 引导话术阈值 |
| `data.setting` | int | 生效的严格模式开关 |
| `data.add_friend` | int | 是否启用加好友策略 |
| `data.send_num` | int | 已发送次数 |
| `data.should_block` | bool | 是否建议拉黑 |

#### 新接口

- 路由：`/api/check_account`
- 控制器：`app/controller/check_account.php`
- 服务：`app/service/AccountCheckService.php`

请求参数：

- 与旧接口兼容，仍使用：
  - `momoid`
  - `send_id`
  - `setting`
  - `token`

响应参数：

- 与旧接口主字段兼容。
- 仍可能返回：
  - 引导话术
  - 钩子话术
  - 关键词回复
  - deepseek 生成回复

与老接口的区别：

- 新接口将资格判断、脚本选择、关键词命中、AI 回退全部下沉到 `AccountCheckService`。
- 新接口不再在控制器内发起对自身接口的 HTTP 二次调用。
- 新接口通过模型层读取会话和消息数据。

### 4.10 最近回复关键词检测接口

#### 老接口

- 路由：`/check_keyword`
- 典型访问形式：`POST /api/index.php/check_keyword`
- 接口含义：检查上一次自己回复之后，对方回复内容是否命中关键词

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 目标陌陌号 |
| `send_id` | string | 是 | 发送端陌陌号 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.matched` | bool | 是否匹配到关键词 |
| `data.keyword` | string | 命中的关键词 |
| `data.reply` | string | 对应回复 |
| `data.user_reply` | string | 上次回复后对方所有消息拼接内容 |

#### 新接口

- 路由：`/api/check_keyword`
- 控制器：`app/controller/check_keyword.php`
- 服务：`app/service/ChatService.php`

请求参数：

- 与旧接口一致：
  - `momoid`
  - `send_id`

响应参数：

- 与旧接口一致。

与老接口的区别：

- 新接口将聊天记录扫描和关键词匹配下沉到 service/model 层。

### 4.11 聊天记录查询接口

#### 老接口

- 路由：旧目录中存在 `chat_history.php`
- 接入形式：通常为 `GET /api/chat_history.php?token=...&momo_user_id=...`
- 接口含义：根据 `momo_user_id` 返回会话全部聊天记录

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `token` | string | 是 | 访问令牌 |
| `momo_user_id` | int | 是 | 陌陌会话记录 ID |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data[].id` | int | 消息 ID |
| `data[].content` | string | 消息内容 |
| `data[].is_send` | int | 是否自己发送 |
| `data[].message_time` | string | 格式化后的消息时间 |

#### 新接口

- 路由：`/api/chat_history`
- 控制器：`app/controller/chat_history.php`
- 服务：`app/service/ChatService.php`

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momo_user_id` | int | 是 | 会话 ID，使用 query 参数读取 |

响应参数：

- 与旧接口兼容，返回格式化消息数组。

与老接口的区别：

- 新接口统一走入口鉴权，不再自己手工验 token。
- 新接口只允许查看当前用户自己的会话。

### 4.12 会话资料筛选接口

#### 老接口

- 路由：旧目录脚本 `session_message.php`
- 常见访问形式：`POST /api/session_message.php`
- 接口含义：传入多个 `SESSION_ID`，批量请求远程资料接口，筛选符合条件的在线男性会话

请求参数：

支持两种输入形式：

1. 包装格式

```json
{
  "momoid": "外层陌陌号",
  "remoteids": [
    { "SESSION_ID": "xxx" }
  ]
}
```

2. 直接数组格式

```json
[
  { "SESSION_ID": "xxx" }
]
```

请求参数说明：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 否 | 外层陌陌号，用于关联本地会话判断 |
| `remoteids` | array | 否 | 会话列表包装字段 |
| `remoteids[].SESSION_ID` | string | 是 | 会话 ID |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data[].id` | string | 会话 ID |
| `data[].last_interaction` | int | 最后交互时间 |
| `data[].message_content` | string | 最近消息内容 |
| `data[].sex` | string | 性别 |
| `data[].status` | string | 在线状态 |
| `data[].nickname` | string | 昵称 |

#### 新接口

- 路由：`/api/session_message`
- 控制器：`app/controller/session_message.php`
- 服务：`app/service/RemoteProfileService.php`

请求参数：

- 继续兼容旧的包装格式和直接数组格式。

响应参数：

- 继续返回筛选后的会话资料数组。

与老接口的区别：

- 新接口已统一到 `RemoteProfileService`。
- 新接口删除了脚本内自建数据库连接和大段并发处理样板代码。

### 4.13 最后一条消息资料筛选接口

#### 老接口

- 路由：旧目录脚本 `last_message.php`
- 常见访问形式：`POST /api/last_message.php`
- 接口含义：根据消息列表中的 `c4xid` 批量查询远程资料，并结合本地会话条件筛选有效用户

请求参数：

支持两种输入形式：

1. 包装格式

```json
{
  "remoteids": [
    { "c4xid": "xxx", "c5message_timestamp": 0, "c2mct": "内容" }
  ]
}
```

2. 直接数组格式

```json
[
  { "c4xid": "xxx", "c5message_timestamp": 0, "c2mct": "内容" }
]
```

请求参数说明：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `c4xid` | string | 是 | 远程 ID |
| `c5message_timestamp` | int | 否 | 最后消息时间 |
| `c2mct` | string | 否 | 最后消息内容 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data[].id` | string | remoteid |
| `data[].last_interaction` | int | 最后交互时间 |
| `data[].message_content` | string | 消息内容 |
| `data[].sex` | string | 性别 |
| `data[].status` | string | 在线状态 |
| `data[].nickname` | string | 昵称 |

#### 新接口

- 路由：`/api/last_message`
- 控制器：`app/controller/last_message.php`
- 服务：`app/service/LastMessageService.php`

请求参数：

- 与旧接口兼容。

响应参数：

- 与旧接口兼容。

与老接口的区别：

- 新接口把输入解析和远程资料获取拆分为 `LastMessageService + RemoteProfileService`。

### 4.14 remoteid 筛选接口

#### 老接口

- 路由：旧目录脚本 `remoteid_check.php`
- 常见访问形式：`POST /api/remoteid_check.php`
- 接口含义：根据多个 `m_remoteid` 批量筛选在线男性资料

请求参数：

支持两种输入形式：

1. 包装格式

```json
{
  "remoteids": [
    { "m_remoteid": "xxx", "m_msginfo": "..." }
  ]
}
```

2. 直接数组格式

```json
[
  { "m_remoteid": "xxx", "m_msginfo": "..." }
]
```

请求参数说明：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `m_remoteid` | string | 是 | 远程 ID |
| `m_msginfo` | mixed | 否 | 原消息附加信息，回传使用 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data[].id` | string | remoteid |
| `data[].sex` | string | 性别 |
| `data[].status` | string | 在线状态 |
| `data[].nickname` | string | 昵称 |
| `data[].m_msginfo` | mixed | 原样透传的消息附加信息 |

#### 新接口

- 路由：`/api/remoteid_check`
- 控制器：`app/controller/remoteid_check.php`
- 服务：`app/service/RemoteProfileService.php`

请求参数：

- 与旧接口兼容。

响应参数：

- 与旧接口兼容。

与老接口的区别：

- 新接口统一使用 service 封装远程资料拉取与筛选逻辑。

### 4.15 旧版批量账号检测接口

#### 老接口

- 路由：旧目录脚本 `batch_check_account.php`
- 常见访问形式：`POST /api/batch_check_account.php`
- 接口含义：批量检查账号并返回可发送的话术，偏向旧流程兼容

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `token` | string | 是 | 旧脚本要求显式传入 |
| `momoid` | string | 是 | 外层陌陌号 |
| `data` | array | 是 | 账号列表 |
| `setting` | int | 否 | 严格模式开关 |

`data` 每项常见字段：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `id` | string | 是 | 会话账号 ID |
| `nickname` | string | 否 | 昵称 |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data[].id` | string | 会话账号 ID |
| `data[].nickname` | string | 昵称 |
| `data[].message` | string | 可发送话术 |
| `data[].is_guide` | bool | 是否使用引导话术 |

#### 新接口

- 路由：`/api/batch_check_account`
- 控制器：`app/controller/batch_check_account.php`
- 服务：`app/service/BatchAccountService.php`

请求参数：

- 与旧接口兼容。

响应参数：

- 与旧接口兼容，此外统一在 `message` 中返回统计信息。

与老接口的区别：

- 新接口不再在控制器里堆叠批处理和会话判断逻辑。
- 新接口通过 `BatchAccountService` 调用模型与账号检测服务。

### 4.16 新版批量账号检测接口

#### 老接口

- 路由：旧目录脚本 `batch_check_account_v2.php`
- 常见访问形式：`POST /api/batch_check_account_v2.php`
- 接口含义：先批量获取远程会话资料，再对每个账号执行单账号检测逻辑

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 外层陌陌号 |
| `accounts` | array | 是 | 账号列表 |
| `setting` | int | 否 | 严格模式 |
| `token` | string | 否 | 透传给 deepseek |

`accounts` 每项常见字段：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `id` | string | 是 | 会话 ID |

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data[].id` | string | 会话 ID |
| `data[].sex` | string | 性别 |
| `data[].status` | string | 在线状态 |
| `data[].nickname` | string | 昵称 |
| `data[].message` | string | 生成的话术 |
| `data[].is_guide` | bool | 是否引导话术 |
| `data[].is_hook` | bool | 是否钩子话术 |
| `data[].is_keyword` | bool | 是否关键词回复 |
| `data[].error` | string | 单个账号处理错误 |

#### 新接口

- 路由：`/api/batch_check_account_v2`
- 控制器：`app/controller/batch_check_account_v2.php`
- 服务：`app/service/BatchAccountService.php`

请求参数：

- 与旧接口兼容。

响应参数：

- 与旧接口兼容，继续返回批量处理结果数组。

与老接口的区别：

- 新接口把远程资料获取和单账号判断拆成两个 service 协同完成。
- 新接口不再写死 user_id，不再把核心逻辑散落在脚本里。

### 4.17 文件列表接口

#### 老接口

- 路由：`/download/list`
- 典型访问形式：`POST /api/index.php/download/list`
- 接口含义：随机获取一个未下载文件的元信息

请求参数：

- 无业务参数，依赖当前登录用户。

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.id` | int | 文件 ID |
| `data.filename` | string | 系统文件名 |
| `data.original_name` | string | 原始文件名 |
| `data.file_url` | string | 文件访问 URL |
| `data.file_size` | int | 文件大小 |
| `data.file_type` | string | MIME 类型 |

#### 新接口

- 路由：`/api/download/list`
- 控制器：`app/controller/download/list.php`

请求参数：

- 无额外业务参数。

响应参数：

- 与旧接口兼容。

与老接口的区别：

- 新接口通过 `FileModel` 获取待下载文件。

### 4.18 文件下载接口

#### 老接口

- 路由：`/download/file`
- 典型访问形式：`POST /api/index.php/download/file`
- 接口含义：下载指定文件，并在下载成功后更新下载状态

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `filename` | string | 否 | 文件名 |
| `file_id` | int | 否 | 文件 ID |

说明：

- `filename` 与 `file_id` 至少传一个。

响应参数：

- 成功时直接输出二进制文件流。
- 失败时返回 JSON 错误：
  - 文件不存在
  - 文件已被删除
  - 参数缺失

#### 新接口

- 路由：`/api/download/file`
- 控制器：`app/controller/download/file.php`

请求参数：

- 与旧接口兼容：
  - `filename`
  - `file_id`

响应参数：

- 与旧接口兼容，成功时仍然返回文件流。

与老接口的区别：

- 新接口通过 `FileModel` 做文件归属和未下载状态校验。

### 4.19 下载状态更新接口

#### 老接口

- 路由：`/download/status`
- 典型访问形式：`POST /api/index.php/download/status`
- 接口含义：按文件名更新是否已下载状态

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `filename` | string | 是 | 文件名 |
| `is_downloaded` | int | 否 | 是否已下载，默认 `1` |

响应参数：

- 成功时返回空数据 + 成功消息。

#### 新接口

- 路由：`/api/download/status`
- 控制器：`app/controller/download/status.php`

请求参数：

- 与旧接口一致。

响应参数：

- 与旧接口一致。

与老接口的区别：

- 新接口通过 `FileModel` 做归属判断和状态更新。

### 4.20 IEC 文件列表接口

#### 老接口

- 路由：`/download/iec/list`
- 典型访问形式：`POST /api/index.php/download/iec/list`
- 接口含义：返回固定 IEC 文件的元信息

请求参数：

- 无业务参数。

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.id` | int | 固定为 `1` |
| `data.filename` | string | 固定文件名 |
| `data.original_name` | string | 原始文件名 |
| `data.file_url` | string | IEC 文件 URL |
| `data.file_size` | int | 文件大小 |
| `data.file_type` | string | MIME 类型 |

#### 新接口

- 路由：`/api/download/iec/list`
- 控制器：`app/controller/download/iec/list.php`

请求参数：

- 无业务参数。

响应参数：

- 与旧接口一致。

与老接口的区别：

- 新接口把固定文件信息封装到专门的目录控制器中。

### 4.21 IEC 文件下载接口

#### 老接口

- 路由：`/download/iec/file`
- 典型访问形式：`POST /api/index.php/download/iec/file`
- 接口含义：下载固定 IEC 文件

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `filename` | string | 否 | 老脚本里读取但未强依赖 |

响应参数：

- 成功时返回二进制文件流。
- 失败时返回 JSON 错误。

#### 新接口

- 路由：`/api/download/iec/file`
- 控制器：`app/controller/download/iec/file.php`

请求参数：

- 无强制业务参数。

响应参数：

- 与旧接口一致，返回文件流。

与老接口的区别：

- 新接口使用专门目录控制器承接，不再把下载逻辑写在总入口 switch 中。

### 4.22 在线好友上报接口

#### 老接口

- 旧系统无此接口。

#### 新接口

- 路由：`/api/online_users`
- 控制器：`app/controller/online_users.php`
- 服务：`app/service/MomoService.php`
- 接口含义：上报指定 `momoid` 当前在线的好友 ID 列表，并同步刷新 `momo_users` 表中的在线状态

请求参数：

| 参数 | 类型 | 必填 | 说明 |
| --- | --- | --- | --- |
| `momoid` | string | 是 | 主账号陌陌号 |
| `data` | array | 是 | 当前在线好友 ID 数组 |

`data` 数组示例：

```json
{
  "momoid": "10001",
  "data": ["20001", "20002", "20003"]
}
```

处理规则：

- 先将当前用户下该 `momoid` 的全部 `send_momoid` 记录置为离线
- 再将 `data` 数组中存在的 `send_momoid` 标记为在线

响应参数：

| 字段 | 类型 | 说明 |
| --- | --- | --- |
| `data.momoid` | string | 本次上报的主账号 |
| `data.reported_online_count` | int | 上报的在线好友数量 |
| `data.updated_online_count` | int | 成功更新为在线的数据库记录数量 |
| `data.total_user_count` | int | 当前用户下该 `momoid` 的会话总数 |

与老接口的区别：

- 这是新系统新增接口，旧系统没有对应能力。
- 新接口依赖 `momo_users.is_online` 字段维护在线状态。
- 适合客户端定时批量上报在线好友列表。

## 5. 旧接口与新接口的整体差异总结

### 5.1 路由结构

- 旧接口主要依赖 `api/index.php` 中的大型 `switch` 分发。
- 新接口改为目录控制器路由，按文件结构映射：
  - `/api/login` -> `app/controller/login.php`
  - `/api/download/list` -> `app/controller/download/list.php`
  - `/api/momo/batch_update` -> `app/controller/momo/batch_update.php`

### 5.2 鉴权机制

- 旧接口：session + 脚本内 token 校验。
- 新接口：Redis token + 入口拦截器统一认证。

### 5.3 代码结构

- 旧接口：大量脚本式代码，逻辑、SQL、认证、输入解析混在一个文件中。
- 新接口：`controller + service + model + core` 分层。

### 5.4 SQL 使用方式

- 旧接口：控制器脚本直接写 SQL。
- 新接口：控制器不直接写 SQL，service 不直接写 SQL，统一走 model。

### 5.5 安全性

- 新接口在入口增加了明显的 SQL 注入和文件注入特征拦截。
- 新接口参数校验更统一，异常与状态码更清晰。

### 5.6 兼容性

- 新接口尽量保持旧参数名和主要返回字段不变。
- 这样旧客户端可以较低成本切换到新路由。

## 6. 仅新系统新增接口说明

### 6.1 `/api/online_users`

- 含义：上报某个 `momoid` 当前在线的好友 ID 数组。
- 作用：维护 `momo_users.is_online` 在线状态，便于后续做在线好友筛选、消息推送策略和运营统计。
- 特点：无旧接口对应项，属于本次重构后新增能力。

## 7. 附录：非对外脚本说明

### 7.1 `api/auth.php`

- 作用：旧项目认证辅助函数文件。
- 包含内容：
  - `checkAuth()`
  - `checkSuperUser()`
  - `getCurrentUserId()`
  - `checkResourcePermission()`
- 结论：它不是外部 API，不应被文档当作独立接口暴露。

### 7.2 `api/download.php`

- 作用：旧项目历史遗留下载脚本。
- 特点：
  - 读取当前用户全部文件
  - 找到一个可下载文件后直接输出文件流
- 结论：
  - 不在旧 `api/index.php` 主路由表中
  - 当前正式接口应以 `/download/list`、`/download/file`、`/download/status` 为准
