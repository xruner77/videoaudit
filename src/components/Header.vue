<template>
	<view class="header-bar">
		<view class="header-left">
			<view class="back-btn" v-if="showBack" @click="goBack">
				<text class="back-icon">❮</text>
			</view>
			<text class="header-title">{{ title }}</text>
		</view>
		<view class="header-right" v-if="authStore.isLoggedIn">
			<text class="header-user" @click="showMenu = !showMenu">{{ authStore.username }}</text>
			<view class="header-menu" v-if="showMenu">
				<view class="menu-item" @click="goUpload">
					<text>📤 上传视频</text>
				</view>
				<view class="menu-item" v-if="authStore.isAdmin" @click="goAdmin">
					<text>⚙️ 后台管理</text>
				</view>
				<view class="menu-item menu-danger" @click="handleLogout">
					<text>🚪 退出登录</text>
				</view>
			</view>
		</view>
		<view class="header-right" v-else>
			<text class="header-login-btn" @click="goLogin">登录</text>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../stores/authStore'

defineProps({
	title: { type: String, default: '视频审片系统' },
	showBack: { type: Boolean, default: false }
})

const authStore = useAuthStore()
const showMenu = ref(false)

function goLogin() {
	uni.navigateTo({ url: '/pages/login/login' })
}

function goUpload() {
	showMenu.value = false
	uni.navigateTo({ url: '/pages/upload/upload' })
}

function goBack() {
	uni.reLaunch({ url: '/pages/videoList/videoList' })
}

function goAdmin() {
	showMenu.value = false
	uni.navigateTo({ url: '/pages/admin/admin' })
}

function handleLogout() {
	showMenu.value = false
	authStore.logout()
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

.back-icon {
	font-size: 36rpx;
	color: #6c5ce7;
	font-weight: bold;
}

.header-title {
	font-size: 34rpx;
	font-weight: 700;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	-webkit-background-clip: text;
	-webkit-text-fill-color: transparent;
}

.header-right {
	position: relative;
}

.header-user {
	color: #a0a0b8;
	font-size: 26rpx;
	padding: 10rpx 20rpx;
	background: rgba(108, 92, 231, 0.15);
	border-radius: 20rpx;
	border: 1px solid rgba(108, 92, 231, 0.3);
}

.header-login-btn {
	color: #6c5ce7;
	font-size: 28rpx;
	font-weight: 600;
}

.header-menu {
	position: absolute;
	top: 70rpx;
	right: 0;
	background: #1e1e36;
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 16rpx;
	min-width: 260rpx;
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.4);
	overflow: hidden;
	z-index: 1000;
}

.menu-item {
	padding: 24rpx 30rpx;
	font-size: 26rpx;
	color: #c0c0d0;
	border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.menu-item:active {
	background: rgba(108, 92, 231, 0.15);
}

.menu-danger text {
	color: #e74c3c;
}
</style>
