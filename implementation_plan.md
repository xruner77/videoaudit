# 视频审片系统 (Video Audit System)

用户在播放视频时可发布审核意见,评论时自动暂停并标记时间点,评论按时间排序展示。支持用户登录、管理员视频上传与批注管理。

## 技术栈

| 层级 | 技术 |
|---|---|
| 前端 | Vite + React 18, React Router v6, Zustand |
| 后端 | **PHP 8 + Slim Framework 4**, SQLite (PDO), Firebase JWT |
| 样式 | **Tailwind CSS v4**, 深色主题 |

> [!IMPORTANT]
> 全栈单仓库项目。前端在根目录,后端在 `server/` 目录。开发时前端 `npm run dev`、后端 `php -S localhost:8080`。

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
├── src/                         # React 前端
│   ├── main.jsx
│   ├── App.jsx
│   ├── index.css
│   ├── stores/authStore.js
│   ├── components/
│   │   ├── VideoPlayer.jsx
│   │   ├── CommentPanel.jsx
│   │   ├── CommentInput.jsx
│   │   └── Header.jsx
│   └── pages/
│       ├── LoginPage.jsx
│       ├── VideoListPage.jsx
│       ├── ReviewPage.jsx
│       └── AdminPage.jsx
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

### 视频 (需管理员权限)
| 方法 | 路径 | 说明 |
|---|---|---|
| GET    | `/api/videos` | 视频列表 |
| POST   | `/api/videos/upload` | 上传本地视频文件 |
| POST   | `/api/videos/remote` | 添加远程视频 URL |
| DELETE | `/api/videos/{id}` | 删除视频 |

### 评论
| 方法 | 路径 | 说明 |
|---|---|---|
| GET    | `/api/comments/{videoId}` | 获取视频评论 |
| POST   | `/api/comments` | 创建评论 (含时间戳) |
| DELETE | `/api/comments/{id}` | 管理员删除评论 |

预设管理员: `admin / admin123`

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
- **ReviewPage**: 播放器 + 评论输入 + 评论列表
- **AdminPage**: 视频上传（本地文件或远程 URL）/ 管理 + 评论管理

---

## Verification Plan

1. 启动 PHP 后端 `php -S localhost:8080 -t server/public`
2. 启动前端 `npm run dev`
3. 浏览器测试: 登录 → 上传视频 → 播放 → 发布评论 → 验证时间戳跳转

---

## 审计发现及应对策略 (Audit Findings)

基于对上述方案的审核，补充以下安全与稳定性保障措施：

1. **生命周期安全 (Lifecycle Safety)**: 
   - `VideoPlayer` 组件内部的 `timeupdate`、`play`、`pause` 等原生 DOM 事件监听器，必须在组件卸载 (unmount) 时严格移除，防止内存泄漏或状态异常触发。
2. **权限与安全检查 (Permissions)**: 
   - 所有 `/api/videos/*` 相关的写操作和 `/api/comments/{id}` 的删除操作，后端中间件必须严格校验 JWT token 中的 `role` 是否为 `admin`。
   - 文件上传 API 必须校验扩展名和 MIME 类型，仅允许 `mp4` 和 `webm` 格式，防止恶意脚本上传及避免浏览器播放兼容性问题。
3. **性能与呈现优化 (Performance & UX)**: 
   - 考虑到大批量评论的情况，`CommentPanel` 如果评论数极多，内部需要做懒加载或虚拟列表（初期可先实现基础展示，预留优化空间）。
   - 时间轴上的评论提示点，如果有密集评论需进行简单聚合，防止 DOM 节点过多及视觉重叠。
