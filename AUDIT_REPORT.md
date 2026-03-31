# Vibe Code Audit Report — VideoAudit

**Input:** 21 files (15 frontend `.vue/.js` + 6 backend `.php`)
**Language/Framework:** Vue 3 + uni-app (frontend) / PHP 8 + Slim 4 + MySQL (backend)
**Quick Stats:** ~7,440 行代码，194 KB 前端 + 41 KB 后端
**Assumptions:** 生产 Web 服务，中等规模（100-1000 用户）

---

## Executive Summary (Read This First)

- **[CRITICAL]** JWT 密钥硬编码在 3 个 PHP 源码文件中，且已提交到 Git 仓库
- **[CRITICAL]** `review.vue` (2055 行) 和 `admin.vue` (1719 行) 严重超过合理组件大小，任何修改都有高回归风险
- **[HIGH]** 5 个工具函数在 **3~6 个文件**中重复定义，维护成本倍增
- **[HIGH]** `videoList.vue` 将管理员视图和普通用户视图合并在一个 769 行的文件中，职责不清
- Overall: **Deployable for internal use with monitoring, but needs targeted fixes**

---

## Critical Issues (Must Fix Before Production)

### [CRITICAL #1] JWT 密钥硬编码在源码中

| | |
|---|---|
| **Location** | `auth.php:15`, `videos.php:17`, `comments.php:19` |
| **Dimension** | Security & Safety |
| **Problem** | `$jwtSecret = 'videoaudit_jwt_secret_key_2026'` 直接写在源码中 并已推送到 GitHub 公开仓库。攻击者获取后可伪造任意用户的 JWT token，包括管理员。 |
| **Fix** | 将 JWT 密钥移至 `db_config.php` 或独立环境配置，从 3 个路由文件引用。同时 **立即轮换密钥**（当前值已泄露）。 |

```php
// ❌ Before (3 个文件各写一遍)
$jwtSecret = 'videoaudit_jwt_secret_key_2026';

// ✅ After (db_config.php 统一管理)
// db_config.php 中新增:
// 'jwt_secret' => 'NEW_RANDOM_64_CHAR_SECRET'
// 路由文件中:
$jwtSecret = $dbConfig['jwt_secret'];
```

---

### [CRITICAL #2] 巨型单文件组件（God Components）

| | |
|---|---|
| **Location** | `review.vue` (2055 行), `admin.vue` (1719 行), `videoList.vue` (769 行) |
| **Dimension** | Architecture & Design |
| **Problem** | 3 个组件超过 300 行阈值（最大的超过 **6.8 倍**）。`review.vue` 包含视频播放器控制、评论系统、进度条手势、全屏管理、图片上传等 **至少 6 个独立职责**。任何修改都可能引发意外的副作用。 |
| **Fix** | 分阶段拆分。优先提取独立的子组件（VideoPlayer、CommentList、ProgressBar）。参考下方重构优先级。 |

```
review.vue (2055行) → 拆分为:
├── VideoPlayer.vue (~400行) — 播放器+控制+全屏
├── ProgressBar.vue (~150行) — 进度条+手势+评论圆点
├── CommentThread.vue (~300行) — 评论列表+排序+回复
├── CommentForm.vue (~200行) — 评论输入+图片上传
└── review.vue (~400行) — 页面容器+数据获取+路由
```

---

## High-Risk Issues

### [HIGH #1] 工具函数大面积重复

| | |
|---|---|
| **Location** | 6 个 `.vue` 文件 |
| **Dimension** | Consistency & Maintainability |
| **Problem** | 5 个相同函数被复制粘贴到多个文件中 |

| 函数 | 重复次数 | 文件 |
|------|---------|------|
| `formatTime()` | **6次** | videoList, admin, myCommentsList, myComments, profile, review |
| `formatDateSimple()` | **4次** | videoList, admin, myVideos, myCommentsList |
| `getUserColor()` / `getAvatarColor()` | **4次** | videoList, admin, myCommentsList, review |
| `formatDuration()` | **3次** | videoList, admin, myVideos |
| `getVideoThumbUrl()` | **3次** | videoList, admin, myVideos |

**Fix:** 提取到 `composables/useUtils.js`，所有页面引用。**工作量：S（< 1天）**

---

### [HIGH #2] 视频卡片和评论卡片模板+样式重复

| | |
|---|---|
| **Location** | videoList.vue, admin.vue, myVideos.vue, myCommentsList.vue |
| **Dimension** | Consistency & Maintainability |
| **Problem** | `.video-manage-card` 模板+CSS 在 3 个文件中完全重复（每处约 30 行 HTML + 20 行 CSS）。`.recent-comment-item` 同样在 3 个文件中重复。修改任一样式需要找到并同步修改所有副本。 |
| **Fix:** 提取 `VideoCard.vue` 和 `CommentCard.vue` 公共组件。**工作量：M（1-3天）** |

---

### [HIGH #3] videoList.vue 职责混杂

