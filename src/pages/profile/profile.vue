<template>
	<view class="page">
		<Header title="我的" />

		<!-- 未登录状态 -->
		<view class="not-logged-in" v-if="!authStore.isLoggedIn">
			<view class="login-prompt-card">
				<view class="prompt-avatar">
					<uni-icons type="person-filled" size="48" color="#555" />
				</view>
				<text class="prompt-text">登录后查看个人中心</text>
				<button class="btn-primary login-btn" @click="goLogin">立即登录</button>
			</view>
		</view>

		<!-- 已登录状态 -->
		<view class="profile-content" v-else>
			<!-- 用户信息卡片 -->
			<view class="user-card">
				<view class="user-info">
					<view class="avatar-large" :style="{ background: getAvatarColor(authStore.username) }">
						<text class="avatar-letter-large">{{ getAvatarLetter(authStore.username) }}</text>
					</view>
					<view class="user-details">
						<text class="user-name">{{ authStore.username }}</text>
						<text class="user-role">{{ authStore.isAdmin ? '管理员' : '普通用户' }}</text>
					</view>
				</view>
			</view>

			<!-- 数据统计入口（普通用户） -->
			<view class="stat-grid" v-if="!authStore.isAdmin">
				<view class="stat-card" @click="goMyVideos">
					<view v-if="loadingVideos" class="skeleton-box stat-num-skeleton"></view>
					<text v-else class="stat-number">{{ videoTotal }}</text>
					<view class="stat-label-row">
						<text class="stat-icon">🎬</text>
						<text class="stat-label">我的视频</text>
					</view>
				</view>
				<view class="stat-card" @click="goMyComments">
					<view v-if="loadingComments" class="skeleton-box stat-num-skeleton"></view>
					<text v-else class="stat-number">{{ commentTotal }}</text>
					<view class="stat-label-row">
						<text class="stat-icon">💬</text>
						<text class="stat-label">我的评论</text>
					</view>
				</view>
			</view>

			<!-- 功能菜单 -->
			<view class="menu-section">
				<view class="menu-card">
					<view class="menu-item" @click="goUpload" v-if="authStore.isLoggedIn && !authStore.isAdmin">
						<view class="menu-icon-wrapper">
							<uni-icons type="cloud-upload" size="20" color="#b8b8b8" />
						</view>
						<text class="menu-label">上传视频</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
					<view class="menu-item" @click="goPasswordChange">
						<view class="menu-icon-wrapper">
							<uni-icons type="locked" size="20" color="#b8b8b8" />
						</view>
						<text class="menu-label">修改密码</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
				</view>
			</view>

			<!-- 退出登录 -->
			<view class="logout-section">
				<button class="logout-btn" @click="handleLogout">
					<text>退出登录</text>
					<!-- 替换为更确切的标准 logout SVG 图标 -->
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#b8b8b8" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-left:12rpx;">
						<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
						<polyline points="16 17 21 12 16 7"></polyline>
						<line x1="21" y1="12" x2="9" y2="12"></line>
					</svg>
				</button>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'
import { getUserColor as getAvatarColor, updateTabBarForRole } from '../../composables/useUtils'
import { request } from '../../composables/useRequest'

function getAvatarLetter(username) {
	return username ? username.charAt(0).toUpperCase() : '?'
}

const authStore = useAuthStore()
const commentTotal = ref(0)
const loadingComments = ref(false)

// 视频列表相关
const videoTotal = ref(0)
const loadingVideos = ref(false)

onShow(() => {
	if (authStore.isLoggedIn) {
		updateTabBarForRole(authStore.isAdmin)
		fetchMyComments()
		fetchVideos()
	}
})

async function fetchVideos() {
	loadingVideos.value = true
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/videos`,
			method: 'GET',
			data: {
				user_id: authStore.user?.id,
				limit: 1 // 仅需要总数，减少数据传输
			}
		})
		if (res.statusCode === 200) {
			videoTotal.value = res.data.total || 0
		}
	} catch (e) {
		console.error(e)
	} finally {
		loadingVideos.value = false
	}
}

async function fetchMyComments() {
	loadingComments.value = true
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/comments/user/${authStore.user.id}`,
			method: 'GET',
			data: {
				limit: 1 // 仅需要总数
			}
		})
		if (res.statusCode === 200) {
			commentTotal.value = res.data.total || 0
		}
	} catch (e) {
		console.error('Failed to fetch my comments:', e)
	} finally {
		loadingComments.value = false
	}
}

