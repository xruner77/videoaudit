# 视频审片系统 (Video Audit System)

用户在播放视频时可发布审核意见,评论时自动暂停并标记时间点,评论按时间排序展示。支持用户登录，**所有登录用户均可**进行视频上传，管理员（具有 admin 角色的专属用户）在独立的后台页面进行全局批注管理与违规视频删除。

## 技术栈

| 层级 | 技术 |
|---|---|
| 前端 | **UniApp (Vue 3)**, Vite, Pinia |
| 后端 | **PHP 8 + Slim Framework 4**, SQLite (PDO), Firebase JWT |
| 样式 | **Tailwind CSS v4**, 深色主题 |

> [!IMPORTANT]
> 包含前后端的单仓库项目。前端采用 UniApp，项目结构在根目录,后端在 `server/` 目录。开发时前端使用 HBuilderX 运行或执行 `npm run dev:h5`，后端执行 `php -S localhost:8080`。

## 项目结构

```
videoaudit/
├── server/                      # PHP 后端
│   ├── composer.json
│   ├── public/
│   │   └── index.php            # Slim 入口
│   ├── src/
│   │   ├── Middleware/
│   │   │   └── AuthMiddleware.php
│   │   └── Routes/
│   │       ├── auth.php         # 登录/注册
│   │       ├── videos.php       # 视频管理
│   │       └── comments.php     # 评论管理
│   ├── uploads/                 # 本地上传视频存储
│   └── database.sqlite          # SQLite 数据库
├── src/                         # UniApp 前端 (Vue 3)
│   ├── App.vue
│   ├── main.js
│   ├── manifest.json
│   ├── pages.json
│   ├── static/                  # 静态资源
│   ├── stores/
│   │   └── authStore.js
│   ├── components/
│   │   ├── VideoPlayer.vue
│   │   ├── CommentPanel.vue
│   │   ├── CommentInput.vue
│   │   └── Header.vue
│   └── pages/
│       ├── login/login.vue
│       ├── videoList/videoList.vue
│       ├── review/review.vue
│       ├── upload/upload.vue
│       └── admin/admin.vue
├── index.html
├── package.json
└── vite.config.js
```

---

## 后端 API (Slim Framework)

### 认证
| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/api/auth/register` | 用户注册 |
| POST | `/api/auth/login` | 登录,返回 JWT |
| GET  | `/api/auth/me` | 获取当前用户信息 |

### 视频 (要求已登录用户)
| 方法 | 路径 | 说明 |
|---|---|---|
| GET    | `/api/videos` | 视频列表 |
| POST   | `/api/videos/upload` | 用户上传本地视频文件 |
| POST   | `/api/videos/remote` | 用户添加远程视频 URL |
| DELETE | `/api/videos/{id}` | 删除视频（仅限创建者本人或管理员） |

### 评论
| 方法 | 路径 | 说明 |
|---|---|---|
| GET    | `/api/comments/{videoId}` | 获取视频评论 |
| POST   | `/api/comments` | 创建评论 (含时间戳) |
| DELETE | `/api/comments/{id}` | 管理员删除评论 |

预设或修改管理员: 可通过数据库将任意现有用户的 `role` 字段标记为 `admin`。

---

## 前端核心组件

### VideoPlayer
播放/暂停、快退/快进 (5s)、倍速 (0.5x~2x)、时间轴拖动、缩略图预览、音量、全屏、评论时间点标记

### CommentInput
点击评论自动暂停视频,捕获当前时间戳,提交后可恢复播放

### CommentPanel
评论列表按时间排序,点击时间戳跳转播放位置,管理员可删除

### 页面
- **LoginPage**: 登录/注册, 深色主题
- **VideoListPage**: 卡片式视频列表
- **UploadPage**: 供普通用户（包括管理员）进行视频上传（本地文件或远程 URL）及个人视频管理页。
- **AdminPage**: 仅限具有 `admin` 角色的管理员访问的独立页面，进行全局违规视频、所有评论的管理操作。

---

## Verification Plan

1. 启动 PHP 后端 `php -S localhost:8080 -t server/public`
2. 启动前端: 使用 HBuilderX 运行到 H5 或小程序，或者执行 `npm run dev:h5`
3. 浏览器或模拟器测试: 登录 → 上传视频 → 播放 → 发布评论 → 验证时间戳跳转

---

## 审计发现及应对策略 (Audit Findings - UniApp 修改版)

基于 `plan` 技能与前端迁移至 **UniApp (Vue 3)** 的二次审核，补充以下安全、稳定性与兼容性保障措施：

1. **全局冲突 (Global Conflicts)**:
   - 全局状态/样式：所有 Vue 组件样式必须使用 `<style scoped>` 避免 CSS 污染；全局变量统一收口至 Pinia `authStore`，避免滥用 `getApp().globalData`。
   - 全局事件：若需跨页面通信使用 `uni.$on`，必须在对应的生命周期（如 `onUnload` / `onUnmounted`）中执行 `uni.$off` 清理，防止重复触发。
2. **生命周期安全 (Lifecycle Safety)**: 
   - 视频上下文管理：使用 `uni.createVideoContext('videoId')` 控制视频时，组件卸载 (`onUnmounted`) 或页面卸载 (`onUnload`) 必须安全销毁实例引用，停止视频播放，避免内存泄漏或背景音持续播放。
   - 异步回调清理：所有持续的计时器、异步请求的回调在组件销毁后需增加安全判断，防止写入已销毁组件的状态。
3. **权限与越权防范 (Permissions & Privilege Escalation Defenses)**: 
   - **后端 API（核心防线）**：所有视频上传相关的写操作必须校验 JWT token 确认为**已登录用户**；`/api/videos/{id}` 的删除操作需校验操作者是否为视频的拥有者或是 `admin`。`/api/comments/{id}` 的删除操作需严格校验 `admin` 角色或创建者本人。
   - **前端路由隔离（纵深防御）**：为了避免合并页面的前端状态篡改风险，使用独立的 `AdminPage` 隔离管理功能。利用 `uni.addInterceptor`（如 `navigateTo`, `switchTab` 等）设定拦截器：未登录用户拦截至登录页；试图访问 `AdminPage` 必须由拦截逻辑校验 Pinia `authStore` 中的 `role === 'admin'`，否则强制重定向至首页。
4. **性能损耗 (Performance)**: 
   - 长列表优化：大批量评论在各端（尤其小程序端）渲染会带来严重卡顿。`CommentPanel` 评论列表如果数据极大需分批加载（分页）或采用虚拟列表（`scroll-view` 的 `custom-type="list"` 机制）以避免 DOM 节点过多。
   - 密集打点：时间轴上的评论提示点需根据时间跨度进行聚合渲染，防止因频繁触发 Vue 响应式更新或渲染过多小点导致滑动卡顿。
5. **交互回退与跨端兼容 (Fallbacks)**:
   - 视频内核差异：小程序、H5 及 App 端使用的视频播放器内核表现有差异（比如 iOS 的强制全屏问题），必须对 `<video>` 标签添加 `playsinline`、`webkit-playsinline` 属性。
   - 不支持特性降级：若某些环境不支持高阶 API，需确保有基础的文本功能作为托底。
