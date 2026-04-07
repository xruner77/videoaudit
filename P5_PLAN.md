# P5 — 拆分 review.vue（2062 行 → ~5 个文件）

## 背景

`review.vue` 当前有 2062 行，包含视频播放器、进度条、评论列表、评论输入、排序弹窗等 6+ 个独立职责，维护困难。需要拆分为可维护的子组件。

## 拆分方案

与 REFACTOR_LOG 中原始建议略有不同 — 重新评估后，**排序弹窗** 应归入评论列表组件，**视频元信息** 可以内联保留（仅 17 行模板，不值得单独组件）。

### 跨组件通信策略

> [!IMPORTANT]
> 拆分的核心难点是跨组件状态共享。方案：使用 **props + emits** 模式，不引入 provide/inject 或新 store，最小化架构变更。
>
> - 所有播放状态（`currentTime`, `duration`, `isPlaying` 等）保留在 `review.vue` 中
> - 子组件通过 props 接收数据，通过 emits 发出事件
> - `videoContext` 操作全部保留在 `review.vue`，子组件仅发 emit 请求操作

---

## Proposed Changes

### 审片页面子组件

#### [NEW] [VideoPlayer.vue](file:///d:/tfx/myapp/videoaudit/src/pages/review/components/VideoPlayer.vue)

视频播放器 + 自定义控制条（含进度条和评论打点）。

**包含内容**（原 review.vue 第 7~105 行模板 + 相关 CSS）：
- 视频 `<video>` 元素 + loading overlay + 居中播放按钮
- 进度条 + 评论打点（comment dots）
- 控制条底部（时间显示、播放/暂停/快退/快进、倍速、全屏）

**Props**: `videoUrl`, `isPlaying`, `isLoading`, `currentTime`, `duration`, `playbackRate`, `isFullscreen`, `isRotated`, `commentDots`, `activeCommentId`, `seekMessage`, `progressPercent`

**Emits**: `togglePlay`, `skip`, `cycleSpeed`, `toggleFullscreen`, `seekTo`, `timeupdate`, `play`, `pause`, `ended`, `metaloaded`, `progressTouchStart`, `progressTouchMove`, `progressTouchEnd`, `progressClick`

**预估行数**: ~450 行（模板 100 + 脚本 50 + CSS 350）

---

#### [NEW] [CommentForm.vue](file:///d:/tfx/myapp/videoaudit/src/pages/review/components/CommentForm.vue)

评论输入区域（含回复提示 + 图片上传预览）。

**包含内容**（原 review.vue 第 127~167 行模板 + 相关逻辑和 CSS）：
- 回复提示栏 + 取消回复
- 输入框 + 图片选择按钮 + 发送按钮
- 图片预览行（含上传进度）
- 时间戳标记提示
- 未登录提示

**Props**: `isLoggedIn`, `replyTo`, `commentTimestamp`, `videoId`, `submitting`

**Emits**: `submit`, `cancelReply`, `pauseForComment`, `update:commentTimestamp`

**预估行数**: ~250 行（模板 40 + 脚本 120 + CSS 90）

---

#### [NEW] [CommentThread.vue](file:///d:/tfx/myapp/videoaudit/src/pages/review/components/CommentThread.vue)

评论列表 + 排序 + 子评论展开/折叠 + 排序弹窗。

**包含内容**（原 review.vue 第 169~297 行模板 + 排序弹窗 + 相关逻辑和 CSS）：
- 列表头（标题 + 排序按钮）
- 父评论渲染（头像 + 用户名 + 时间戳 + 内容 + 图片 + 操作）
- 子评论展平显示 + 展开/折叠控制
- 加载更多 / 空状态
- 排序弹窗 (popup)

**Props**: `comments` (原始 dataList), `activeCommentId`, `isAdmin`, `currentUserId`, `loadingComments`, `hasMoreComments`

**Emits**: `seekTo`, `reply`, `delete`, `loadMore`

**预估行数**: ~600 行（模板 130 + 脚本 120 + CSS 350）

---

#### [MODIFY] [review.vue](file:///d:/tfx/myapp/videoaudit/src/pages/review/review.vue)

页面容器 + 数据获取 + 状态管理 + 协调子组件。

**保留内容**：
- `onLoad` / `onShow` / `onUnmounted` 生命周期
- `fetchVideoDetail()` / `fetchMarkers()` / `incrementViewCount()` API 调用
- 全部响应式状态定义
- `videoContext` 操作函数（`togglePlay`, `skip`, `seekTo`, `cycleSpeed`, `toggleFullscreen`）
- 进度条事件处理（`updateProgressByEvent` 等）
- 视频元信息区域（仅 17 行模板，保留内联）

**预估行数**: ~400 行

---

## 文件结构

```
src/pages/review/
├── review.vue                (~400行，原2062行)
└── components/
    ├── VideoPlayer.vue       (~450行)
    ├── CommentForm.vue       (~250行)
    └── CommentThread.vue     (~600行)
```

## Open Questions

