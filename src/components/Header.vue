<template>
	<view class="header-bar">
		<view class="header-side left">
			<view class="back-btn" v-if="showBack" @click="goBack">
				<uni-icons type="left" size="24" color="#fff" />
			</view>
		</view>
		<view class="header-center">
			<text class="header-title">{{ title }}</text>
		</view>
		<view class="header-side right"></view>
	</view>
</template>

<script setup>
import { useAuthStore } from '../stores/authStore'

defineProps({
	title: { type: String, default: '生活网审片系统' },
	showBack: { type: Boolean, default: false }
})

const authStore = useAuthStore()

function goBack() {
	const pages = getCurrentPages()
	if (pages.length > 1) {
		uni.navigateBack({ delta: 1 })
	} else {
		// 如果只有一页（例如直接打开的链接），默认返回首页
		uni.switchTab({ url: '/pages/videoList/videoList' })
	}
}
</script>

<style scoped>
.header-bar {
	display: flex;
	align-items: center;
	height: 88rpx;
	background: linear-gradient(135deg, #1a1a2e, #16213e);
	border-bottom: 1px solid rgba(255, 255, 255, 0.06);
	position: sticky;
	top: 0;
	z-index: 999;
	padding: 0 10rpx;
}

.header-side {
	width: 100rpx;
	height: 100%;
	display: flex;
	align-items: center;
}

.left {
	justify-content: flex-start;
}

.right {
	justify-content: flex-end;
}

.header-center {
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
}

.back-btn {
	padding: 10rpx 20rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.back-btn:active {
	opacity: 0.7;
}

.header-title {
	font-size: 34rpx;
	color: #fff;
	text-overflow: ellipsis;
	white-space: nowrap;
	overflow: hidden;
	max-width: 450rpx; /* 限制宽度，防止挤压返回按钮 */
	text-align: center;
}
</style>
