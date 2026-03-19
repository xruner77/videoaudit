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

			<!-- 数据统计入口 -->
			<view class="stat-grid">
				<view class="stat-card" @click="goMyVideos">
					<text class="stat-number">{{ myVideos.length }}</text>
					<view class="stat-label-row">
						<uni-icons type="videocam" size="14" color="#888" style="margin-right:6rpx;" />
						<text class="stat-label">我的视频</text>
					</view>
				</view>
				<view class="stat-card" @click="goMyComments">
					<text class="stat-number">{{ myComments.length }}</text>
					<view class="stat-label-row">
						<uni-icons type="chat" size="14" color="#888" style="margin-right:6rpx;" />
						<text class="stat-label">我的评论</text>
					</view>
				</view>
			</view>

			<!-- 功能菜单 -->
			<view class="menu-section">
				<view class="menu-card">
					<view class="menu-item" @click="goUpload" v-if="authStore.isLoggedIn">
						<view class="menu-icon-wrapper" style="background: rgba(108, 92, 231, 0.15);">
							<uni-icons type="cloud-upload" size="20" color="#6c5ce7" />
						</view>
						<text class="menu-label">上传视频</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
					<view class="menu-item" @click="goAdmin" v-if="authStore.isAdmin">
						<view class="menu-icon-wrapper" style="background: rgba(52, 152, 219, 0.15);">
							<uni-icons type="gear" size="20" color="#3498db" />
						</view>
						<text class="menu-label">后台管理</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
					<view class="menu-item" @click="goPasswordChange">
						<view class="menu-icon-wrapper" style="background: rgba(46, 204, 113, 0.15);">
							<uni-icons type="locked" size="20" color="#2ecc71" />
						</view>
						<text class="menu-label">修改密码</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
				</view>
			</view>

			<!-- 退出登录 -->
			<view class="logout-section">
				<button class="logout-btn" @click="handleLogout">退出登录</button>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, nextTick } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'

const avatarColors = ['#5b52f6', '#a855f7', '#ec4899', '#f43f5e', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#6366f1']
function getAvatarColor(username) {
	if (!username) return avatarColors[0]
	let hash = 0
	for (let i = 0; i < username.length; i++) {
		hash = username.charCodeAt(i) + ((hash << 5) - hash)
	}
	return avatarColors[Math.abs(hash) % avatarColors.length]
}

function getAvatarLetter(username) {
	return username ? username.charAt(0).toUpperCase() : '?'
}

const authStore = useAuthStore()
const myComments = ref([])
const loadingComments = ref(false)


// 视频列表相关
const allVideos = ref([])
const loadingVideos = ref(false)

const myVideos = computed(() => {
	return allVideos.value.filter(v => v.user_id == authStore.user?.id)
})

// 按视频分组评论
const groupedComments = computed(() => {
	if (!myComments.value || myComments.value.length === 0) return []
	
	const groupMap = new Map()
	myComments.value.forEach(comment => {
		if (!groupMap.has(comment.video_id)) {
			groupMap.set(comment.video_id, {
				video_id: comment.video_id,
				video_title: comment.video_title,
				comments: []
			})
		}
		groupMap.get(comment.video_id).comments.push(comment)
	})
	
	return Array.from(groupMap.values())
})

onShow(() => {
	if (authStore.isLoggedIn) {
		fetchMyComments()
		fetchVideos()
	}
})

async function fetchVideos() {
	loadingVideos.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/videos`,
			method: 'GET'
		})
		if (res.statusCode === 200) {
			allVideos.value = res.data.videos || []
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
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments/user/${authStore.user.id}`,
			method: 'GET',
			header: authStore.getAuthHeader()
		})
		if (res.statusCode === 200) {
			myComments.value = res.data.comments || []
		}
	} catch (e) {
		console.error('Failed to fetch my comments:', e)
	} finally {
		loadingComments.value = false
	}
}


async function handleLogout() {
	uni.showModal({
		title: '确认退出',
		content: '确定要退出登录吗？',
		success: (res) => {
			if (res.confirm) {
				authStore.token = ''
				authStore.user = null
				uni.removeStorageSync('token')
				uni.removeStorageSync('user')
				uni.reLaunch({ url: '/pages/login/login' })
			}
		}
	})
}

async function deleteVideo(id) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除此视频及所有评论吗？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await uni.request({
					url: `${authStore.API_BASE}/api/videos/${id}`,
					method: 'DELETE',
					header: authStore.getAuthHeader()
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					fetchVideos()
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

function goReview(id) {
	uni.navigateTo({ url: `/pages/review/review?id=${id}` })
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

function goToMyComments(videoId) {
	uni.navigateTo({ url: `/pages/myComments/myComments?videoId=${videoId}` })
}

function goMyVideos() {
	// Navigate to profile sub-page showing my videos
	uni.navigateTo({ url: '/pages/profile/myVideos' })
}

function goMyComments() {
	// Navigate to profile sub-page showing my comments grouped by video
	uni.navigateTo({ url: '/pages/profile/myCommentsList' })
}

function formatTime(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}

function formatDate(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getFullYear()}-${(d.getMonth() + 1).toString().padStart(2, '0')}-${d.getDate().toString().padStart(2, '0')} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`
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

.stat-label {
	font-size: 24rpx;
	color: #888;
}

/* 退出登录 */
.logout-section {
	margin: 40rpx 20rpx;
}

.logout-btn {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.08);
	color: #88889a;
	font-size: 28rpx;
	border-radius: 16rpx;
	padding: 24rpx;
}

.logout-btn:active {
	background: rgba(231, 76, 60, 0.2);
}

</style>
