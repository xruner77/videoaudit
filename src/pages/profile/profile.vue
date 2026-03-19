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
					<view class="avatar-large">
						<uni-icons type="person-filled" size="36" color="#ffffff" />
					</view>
					<view class="user-details">
						<text class="user-name">{{ authStore.username }}</text>
						<text class="user-role">{{ authStore.isAdmin ? '管理员' : '普通用户' }}</text>
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
					<view class="menu-item" @click="showPasswordModal = true">
						<view class="menu-icon-wrapper" style="background: rgba(46, 204, 113, 0.15);">
							<uni-icons type="locked" size="20" color="#2ecc71" />
						</view>
						<text class="menu-label">修改密码</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
				</view>
			</view>

			<!-- 我的评论 -->
			<view class="section">
				<view class="section-header">
					<text class="section-title">我的评论 (共 {{ myComments.length }} 条，涉及 {{ groupedComments.length }} 个视频)</text>
				</view>

				<view class="comment-group-card" v-for="group in groupedComments" :key="group.video_id" @click="goToMyComments(group.video_id)">
					<view class="group-info">
						<text class="group-video-title">{{ group.video_title || '未知视频' }}</text>
						<view class="group-stats">
							<text class="group-count"><uni-icons type="chat" size="14" color="#a855f7" style="margin-right:6rpx;"/>{{ group.comments.length }} 条评论</text>
							<uni-icons type="right" size="16" color="#555" />
						</view>
					</view>
				</view>

				<view class="empty-state" v-if="!loadingComments && groupedComments.length === 0">
					<uni-icons type="chat" size="40" color="#444" />
					<text class="empty-text">暂无评论</text>
				</view>
			</view>

			<!-- 退出登录 -->
			<view class="logout-section">
				<button class="logout-btn" @click="handleLogout">退出登录</button>
			</view>
		</view>

		<!-- 修改密码弹窗 -->
		<view class="modal-mask" v-if="showPasswordModal" @click.self="showPasswordModal = false">
			<view class="modal-card">
				<text class="modal-title">修改密码</text>

				<view class="form-group">
					<text class="form-label">当前密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="oldPassword" placeholder="请输入当前密码" :password="!showOldPwd" />
						<view class="eye-icon" @click="showOldPwd = !showOldPwd">
							<uni-icons :type="showOldPwd ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<view class="form-group">
					<text class="form-label">新密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="newPassword" placeholder="请输入新密码（至少6位）" :password="!showNewPwd" />
						<view class="eye-icon" @click="showNewPwd = !showNewPwd">
							<uni-icons :type="showNewPwd ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<view class="form-group">
					<text class="form-label">确认新密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="confirmPassword" placeholder="请再次输入新密码" :password="!showConfirmPwd" />
						<view class="eye-icon" @click="showConfirmPwd = !showConfirmPwd">
							<uni-icons :type="showConfirmPwd ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<view class="modal-actions">
					<button class="btn-secondary modal-cancel" @click="closePasswordModal">取消</button>
					<button class="btn-primary modal-confirm" :loading="changingPassword" @click="changePassword">确认修改</button>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'

const authStore = useAuthStore()
const myComments = ref([])
const loadingComments = ref(false)

// 修改密码
const showPasswordModal = ref(false)
const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const showOldPwd = ref(false)
const showNewPwd = ref(false)
const showConfirmPwd = ref(false)
const changingPassword = ref(false)

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
	}
})

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

