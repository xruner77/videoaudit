<template>
	<view class="header-bar">
		<view class="header-left">
			<view class="back-btn" v-if="showBack" @click="goBack">
				<uni-icons type="left" size="24" color="#fff" />
			</view>
			<text class="header-title">{{ title }}</text>
		</view>
		<view class="header-right" v-if="authStore.isLoggedIn">
			<view class="header-user-wrapper" @click="showMenu = !showMenu">
				<view class="user-avatar">
					<uni-icons type="person-filled" size="14" color="#ffffff" />
				</view>
				<text class="header-user-name">{{ authStore.username }}</text>
				<view class="dropdown-icon-wrapper" :class="{ 'rotate': showMenu }">
					<uni-icons type="bars" size="14" color="#a0a0b8" />
				</view>
			</view>
			<view class="header-menu" v-if="showMenu">
				<view class="menu-item" @click="goUpload">
					<text><uni-icons type="cloud-upload" size="16" color="#fff" style="margin-right:8rpx;"/>上传视频</text>
				</view>
				<view class="menu-item" v-if="authStore.isAdmin" @click="goAdmin">
					<text><uni-icons type="gear" size="16" color="#fff" style="margin-right:8rpx;"/>后台管理</text>
				</view>
				<view class="menu-item menu-danger" @click="handleLogout">
					<text><uni-icons type="info" size="16" color="#ff4d4f" style="margin-right:8rpx;"/>退出登录</text>
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
	display: flex;
	align-items: center;
}

.header-user-wrapper {
	display: flex;
	align-items: center;
	padding: 10rpx 0rpx;
	transition: all 0.3s ease;
	cursor: pointer;
}

.header-user-wrapper:active {
	opacity: 0.8;
}

.user-avatar {
	width: 36rpx;
	height: 36rpx;
	border-radius: 50%;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 10rpx;
	box-shadow: 0 4rpx 12rpx rgba(108, 92, 231, 0.3);
}

.header-user-name {
	color: #ffffff;
	font-size: 26rpx;
	font-weight: 500;
	margin-right: 12rpx;
	max-width: 160rpx;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dropdown-icon-wrapper {
	display: flex;
	align-items: center;
	transition: transform 0.3s ease;
}

.dropdown-icon-wrapper.rotate {
	transform: rotate(180deg);
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
