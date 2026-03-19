---
name: dev
description: 启动本地前端开发服务器，并代理请求至线上后端
---

# 🚀 本地前后台联调测试 (Local Development)

本技能用于在本地快速启动前端测试环境，并直接连接到生产环境的后端服务进行真实数据跑通。

## 🎯 核心机制
- **前端热更新**：前端运行在本地 Vite 服务器上，支持极速热更新，修改代码后浏览器立即生效。
- **线上后端直连**：本地的接口网络请求（`/api/*`）会依赖于 `vite.config.js` 配置的 Proxy，完全无缝、透明、无跨域地转发给线上真实的后台服务器（`https://va.xruner.tk`）。
- **零包袱**：无需在本地电脑上安装任何 PHP 或 SQLite 运行环境！

## 🛠️ 执行步骤

1. **启动服务**
   请直接在此处唤起终端并执行：
   ```bash
   npm run dev:h5
   ```

2. **部署变动的后端文件**（如有）
   由于本地 API 请求会代理到线上服务器，如果本次开发中修改了 `server/` 目录下的任何后端 PHP 文件，**必须先将变动的文件部署到服务器**，否则线上后端不会生效。
   ```bash
   # 将变动的后端文件逐个上传，例如：
   scp -i ~/.ssh/a2.xruner.tk server/src/Routes/comments.php root@va.xruner.tk:/www/wwwroot/va.xruner.tk/server/src/Routes/comments.php
   ```
   > 💡 可以用 `git diff --name-only server/` 快速查看哪些后端文件发生了变化。

3. **打开浏览器**
   等待几秒钟命令行就绪后，点击或复制以下本地链接到浏览器即可测试：
   👉 `http://localhost:5173`

## ⚠️ 重点注意事项
由于本地的所有 API 已经被代理到了**正式生产服务器**：
1. 您在本地 `localhost` 发送的弹幕评论、上传的视频、修改的配置，**都会直接并且真实地落盘到正式服数据库**（va.xruner.tk）。
2. 在本地完成开发测试后，可以通过专门配置好的 `/deploy` 技能进行正式上线打包及部署。