function handleLogout() {
	uni.showModal({
		title: '确认退出',
		content: '确定要退出登录吗？',
		success: (res) => {
			if (res.confirm) {
				authStore.logout()
			}
		}
	})
}

function goLogin() {
	uni.navigateTo({ url: '/pages/login/login' })
}

function goUpload() {
	uni.navigateTo({ url: '/pages/upload/upload' })
}

function goPasswordChange() {
	uni.navigateTo({ url: '/pages/profile/changePassword' })
}

function goMyVideos() {
	uni.navigateTo({ url: '/pages/profile/myVideos' })
}

function goMyComments() {
	uni.navigateTo({ url: '/pages/profile/myCommentsList' })
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #0f0f1a;
	padding-bottom: 140rpx;
}

/* 未登录提示 */
.not-logged-in {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 120rpx 40rpx;
}

.login-prompt-card {
	text-align: center;
	padding: 60rpx 40rpx;
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 24rpx;
	width: 100%;
}

.prompt-avatar {
	width: 120rpx;
	height: 120rpx;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.06);
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 30rpx;
}

.prompt-text {
	font-size: 28rpx;
	color: #666;
	display: block;
	margin-bottom: 40rpx;
}

.login-btn {
	width: 60%;
	margin: 0 auto;
}

/* 用户卡片 */
.user-card {
	padding: 60rpx 40rpx;
	background: linear-gradient(180deg, rgba(88, 72, 211, 0.15) 0%, rgba(15, 15, 26, 1) 100%);
}

.user-info {
	display: flex;
	align-items: center;
}

.avatar-large {
	width: 120rpx;
	height: 120rpx;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 32rpx;
	box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.3);
}

.avatar-letter-large {
	font-size: 52rpx;
	color: #fff;
	font-weight: 700;
}

.user-details {
	flex: 1;
}

.user-name {
	font-size: 36rpx;
	font-weight: 700;
	color: #fff;
	display: block;
	margin-bottom: 8rpx;
}

.user-role {
	font-size: 24rpx;
	color: #a0a0b8;
	display: block;
}

/* 功能菜单 */
.menu-section {
	margin: 20rpx;
}

.menu-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 20rpx;
	overflow: hidden;
}

.menu-item {
	display: flex;
	align-items: center;
	padding: 28rpx 30rpx;
	border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.menu-item:last-child {
	border-bottom: none;
}

.menu-item:active {
	background: rgba(255, 255, 255, 0.04);
}

.menu-icon-wrapper {
	width: 56rpx;
	height: 56rpx;
	border-radius: 14rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 24rpx;
}

.menu-label {
	flex: 1;
	font-size: 28rpx;
	color: #d0d0e0;
}

/* 数据统计入口 */
.stat-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 20rpx;
	padding: 0 40rpx;
	margin-top: -10rpx;
	margin-bottom: 20rpx;
}

.stat-card {
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.08);
	border-radius: 20rpx;
	padding: 28rpx 24rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	transition: all 0.2s ease;
}

.stat-card:active {
	background: rgba(255, 255, 255, 0.08);
	transform: scale(0.97);
}

.stat-number {
	font-size: 56rpx;
	font-weight: 400;
	color: #fff;
	line-height: 1.2;
	margin-bottom: 6rpx;
}

.stat-label-row {
	display: flex;
	align-items: center;
}

.stat-icon {
	font-size: 28rpx;
	margin-right: 8rpx;
}

.stat-label {
	font-size: 28rpx;
	color: #888;
}

/* 骨架屏特性 */
@keyframes shimmer {
	0% { background-position: -200% 0; }
	100% { background-position: 200% 0; }
}

.skeleton-box {
	background: linear-gradient(90deg, rgba(255,255,255,0.02) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.02) 75%);
	background-size: 200% 100%;
	animation: shimmer 1.5s infinite;
	border-radius: 8rpx;
}

.stat-num-skeleton {
	width: 80rpx;
	height: 56rpx;
	margin-bottom: 12rpx; /* Align visually with text */
	margin-top: 6rpx;
}

/* 退出登录 */
.logout-section {
	margin: 40rpx 20rpx;
}

.logout-btn {
	background: transparent;
	border: none;
	color: #e5e5e5;
	font-size: 28rpx;
	border-radius: 16rpx;
	padding: 24rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.logout-btn::after {
	border: none;
}

.logout-btn:active {
	opacity: 0.6;
}

</style>
