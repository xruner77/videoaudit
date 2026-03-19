<script setup>
import { onLaunch } from '@dcloudio/uni-app'
import { useAuthStore } from './stores/authStore'

onLaunch(() => {
	const authStore = useAuthStore()
	authStore.init()
})
</script>

<style>
/* ========== 全局样式 ========== */
page {
	background-color: #0f0f1a;
	color: #e0e0e0;
	font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
	font-size: 14px;
	line-height: 1.6;
}

/* 带有顶部发光效果的页面容器 */
.page-container {
	min-height: 100vh;
	background: linear-gradient(180deg, rgba(108, 92, 231, 0.12) 0%, rgba(15, 15, 26, 1) 400rpx, rgba(15, 15, 26, 1) 100%);
	padding-bottom: 60rpx;
}

/* 通用暗色输入框 */
.dark-input {
	background-color: rgba(255, 255, 255, 0.06);
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 12rpx;
	color: #e0e0e0;
	padding: 20rpx 24rpx;
	font-size: 28rpx;
}

.dark-input:focus {
	border-color: #6c5ce7;
}

/* 主按钮 */
.btn-primary {
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	color: #fff;
	border: none;
	border-radius: 12rpx;
	padding: 20rpx 40rpx;
	font-size: 30rpx;
	font-weight: 600;
	text-align: center;
}

.btn-primary:active {
	opacity: 0.85;
	transform: scale(0.98);
}

/* 次要按钮 */
.btn-secondary {
	background: rgba(255, 255, 255, 0.08);
	color: #a0a0b8;
	border: 1px solid rgba(255, 255, 255, 0.12);
	border-radius: 12rpx;
	padding: 20rpx 40rpx;
	font-size: 28rpx;
	text-align: center;
}

/* 危险按钮 */
.btn-danger {
	background: linear-gradient(135deg, #e74c3c, #c0392b);
	color: #fff;
	border: none;
	border-radius: 12rpx;
	padding: 16rpx 32rpx;
	font-size: 26rpx;
}

/* 卡片 */
.card {
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	padding: 24rpx;
	backdrop-filter: blur(10px);
}
</style>
