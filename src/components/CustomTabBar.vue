<template>
	<view class="tab-bar">
		<view class="tab-item" @click="switchTab('/pages/videoList/videoList')">
			<uni-icons 
				type="home-filled" 
				size="24" 
				:color="activePath === '/pages/videoList/videoList' ? '#a855f7' : '#666'" 
			/>
			<text :class="['tab-text', activePath === '/pages/videoList/videoList' && 'active']">首页</text>
		</view>

		<view class="upload-btn-wrapper" @click="goUpload">
			<view class="upload-btn">
				<uni-icons type="plusempty" size="28" color="#fff" />
			</view>
			<text class="tab-text">上传</text>
		</view>

		<view class="tab-item" @click="switchTab('/pages/profile/profile')">
			<uni-icons 
				type="person-filled" 
				size="24" 
				:color="activePath === '/pages/profile/profile' ? '#a855f7' : '#666'" 
			/>
			<text :class="['tab-text', activePath === '/pages/profile/profile' && 'active']">我的</text>
		</view>
	</view>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/authStore'

const props = defineProps({
	activePath: {
		type: String,
		required: true
	}
})

const authStore = useAuthStore()

function switchTab(url) {
	if (props.activePath === url) return
	uni.switchTab({ url })
}

function goUpload() {
	if (!authStore.isLoggedIn) {
		uni.showToast({ title: '请先登录', icon: 'none' })
		setTimeout(() => {
			uni.navigateTo({ url: '/pages/login/login' })
		}, 800)
		return
	}
	uni.navigateTo({ url: '/pages/upload/upload' })
}
</script>

<style scoped>
.tab-bar {
	position: fixed;
	bottom: 0;
	left: 0;
	right: 0;
	height: 110rpx;
	background: #1a1a2e;
	display: flex;
	border-top: 1px solid rgba(255, 255, 255, 0.05);
	padding-bottom: env(safe-area-inset-bottom);
	z-index: 999;
}

.tab-item {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding-top: 10rpx;
}

.tab-text {
	font-size: 20rpx;
	color: #666;
	margin-top: 4rpx;
}

.tab-text.active {
	color: #a855f7;
}

.upload-btn-wrapper {
	flex: 1;
	display: flex;
	flex-direction: column;
	align-items: center;
	position: relative;
	margin-top: -40rpx;
}

.upload-btn {
	width: 100rpx;
	height: 100rpx;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 8rpx 20rpx rgba(108, 92, 231, 0.4);
	margin-bottom: 4rpx;
	border: 6rpx solid #1a1a2e;
}

.upload-btn:active {
	transform: scale(0.92);
}
</style>