async function changePassword() {
	if (!oldPassword.value) {
		return uni.showToast({ title: '请输入当前密码', icon: 'none' })
	}
	if (!newPassword.value || newPassword.value.length < 6) {
		return uni.showToast({ title: '新密码至少6个字符', icon: 'none' })
	}
	if (newPassword.value !== confirmPassword.value) {
		return uni.showToast({ title: '两次密码不一致', icon: 'none' })
	}

	changingPassword.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/auth/password`,
			method: 'PUT',
			header: {
				'Content-Type': 'application/json',
				...authStore.getAuthHeader()
			},
			data: {
				old_password: oldPassword.value,
				new_password: newPassword.value
			}
		})
		if (res.statusCode === 200) {
			uni.showToast({ title: '密码修改成功', icon: 'success' })
			closePasswordModal()
		} else {
			throw new Error(res.data?.error || '修改失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message || '修改失败', icon: 'none' })
	} finally {
		changingPassword.value = false
	}
}

function closePasswordModal() {
	showPasswordModal.value = false
	oldPassword.value = ''
	newPassword.value = ''
	confirmPassword.value = ''
	showOldPwd.value = false
	showNewPwd.value = false
	showConfirmPwd.value = false
}

function handleLogout() {
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

function goLogin() {
	uni.navigateTo({ url: '/pages/login/login' })
}

function goUpload() {
	uni.navigateTo({ url: '/pages/upload/upload' })
}

function goAdmin() {
	uni.navigateTo({ url: '/pages/admin/admin' })
}

function goToMyComments(videoId) {
	// 因为无法通过 URL 传递大量数组数据，我们可以把这个视频的评论数据先放进 uni 缓存中，或者直接在新页面重新请求/过滤
	// 简单起见，我们把当前视频的 ID 作为参数传给新页面
	uni.navigateTo({ url: `/pages/myComments/myComments?videoId=${videoId}` })
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
	margin: 20rpx;
	padding: 40rpx;
	background: linear-gradient(135deg, rgba(108, 92, 231, 0.15), rgba(168, 85, 247, 0.1));
	border: 1px solid rgba(108, 92, 231, 0.2);
	border-radius: 24rpx;
}

.user-info {
	display: flex;
	align-items: center;
}

.avatar-large {
	width: 100rpx;
	height: 100rpx;
	border-radius: 50%;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 28rpx;
	box-shadow: 0 8rpx 24rpx rgba(108, 92, 231, 0.4);
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

/* 评论区 */
.section {
	margin: 20rpx;
}

.section-header {
	margin-bottom: 20rpx;
}

.section-title {
	font-size: 30rpx;
	font-weight: 600;
	color: #e0e0e0;
}

.comment-group-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	padding: 24rpx;
	margin-bottom: 16rpx;
	transition: all 0.2s ease;
}

.comment-group-card:active {
	background: rgba(255, 255, 255, 0.06);
}

.group-info {
	display: flex;
	flex-direction: column;
}

.group-video-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #fff;
	margin-bottom: 12rpx;
	display: block;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.group-stats {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.group-count {
	font-size: 24rpx;
	color: #a0a0b8;
	display: flex;
	align-items: center;
}

.comment-timestamp {
	font-size: 22rpx;
	color: #f39c12;
	margin-left: 16rpx;
	flex-shrink: 0;
}

.comment-text {
	font-size: 28rpx;
	color: #c0c0d0;
	display: block;
	line-height: 1.6;
	margin-bottom: 12rpx;
}

.comment-card-bottom {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.comment-date {
	font-size: 22rpx;
	color: #555;
}

.delete-btn {
	font-size: 22rpx;
	color: #e74c3c;
	display: flex;
	align-items: center;
}

.delete-btn:active {
	opacity: 0.7;
}

.empty-state {
	text-align: center;
	padding: 80rpx 0;
}

.empty-text {
	font-size: 28rpx;
	color: #555;
	display: block;
	margin-top: 16rpx;
}

/* 退出登录 */
.logout-section {
	margin: 40rpx 20rpx;
}

.logout-btn {
	background: rgba(231, 76, 60, 0.1);
	border: 1px solid rgba(231, 76, 60, 0.2);
	color: #e74c3c;
	font-size: 28rpx;
	border-radius: 16rpx;
	padding: 24rpx;
}

.logout-btn:active {
	background: rgba(231, 76, 60, 0.2);
}

/* 修改密码弹窗 */
.modal-mask {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.7);
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 9999;
}

.modal-card {
	width: 85%;
	max-width: 640rpx;
	background: #1e1e36;
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 24rpx;
	padding: 40rpx;
}

.modal-title {
	font-size: 34rpx;
	font-weight: 700;
	color: #fff;
	display: block;
	text-align: center;
	margin-bottom: 36rpx;
}

.form-group {
	margin-bottom: 24rpx;
}

.form-label {
	font-size: 24rpx;
	color: #888;
	margin-bottom: 10rpx;
	display: block;
}

.password-input-wrapper {
	position: relative;
	width: 100%;
}

.pr-80 {
	padding-right: 80rpx !important;
}

.eye-icon {
	position: absolute;
	right: 0;
	top: 0;
	width: 80rpx;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.modal-actions {
	display: flex;
	gap: 20rpx;
	margin-top: 32rpx;
}

.modal-cancel {
	flex: 1;
	padding: 20rpx;
	font-size: 28rpx;
}

.modal-confirm {
	flex: 1;
	padding: 20rpx;
	font-size: 28rpx;
}
</style>
