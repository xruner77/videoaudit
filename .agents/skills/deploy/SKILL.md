---
name: deploy
description: "一键打包并使用 SCP 部署 H5 代码及按需上传后端代码到 va.xruner.tk 服务器"
---

# 部署技能 (Deploy Skill)

当用户要求“部署”或“发布”应用时，运行此技能会将最新的前端代码打包为 H5 版本，并通过 SCP 推送到远程服务器。同时支持按需上传后端的单个或多个特定文件。

## 目标服务器信息

- **服务器地址**: `a2.xruner.tk`
- **前端目标目录**: `/www/wwwroot/va.xruner.tk`
- **后端目录**: `/www/wwwroot/va.xruner.tk/server`
- **SSH 私钥路径**: `~/.ssh/a2.xruner.tk`
- **登录用户**: 默认 `root`

## 执行步骤

### 1. 部署前端 (H5)

每次常规部署前端时，请执行以下命令：

1. **构建生产环境产物**:
   ```powershell
   npm run build:h5
   ```
2. **清理远端旧的前端资源**（避免旧版本带 hash 的 JS/CSS 残留）:
   ```powershell
   ssh -i ~/.ssh/a2.xruner.tk root@a2.xruner.tk "rm -rf /www/wwwroot/va.xruner.tk/assets /www/wwwroot/va.xruner.tk/static /www/wwwroot/va.xruner.tk/index.html"
   ```
3. **使用 SCP 上传完整前端**:
   ```powershell
   scp -i ~/.ssh/a2.xruner.tk -r dist/build/h5/* root@a2.xruner.tk:/www/wwwroot/va.xruner.tk/
   ```

### 2. 按需部署后端 (Server)

服务器上的后端运行环境（PHP / Composer 依赖等）已经配置完备，绝不需要将整个 `server/` 文件夹强制覆盖。
**只能将用户修改过或指定的 PHP 后端文件单独上传到对应位置**。由于是 PHP 环境，通常上传后即刻生效，无需重启服务。

例如，当需要将变动的 `server/src/ApiController.php` 或者 `server/public/index.php` 等单独文件部署时，请根据文件路径使用如下命令上传（需动态替换文件路径）：

```powershell
# 同步单个后端文件示例：
scp -i ~/.ssh/a2.xruner.tk server/<变动的文件名> root@a2.xruner.tk:/www/wwwroot/va.xruner.tk/server/<变动的文件名>

# 例如更新 PHP 接口路由类:
# scp -i ~/.ssh/a2.xruner.tk server/src/ApiController.php root@a2.xruner.tk:/www/wwwroot/va.xruner.tk/server/src/ApiController.php
```

### 3. 完成与验证

各项上传传输结束后，通知用户部署动作已成功执行完。如果更新了后端 PHP 逻辑，新代码会在下一次请求时自动生效；如果是纯前端，则可提示其刷新页面查看最新效果（`https://va.xruner.tk`）。
