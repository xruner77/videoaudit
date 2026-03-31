# VideoAudit 代码审计与重构 — 工作记录

## 一、已完成的工作

### ✅ P1 — JWT 密钥外部化 [CRITICAL → 已修复]

**问题**：JWT 密钥 `videoaudit_jwt_secret_key_2026` 硬编码在 3 个 PHP 路由文件中，且已推送到 GitHub 公开仓库，存在严重安全隐患。

**修复方案**：

| 文件 | 变更 |
|------|------|
| [db_config.php](file:///d:/tfx/myapp/videoaudit/server/db_config.php) | 新增 `jwt_secret` 配置项，使用新密钥 |
| [index.php](file:///d:/tfx/myapp/videoaudit/server/public/index.php) | 路由注册改为 `$routes($app, $db, $dbConfig)` |
| [auth.php](file:///d:/tfx/myapp/videoaudit/server/src/Routes/auth.php) | 函数签名加 `array $config`，读取 `$config['jwt_secret']` |
| [videos.php](file:///d:/tfx/myapp/videoaudit/server/src/Routes/videos.php) | 同上 |
| [comments.php](file:///d:/tfx/myapp/videoaudit/server/src/Routes/comments.php) | 同上 |

> [!IMPORTANT]
> `db_config.php` 已加入 `.gitignore`，不会被推送到 Git。服务器上的配置需要手动管理。

---

### ✅ P2 — 提取公共工具函数 useUtils.js [HIGH → 已修复]

**问题**：5 个工具函数在 3~7 个文件中重复定义，维护成本高。

**修复方案**：创建 [useUtils.js](file:///d:/tfx/myapp/videoaudit/src/composables/useUtils.js)，包含以下函数：

| 函数 | 用途 | 原重复次数 |
|------|------|-----------|
| `formatDuration(seconds)` | 格式化时长 `M:SS` | 3 次 |
| `formatTime(seconds)` | 格式化时间点 `M:SS` | 6 次 |
| `formatDateSimple(dateStr)` | 格式化日期 `X月X日` | 4 次 |
| `getUserColor(username)` | 根据用户名生成头像颜色 | 4 次 |
| `getVideoThumbUrl(video)` | 生成视频缩略图 URL | 3 次 |

**已替换的 7 个文件**：

| 文件 | 替换的函数 |
|------|-----------|
| [videoList.vue](file:///d:/tfx/myapp/videoaudit/src/pages/videoList/videoList.vue) | 全部 5 个 |
| [admin.vue](file:///d:/tfx/myapp/videoaudit/src/pages/admin/admin.vue) | formatDateSimple, formatDuration, formatTime, getUserColor |
| [review.vue](file:///d:/tfx/myapp/videoaudit/src/pages/review/review.vue) | formatTime, getAvatarColor (→ getUserColor) |
| [profile.vue](file:///d:/tfx/myapp/videoaudit/src/pages/profile/profile.vue) | getAvatarColor (→ getUserColor), formatTime |
| [myVideos.vue](file:///d:/tfx/myapp/videoaudit/src/pages/profile/myVideos.vue) | formatDuration, getVideoThumbUrl, formatDateSimple |
| [myCommentsList.vue](file:///d:/tfx/myapp/videoaudit/src/pages/profile/myCommentsList.vue) | getUserColor, formatDateSimple, formatTime |
| [myComments.vue](file:///d:/tfx/myapp/videoaudit/src/pages/myComments/myComments.vue) | formatTime |

> [!NOTE]
> `review.vue` 和 `profile.vue` 中原函数名为 `getAvatarColor`，通过 `import { getUserColor as getAvatarColor }` 保持模板兼容。

---

### ✅ Quick Win — 空 catch 块修复

[review.vue:760](file:///d:/tfx/myapp/videoaudit/src/pages/review/review.vue#L760) 的空 `catch (e) {}` 已改为 `catch (e) { console.warn('exitFullscreen failed:', e) }`

---

## 二、验证结果

- ✅ `npm run build:h5` 编译通过，零错误
- ✅ 前端部署至 `va.xruner.tk`，登录、Dashboard、视频列表、评论均正常
- ✅ JWT 新密钥工作正常（旧 token 会自动失效，用户重新登录即可）
- ✅ Git 已提交并推送：`[安全+重构] JWT密钥外部化至配置文件，提取公共工具函数useUtils.js消除7个文件中的重复代码`

---

## 三、未完成的工作

### 🔲 P3 — 删除 videoList.vue 管理员分支 [HIGH]

**当前问题**：[videoList.vue](file:///d:/tfx/myapp/videoaudit/src/pages/videoList/videoList.vue) (742 行) 通过 `v-if="authStore.isAdmin"` 包含了完整的管理员后台（Dashboard + 视频/评论/用户管理），与 [admin.vue](file:///d:/tfx/myapp/videoaudit/src/pages/admin/admin.vue) (1701 行) 功能完全重复。

**待做**：
1. 确认路由配置中管理员入口确实是 `admin.vue`
2. 删除 `videoList.vue` 中所有 `v-if="authStore.isAdmin"` 的 HTML 分支
3. 删除对应的管理员专用 JS 逻辑（`fetchDashboard`, `switchAdminTab`, 管理员分页等）
4. 删除管理员专用 CSS
5. 预计可减少 **400+ 行**代码

**风险**：需确保普通用户的首页视频列表不受影响。

---

### 🔲 P4 — 提取 VideoCard + CommentCard 组件 [HIGH]

**当前问题**：视频卡片模板（`.video-manage-card`）在 3 个文件中重复，评论卡片（`.recent-comment-item`）也在 3 个文件中重复。

**待做**：
1. 创建 `src/components/VideoCard.vue` — 统一视频卡片（缩略图、标题、上传者、时长、评论数、播放量）
2. 创建 `src/components/CommentCard.vue` — 统一评论卡片（用户头像、评论内容、时间戳、时间）
3. 在 `admin.vue`、`videoList.vue`、`myVideos.vue`、`myCommentsList.vue` 中替换为组件引用
4. 清理各文件中重复的 CSS

**注意**：
- 各页面的卡片功能略有差异（admin 有删除按钮，普通用户没有），需通过 props/slots 处理
- 工作量约 1-3 天

---

### 🔲 P5 — 拆分 review.vue [MEDIUM]

**当前问题**：[review.vue](file:///d:/tfx/myapp/videoaudit/src/pages/review/review.vue) 有 2055 行，包含至少 6 个独立职责。

**建议拆分方案**：
```
review.vue (2055行) → 拆分为:
├── VideoPlayer.vue (~400行) — 播放器控制 + 全屏管理
├── ProgressBar.vue (~150行) — 进度条 + 手势 + 评论圆点
├── CommentThread.vue (~300行) — 评论列表 + 排序 + 展开/折叠
├── CommentForm.vue (~200行) — 评论输入 + 图片上传
└── review.vue (~400行) — 页面容器 + 数据获取
```

**风险**：拆分涉及跨组件状态共享（`currentTime`, `videoContext` 等），需要 `provide/inject` 或 props 传递。工作量较大（3+ 天）。

---

### 🔲 其他待办

| 项目 | 说明 | 优先级 |
|------|------|--------|
| 后端添加 `GET /api/videos/:id` | review.vue 目前拉全量视频再 `.find()` 筛选 | LOW |
| CORS 配置确认 | 当前 `Access-Control-Allow-Origin: *`，生产环境建议限制域名 | LOW |
| `index.php` 每次请求建表优化 | 移至独立安装脚本 | LOW |

---

## 四、参考文件

| 文件 | 说明 |
|------|------|
| [AUDIT_REPORT.md](file:///d:/tfx/myapp/videoaudit/AUDIT_REPORT.md) | 完整审计报告（评分 58/100） |
| [useUtils.js](file:///d:/tfx/myapp/videoaudit/src/composables/useUtils.js) | 公共工具函数 |
| [db_config.php](file:///d:/tfx/myapp/videoaudit/server/db_config.php) | 数据库+JWT 配置（不在 Git 中） |