| | |
|---|---|
| **Location** | `videoList.vue` (769 行) |
| **Dimension** | Architecture & Design |
| **Problem** | 同一个文件通过 `v-if="authStore.isAdmin"` 包含了完全不同的两套 UI 和逻辑：管理员管理后台（Dashboard、视频/评论/用户管理）和普通用户视频列表。这意味着普通用户会加载 admin 的所有代码。 |
| **Fix:** 已有独立的 `admin.vue` 页面，可将 `videoList.vue` 中的管理员分支移除，仅保留普通用户视图。**工作量：S** |

---

## Maintainability Problems

### [MEDIUM #1] `admin.vue` 和 `videoList.vue` 管理员代码重复

| | |
|---|---|
| **Location** | `admin.vue` (1719行) vs `videoList.vue` (769行) |
| **Dimension** | Architecture & Design |
| **Problem** | 两个文件都实现了完整的管理员 Dashboard（统计卡片、最近视频、最近评论、视频管理、评论管理、用户管理），功能几乎完全重复。目前不清楚哪个是"正在用"的版本。 |
| **Fix:** 保留一个，删除另一个。根据路由配置确定主入口。 |

---

### [MEDIUM #2] 每次请求都执行 `CREATE TABLE IF NOT EXISTS`

| | |
|---|---|
| **Location** | `server/public/index.php:38-95` |
| **Dimension** | Production Risks |
| **Problem** | 每个 HTTP 请求都会执行 3 条 `CREATE TABLE IF NOT EXISTS` SQL 语句。虽然 MySQL 对此开销极小，但这是不规范的做法，且在高并发下可能产生额外开销。 |
| **Fix:** 移至独立的 `install.php` 初始化脚本，或用 migration 工具管理。 |

---

### [MEDIUM #3] 空 catch 块吞噬错误

| | |
|---|---|
| **Location** | `review.vue:768` |
| **Dimension** | Robustness & Error Handling |
| **Problem** | `} catch (e) {}` — 全屏退出失败时完全静默。虽然影响不大，但建立了不良模式。 |
| **Fix:** 至少添加 `console.warn`。 |

---

### [MEDIUM #4] `fetchVideoDetail` 拉取全部视频再筛选

| | |
|---|---|
| **Location** | `review.vue:600-605` |
| **Dimension** | Production Risks |
| **Problem** | 获取单个视频详情时，调用 `GET /api/videos`（无 ID 筛选），拉回所有视频列表后用 `.find()` 在前端过滤。随着视频数量增长，这会产生不必要的带宽和延迟。 |
| **Fix:** 后端添加 `GET /api/videos/:id` 接口返回单个视频。 |

---

### [LOW #1] 前端无测试覆盖

| | |
|---|---|
| **Dimension** | Testing Debt |
| **Problem** | 项目无任何前端单元测试或 E2E 测试文件。所有验证依赖手动操作。 |

### [LOW #2] 后端无 CORS 限制确认

| | |
|---|---|
| **Dimension** | Security |
| **Problem** | 未从代码中确认 CORS 配置。如果使用默认 `Access-Control-Allow-Origin: *`，可能存在 CSRF 风险。 *(unconfirmed — verify)* |

---

## Production Readiness Score

```
Score: 58 / 100
```

Scoring breakdown:
```
Start: 100
[CRITICAL #1] JWT 硬编码 (security):  -20
[CRITICAL #2] God components:          -15
[HIGH #1] 函数重复:                     -8
[HIGH #2] 组件模板重复:                 -8
[HIGH #3] 职责混杂:                     -8
[MEDIUM] × 4:                          -12
Pervasive pattern (重复代码):           -5
```
> **Floor: 0, Ceiling: 100 → Final: 58**

该系统功能完整、可正常运行，但存在显著的安全隐患（JWT 密钥泄露）和严重的可维护性问题。适合在内部使用并密切监控，但需修复 CRITICAL 项后才可面向外部生产环境。

---

## Refactoring Priorities

```
1. [P1 - Blocker] JWT 密钥外部化 — addresses [CRITICAL #1]
   Effort: S (< 1天)
   Impact: 消除已泄露密钥的安全风险

2. [P2 - High] 提取工具函数到 composables/useUtils.js — addresses [HIGH #1]
   Effort: S (< 1天)
   Impact: 消除 6 个文件中 23 处重复代码

3. [P3 - High] 删除 videoList.vue 中的管理员分支 — addresses [HIGH #3] + [MEDIUM #1]
   Effort: S (< 1天)
   Impact: 消除 ~400 行重复代码，清晰化路由职责

4. [P4 - High] 提取 VideoCard + CommentCard 组件 — addresses [HIGH #2]
   Effort: M (1-3天)
   Impact: 消除 3 个文件中的模板+CSS 重复

5. [P5 - Medium] 拆分 review.vue — addresses [CRITICAL #2]
   Effort: L (> 3天)
   Impact: 将 2055 行巨型组件拆为 5 个可维护的子组件
```

### Quick Wins (fix in < 1 hour)

- **JWT 密钥外部化**: 在 `db_config.php` 中新增 `jwt_secret` 字段，3 个路由文件改为读取配置
- **删除空 catch**: `review.vue:768` 添加 `console.warn('exitFullscreen failed:', e)`
- **添加单视频 API**: 后端 `videos.php` 添加 `GET /api/videos/:id` 路由