> [!IMPORTANT]
> **进度条触摸事件处理**：`updateProgressByEvent` 函数使用了 `uni.createSelectorQuery().in(instance.proxy)` 获取进度条元素位置。拆分到 `VideoPlayer.vue` 后，此函数需要使用子组件自身的 instance。是否将此函数直接放入 `VideoPlayer.vue`？（建议是，逻辑上属于播放器）

> [!NOTE]
> 处理方式：将 `updateProgressByEvent` 及 `isDragging` 状态移入 `VideoPlayer.vue`，通过 emit 将 seek 结果和 currentTime 更新传回父组件。

---

## 审计发现 (Plan Audit)

### ✅ 全局冲突检查

发现 **2 个全局副作用**，均已有缓解方案：

| # | 问题 | 风险 | 缓解方案 |
|---|------|------|----------|
| 1 | `document.addEventListener('fullscreenchange', ...)` — 全局监听器 | 拆分后如果放入 VideoPlayer 子组件，需确保在子组件 `onUnmounted` 中清理 | **保留在 review.vue** 中管理，因为 `isFullscreen` 状态归父组件所有 |
| 2 | `.is-fullscreen` CSS 使用 `position: fixed` 覆盖整个视口 | scoped CSS 在子组件中可能无法影响父级布局 | `is-fullscreen` class 绑定在 review.vue 的 `<view>` 上，而非子组件内部，故 **review.vue 保留全屏相关 CSS** |

### ✅ 生命周期安全检查

| # | 问题 | 缓解方案 |
|---|------|----------|
| 3 | `uni.createVideoContext('reviewVideo')` 使用 DOM ID 寻找元素 — 拆到 VideoPlayer 后，`<video id="reviewVideo">` 在子组件模板中，但 `createVideoContext` 可能需要 component instance 上下文 | **`videoContext` 在 review.vue 的 `onLoad` 中创建**，传入 `getCurrentInstance()` 上下文。在 uni-app H5 中 `createVideoContext(id)` 全局查找 DOM，不受组件层级影响。需要验证。备选方案：子组件通过 `defineExpose` 暴露 videoEl ref |
| 4 | `onReachBottom` 是 **页面级** hook，只能在 page 组件中使用 | ✅ 已正确保留在 review.vue 中，通过 emit 触发 CommentThread 的加载更多 |
| 5 | `bufferTimer` (setInterval) 和 `seekTimer` (setTimeout) 清理 | ✅ 保留在 review.vue 或 VideoPlayer 中，`onUnmounted` 清理。**注意**：如果 bufferTimer 移入 VideoPlayer，VideoPlayer 也需要 `onUnmounted` 清理 |

### ✅ 权限检查

无影响。RBAC 逻辑（`authStore.isAdmin`, `c.user_id == authStore.user?.sub`）在拆分后通过 props 传递，不改变判断逻辑。

### ✅ 性能损耗评估

| 维度 | 影响 |
|------|------|
| 内存 | 无增加 — 组件拆分不增加状态 |
| 渲染 | props 变化触发子组件 re-render，但 Vue 的 patch 算法高效处理，无感知 |
| 首屏 | 无影响 — 子组件随页面同步加载，无 lazy import |

### ⚠️ 需特别注意的实施细节

> [!WARNING]
> **CommentForm 的 `pauseForComment`**：当用户 focus 输入框时，需要暂停视频。此操作需要 `videoContext.pause()`。拆分后 CommentForm 无法直接访问 `videoContext`。
> 
> **方案**：CommentForm emit `pauseForComment` 事件，review.vue 监听后执行 `videoContext.pause()` 并更新 `commentTimestamp`。

> [!WARNING]
> **CommentForm 的 `submitComment`**：成功后需要调用 `resetComments()` + `loadNextPage()` + `fetchMarkers()` 来刷新数据。
>
> **方案**：将 `submitComment` 逻辑保留在 review.vue 中，CommentForm 仅收集表单数据（text, images, replyTo）并 emit `submit` 事件。review.vue 完成提交和数据刷新。图片上传逻辑可放入 CommentForm 内部。

### 审计结论

**✅ 方案可执行，未发现阻塞性问题。** 上述 5 个风险点均有明确缓解方案，在实施中注意即可。

---

## Verification Plan

### Automated Tests
- `npm run build:h5` 编译通过零错误
- 部署后验证：视频播放、进度条拖动、评论打点跳转、评论发送、回复、删除、排序、全屏 均正常

### Manual Verification
- 对比拆分前后的 UI 和交互行为，确保完全一致

### 回归测试清单
- [ ] 视频播放/暂停/快退/快进
- [ ] 进度条拖动 + 触摸跳转
- [ ] 评论打点（圈）点击跳转
- [ ] 全屏 + ESC 退出
- [ ] 倍速切换
- [ ] 评论发送（文字 + 图片）
- [ ] 回复评论 + 取消回复
- [ ] 删除评论（自己的 + 管理员）
- [ ] 排序切换（4 种排序）
- [ ] 子评论展开/折叠
- [ ] 触底加载更多评论
- [ ] 未登录提示
