<template>
	<view class="header-bar">
		<view class="header-left">
			<view class="back-btn" v-if="showBack" @click="goBack">
				<uni-icons type="left" size="24" color="#fff" />
			</view>
			<text class="header-title">{{ title }}</text>
		</view>
	</view>
</template>

<script setup>
import { useAuthStore } from '../stores/authStore'

defineProps({
	title: { type: String, default: '视频审片系统' },
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
	justify-content: space-between;
	padding: 0 30rpx;
	height: 88rpx;
	background: linear-gradient(135deg, #1a1a2e, #16213e);
	border-bottom: 1px solid rgba(255, 255, 255, 0.06);
	position: sticky;
	top: 0;
	z-index: 999;
}

.header-left {
	display: flex;
	align-items: center;
}

.back-btn {
	margin-right: 20rpx;
	padding: 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.back-btn:active {
	opacity: 0.7;
}

.header-title {
	font-size: 34rpx;
	font-weight: 700;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
}
</style>
