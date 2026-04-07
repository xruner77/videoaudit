<template>
	<view class="page-container">
		<Header title="管理后台" />

		<view class="admin-container" v-if="authStore.isAdmin">
			<!-- 管理 Tab -->
			<view class="admin-tabs">
				<view class="tab-header">
					<view
						:class="['tab-item', tab === 'dashboard' && 'tab-active']"
						@click="switchTab('dashboard')"
					>
						概览
					</view>
					<view
						:class="['tab-item', tab === 'videos' && 'tab-active']"
						@click="switchTab('videos')"
					>
						视频
					</view>
					<view
						:class="['tab-item', tab === 'comments' && 'tab-active']"
						@click="switchTab('comments')"
					>
						评论
					</view>
					<view
						:class="['tab-item', tab === 'users' && 'tab-active']"
						@click="switchTab('users')"
					>
						用户
					</view>
				</view>
				<view class="tab-indicator-container">
					<view :class="['tab-indicator', 'tab-pos-' + tab]"></view>
				</view>
			</view>

			<!-- ==================== 概览 Dashboard ==================== -->
			<view v-if="tab === 'dashboard'" class="dashboard-section">
				<!-- 统计卡片 -->
				<view class="stats-row">
					<view class="stat-card stat-purple" @click="switchTab('videos')">
						<view class="stat-icon-wrap">
							<uni-icons type="videocam" size="22" color="#fff" />
						</view>
						<view class="stat-body">
							<text class="stat-number">{{ dashStats.videos }}</text>
							<text class="stat-label">视频总数</text>
						</view>
					</view>
					<view class="stat-card stat-blue" @click="switchTab('comments')">
						<view class="stat-icon-wrap">
							<uni-icons type="chat" size="22" color="#fff" />
						</view>
						<view class="stat-body">
							<text class="stat-number">{{ dashStats.comments }}</text>
							<text class="stat-label">评论总数</text>
						</view>
					</view>
					<view class="stat-card stat-green" @click="switchTab('users')">
						<view class="stat-icon-wrap">
							<uni-icons type="person" size="22" color="#fff" />
						</view>
						<view class="stat-body">
							<text class="stat-number">{{ dashStats.users }}</text>
							<text class="stat-label">用户总数</text>
						</view>
					</view>
				</view>

				<!-- 最近视频 -->
				<view class="recent-section">
					<view class="section-header">
						<text class="section-title">最近视频</text>
						<text class="section-more" @click="switchTab('videos')">查看全部 →</text>
					</view>
					<view v-if="recentVideos.length === 0" class="empty-hint">
						<text>暂无视频</text>
					</view>
					<VideoCard
						v-else
						v-for="v in recentVideos"
						:key="v.id"
						:video="v"
						@click="goReview(v.id)"
					/>
				</view>

				<!-- 最近评论 -->
				<view class="recent-section">
					<view class="section-header">
						<text class="section-title">最近评论</text>
						<text class="section-more" @click="switchTab('comments')">查看全部 →</text>
					</view>
					<view v-if="recentComments.length === 0" class="empty-hint">
						<text>暂无评论</text>
					</view>
					<CommentCard
						v-else
						v-for="c in recentComments"
						:key="c.id"
						:comment="c"
						:clickable="true"
						@click="goReview(c.video_id)"
					/>
				</view>
			</view>

			<!-- ==================== 视频管理 ==================== -->
			<view v-if="tab === 'videos'">
				<view class="filter-section">
					<view class="search-box-wrapper">
						<uni-icons type="search" size="18" color="#888" class="search-icon" />
						<input
							class="search-input"
							v-model="videoQuery"
							placeholder="搜索视频标题..."
							confirm-type="search"
							@confirm="onSearchVideos"
						/>
						<view class="clear-btn" v-if="videoQuery" @click="handleClearVideoQuery">
							<uni-icons type="closeempty" size="14" color="#888" />
						</view>
					</view>
				</view>

				<VideoCard
					v-for="v in videoList"
					:key="v.id"
					:video="v"
					:showDelete="true"
					@click="goReview(v.id)"
					@delete="deleteVideo(v.id)"
				/>
				<view class="load-more-status" v-if="videoList.length > 0">
					<text v-if="loadingVideos">正在加载...</text>
					<text v-else-if="hasMoreVideos" @click="loadNextVideos">加载更多视频</text>
					<text v-else>—— 已加载全部 ——</text>
				</view>
				<view class="empty-state" v-if="videoList.length === 0 && loadingVideos">
					<text>正在加载...</text>
				</view>
				<view class="empty-state" v-else-if="videoList.length === 0 && !loadingVideos">
					<text>暂无相关视频</text>
				</view>
			</view>

			<!-- ==================== 评论管理 ==================== -->
			<view v-if="tab === 'comments'">
				<view class="filter-section">
					<view class="search-box-wrapper">
						<uni-icons type="search" size="18" color="#888" class="search-icon" />
						<input
							class="search-input"
							v-model="videoSearchQuery"
							placeholder="按视频标题过滤..."
							@focus="showSearchResult = true"
							@input="showSearchResult = true"
						/>
						<view class="clear-btn" v-if="videoSearchQuery" @click="handleClearVideoFilter">
							<uni-icons type="closeempty" size="14" color="#888" />
						</view>
					</view>

					<!-- 结果下拉列表 -->
					<view class="search-results-list" v-if="showSearchResult && filteredVideoOptions.length > 0">
						<view
							class="search-result-item"
							:class="{ active: !selectedVideoId }"
							@click="selectVideoFilter(null)"
						>
							<text>全部视频</text>
						</view>
						<view
							class="search-result-item"
							v-for="v in filteredVideoOptions"
							:key="v.id"
							:class="{ active: selectedVideoId === v.id }"
							@click="selectVideoFilter(v)"
						>
							<view class="admin-video-icon small-icon">
								<uni-icons type="videocam" size="16" color="#6c5ce7" />
							</view>
							<view class="result-info">
								<text class="result-title">{{ v.title }} <text style="font-size: 20rpx; color: #888; font-weight: normal; margin-left: 8rpx;">({{ v.comment_count || 0 }})</text></text>
								<text class="result-uploader">{{ v.uploader || '系统' }}</text>
							</view>
						</view>
					</view>
					<view class="search-mask" v-if="showSearchResult" @click="showSearchResult = false"></view>
				</view>

				<CommentCard
					v-for="c in commentList"
					:key="c.id"
					:comment="c"
					:showDelete="true"
					:clickable="true"
					@click="goReview(c.video_id)"
					@delete="deleteComment(c.id)"
				/>

				<view class="load-more-status" v-if="commentList.length > 0">
					<text v-if="loadingComments">正在加载...</text>
					<text v-else-if="hasMoreComments" @click="loadNextComments">加载更多评论</text>
					<text v-else>—— 已加载全部 ——</text>
				</view>

				<view class="empty-state" v-if="commentList.length === 0 && loadingComments">
					<text>正在加载...</text>
				</view>
				<view class="empty-state" v-else-if="commentList.length === 0 && !loadingComments">
					<text>暂无相关评论</text>
				</view>
			</view>

			<!-- ==================== 用户管理 ==================== -->
			<view v-if="tab === 'users'">
				<!-- 用户列表 -->
				<view class="user-list-header">
					<text class="section-title">用户列表 ({{ userList.length }})</text>
					<view class="create-user-inline-btn" @click="showCreateUser = true">
						<uni-icons type="plusempty" size="14" color="#fff" style="margin-right:4rpx;" />新建
					</view>
				</view>
				<view class="admin-item-card" v-for="u in userList" :key="u.id">
					<view class="admin-item-header">
						<view class="user-avatar-small" :style="{ background: getUserColor(u.username) }">
							<text class="avatar-letter-s">{{ u.username ? u.username.charAt(0).toUpperCase() : '?' }}</text>
						</view>
						<view class="admin-item-info">
							<text class="admin-item-title">{{ u.username }}</text>
							<view class="admin-item-meta">
								<view :class="['role-badge', u.role === 'admin' ? 'role-admin' : 'role-user']">
									<text>{{ u.role === 'admin' ? '管理员' : '审片员' }}</text>
								</view>
								<view class="meta-tag">
									<text>{{ formatDateSimple(u.created_at) }}</text>
								</view>
							</view>
						</view>
						<view class="user-actions-row" v-if="u.id != authStore.user?.id">
							<text class="btn-link neutral small-text" @click="openResetPassword(u.id, u.username)">
								<uni-icons type="locked" size="14" color="#a0a0b8" style="margin-right:6rpx;" />重置密码
							</text>
							<text class="btn-link danger small-text" @click="deleteUser(u.id, u.username)">
								<uni-icons type="trash" size="14" color="#e74c3c" style="margin-right:6rpx;" />删除
							</text>
						</view>
					</view>
				</view>
				<view class="empty-state" v-if="userList.length === 0 && !loadingUsers">
					<text>暂无用户</text>
				</view>

				<!-- 创建用户弹层 -->
				<view class="modal-mask" v-if="showCreateUser" @click="showCreateUser = false">
					<view class="modal-content" @click.stop>
						<view class="modal-header">
							<text class="modal-title">创建新用户</text>
							<view class="modal-close" @click="showCreateUser = false">
								<uni-icons type="closeempty" size="18" color="#999" />
							</view>
						</view>
						<view class="form-group">
							<text class="form-label">用户名</text>
							<input class="dark-input" v-model="newUsername" placeholder="2-20个字符" maxlength="20" />
						</view>
						<view class="form-group">
							<text class="form-label">密码</text>
							<input class="dark-input" v-model="newPassword" placeholder="至少6个字符" maxlength="32" />
						</view>
						<view class="form-group">
							<text class="form-label">角色</text>
							<view class="role-selector">
								<view :class="['role-option', newRole === 'user' && 'role-active']" @click="newRole = 'user'">审片员</view>
								<view :class="['role-option', newRole === 'admin' && 'role-active']" @click="newRole = 'admin'">管理员</view>
							</view>
						</view>
						<button class="btn-primary create-user-btn" :loading="creatingUser" @click="createUser">创建用户</button>
					</view>
				</view>

				<!-- 重置密码弹层 -->
				<view class="modal-mask" v-if="showResetPassword" @click="showResetPassword = false">
					<view class="modal-content" @click.stop>
						<view class="modal-header">
							<text class="modal-title">重置密码</text>
							<view class="modal-close" @click="showResetPassword = false">
								<uni-icons type="closeempty" size="18" color="#999" />
							</view>
						</view>
						<view class="reset-user-hint">
							<text>为用户「{{ resetTargetName }}」设置新密码</text>
						</view>
						<view class="form-group">
							<text class="form-label">新密码</text>
							<input class="dark-input" v-model="resetNewPassword" placeholder="至少6个字符" maxlength="32" />
						</view>
						<button class="btn-primary create-user-btn" :loading="resettingPassword" @click="resetPassword">确认重置</button>
					</view>
				</view>
			</view>
		</view>

		<!-- 无权限 -->
		<view class="no-access" v-else>
			<text class="no-access-icon">🔒</text>
			<text class="no-access-text">需要管理员权限</text>
			<button class="btn-secondary" @click="goBack">返回</button>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import Header from '@/components/Header.vue'
import VideoCard from '@/components/VideoCard.vue'
import CommentCard from '@/components/CommentCard.vue'
import { useAuthStore } from '@/stores/authStore'
import { usePagination } from '@/composables/usePagination'
import { formatDateSimple, getUserColor, updateTabBarForRole } from '@/composables/useUtils'

const authStore = useAuthStore()

const tab = ref('dashboard')
const videoOptions = ref([])
const selectedVideoId = ref(null)

// ==================== Dashboard ====================
const dashStats = ref({ videos: 0, comments: 0, users: 0 })
const recentVideos = ref([])
const recentComments = ref([])

async function fetchDashboard() {
	try {
		// 并行请求：视频总数+最近5条、评论总数+最近5条、用户列表
		const [videoRes, commentRes, userRes] = await Promise.all([
			uni.request({
				url: `${authStore.API_BASE}/api/videos`,
				method: 'GET',
				header: authStore.getAuthHeader(),
				data: { limit: 5 }
			}),
			uni.request({
				url: `${authStore.API_BASE}/api/admin/comments`,
				method: 'GET',
				header: authStore.getAuthHeader(),
				data: { limit: 5 }
			}),
			uni.request({
				url: `${authStore.API_BASE}/api/admin/users`,
				method: 'GET',
				header: authStore.getAuthHeader()
			})
		])

		if (videoRes.statusCode === 200) {
			dashStats.value.videos = videoRes.data.total || 0
			recentVideos.value = videoRes.data.videos || []
		}
		if (commentRes.statusCode === 200) {
			dashStats.value.comments = commentRes.data.total || 0
			recentComments.value = commentRes.data.comments || []
		}
		if (userRes.statusCode === 200) {
			const users = userRes.data.users || []
			dashStats.value.users = users.length
			// 同时填充用户列表缓存，切换到用户 tab 时不用再请求
			userList.value = users
		}
	} catch (e) {
		console.error('Dashboard fetch failed:', e)
	}
}

// ==================== User management ====================
const userList = ref([])
const loadingUsers = ref(false)
const newUsername = ref('')
const newPassword = ref('')
const newRole = ref('user')
const creatingUser = ref(false)
const showCreateUser = ref(false)
const showResetPassword = ref(false)
const resetTargetId = ref(null)
const resetTargetName = ref('')
const resetNewPassword = ref('')
const resettingPassword = ref(false)

// ==================== Video pagination ====================
const videoQuery = ref('')
const {
	dataList: videoList,
	loading: loadingVideos,
	hasMore: hasMoreVideos,
	loadNextPage: loadNextVideos,
	reset: resetVideos
} = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/videos`,
		method: 'GET',
		header: authStore.getAuthHeader(),
		data: {
			...params,
			q: videoQuery.value
		}
	})
	if (res.statusCode === 200) {
		return { data: res.data.videos, total: res.data.total }
	}
	return { data: [], total: 0 }
}, { limit: 20 })

// ==================== Comment pagination ====================
const videoSearchQuery = ref('')
const commentSearchQuery = ref('')
const showSearchResult = ref(false)

const {
	dataList: commentList,
	loading: loadingComments,
	hasMore: hasMoreComments,
	loadNextPage: loadNextComments,
	reset: resetComments
} = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/admin/comments`,
		method: 'GET',
		header: authStore.getAuthHeader(),
		data: {
			...params,
			q: commentSearchQuery.value,
			video_id: selectedVideoId.value || undefined
		}
	})
	if (res.statusCode === 200) {
		return { data: res.data.comments, total: res.data.total }
	}
	return { data: [], total: 0 }
}, { limit: 20 })

onShow(() => {
	if (!authStore.isAdmin) return
	updateTabBarForRole(true)
	fetchDashboard()
	fetchVideoOptions()
})

onReachBottom(() => {
	if (tab.value === 'videos') loadNextVideos()
	else if (tab.value === 'comments') loadNextComments()
})

function switchTab(newTab) {
	tab.value = newTab
	if (newTab === 'dashboard') {
		fetchDashboard()
	} else if (newTab === 'videos') {
		if (videoList.value.length === 0) refreshVideos()
	} else if (newTab === 'comments') {
		if (commentList.value.length === 0) refreshComments()
	} else if (newTab === 'users') {
		if (userList.value.length === 0) fetchUsers()
	}
}



async function fetchVideoOptions() {
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/videos/all-names`,
			header: authStore.getAuthHeader()
		})
		if (res.data && res.data.videos) {
			videoOptions.value = res.data.videos
		}
	} catch (e) {
		console.error('Fetch video options failed:', e)
	}
}

const filteredVideoOptions = computed(() => {
	if (!videoSearchQuery.value) return videoOptions.value
	const q = videoSearchQuery.value.toLowerCase()
	return videoOptions.value.filter(v =>
		v.title.toLowerCase().includes(q) ||
		(v.uploader && v.uploader.toLowerCase().includes(q))
	)
})

function refreshVideos() {
	resetVideos()
	loadNextVideos()
}

function onSearchVideos() { refreshVideos() }

function refreshComments() { resetComments(); loadNextComments() }

function handleClearVideoQuery() { videoQuery.value = ''; refreshVideos() }

function selectVideoFilter(video) {
	if (!video) { selectedVideoId.value = null; videoSearchQuery.value = '' }
	else { selectedVideoId.value = video.id; videoSearchQuery.value = video.title }
	showSearchResult.value = false; refreshComments()
}

function handleClearVideoFilter() { videoSearchQuery.value = ''; selectedVideoId.value = null; refreshComments() }

async function deleteVideo(id) {
	uni.showModal({
		title: '管理员操作',
		content: '确定要删除此视频及关联的所有评论？',
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
					refreshVideos()
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

async function deleteComment(id) {
	uni.showModal({
		title: '管理员操作',
		content: '确定要删除此评论？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await uni.request({
					url: `${authStore.API_BASE}/api/comments/${id}`,
					method: 'DELETE',
					header: authStore.getAuthHeader()
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					refreshComments()
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

function goBack() {
	uni.navigateBack()
}



async function fetchUsers() {
	loadingUsers.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/admin/users`,
			method: 'GET',
			header: authStore.getAuthHeader()
		})
		if (res.statusCode === 200) {
			userList.value = res.data.users || []
		}
	} catch (e) {
		console.error('Fetch users failed:', e)
	} finally {
		loadingUsers.value = false
	}
}

async function createUser() {
	if (!newUsername.value.trim()) {
		return uni.showToast({ title: '请输入用户名', icon: 'none' })
	}
	if (!newPassword.value || newPassword.value.length < 6) {
		return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
	}

	creatingUser.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/admin/users`,
			method: 'POST',
			header: {
				'Content-Type': 'application/json',
				...authStore.getAuthHeader()
			},
			data: {
				username: newUsername.value.trim(),
				password: newPassword.value,
				role: newRole.value
			}
		})
		if (res.statusCode === 201) {
			uni.showToast({ title: '用户创建成功', icon: 'success' })
			newUsername.value = ''
			newPassword.value = ''
			newRole.value = 'user'
			showCreateUser.value = false
			fetchUsers()
		} else {
			throw new Error(res.data?.error || '创建失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message, icon: 'none' })
	} finally {
		creatingUser.value = false
	}
}

async function deleteUser(id, username) {
	uni.showModal({
		title: '管理员操作',
		content: `确定要删除用户「${username}」及其所有数据？`,
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await uni.request({
					url: `${authStore.API_BASE}/api/admin/users/${id}`,
					method: 'DELETE',
					header: authStore.getAuthHeader()
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					fetchUsers()
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

function openResetPassword(id, username) {
	resetTargetId.value = id
	resetTargetName.value = username
	resetNewPassword.value = ''
	showResetPassword.value = true
}

async function resetPassword() {
	if (!resetNewPassword.value || resetNewPassword.value.length < 6) {
		return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
	}
	resettingPassword.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/admin/users/${resetTargetId.value}/reset-password`,
			method: 'PUT',
			header: { 'Content-Type': 'application/json', ...authStore.getAuthHeader() },
			data: { password: resetNewPassword.value }
		})
		if (res.statusCode === 200) {
			uni.showToast({ title: '密码已重置', icon: 'success' })
			showResetPassword.value = false
		} else {
			throw new Error(res.data?.error || '重置失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message, icon: 'none' })
	} finally {
		resettingPassword.value = false
	}
}
</script>

<style scoped>

.admin-container {
	padding: 0 30rpx 140rpx;
}

/* ==================== Tabs ==================== */
.admin-tabs {
	position: sticky;
	top: 88rpx;
	z-index: 110;
	padding-top: 10rpx;
	padding-bottom: 10rpx;
	margin-bottom: 0;
	background: #1a1a2e !important;
	margin-left: -30rpx;
	margin-right: -30rpx;
	padding-left: 30rpx;
	padding-right: 30rpx;
}

.tab-header {
	display: flex;
	justify-content: space-around;
	padding: 10rpx 0;
}

.tab-item {
	font-size: 26rpx;
	color: #777;
	padding: 20rpx 0;
	transition: all 0.3s ease;
	font-weight: 500;
}

.tab-active {
	color: #fff;
	font-weight: 600;
}

.tab-indicator-container {
	height: 4rpx;
	background: rgba(255, 255, 255, 0.05);
	border-radius: 2rpx;
	position: relative;
}

.tab-indicator {
	position: absolute;
	top: 0;
	left: 0;
	width: 25%;
	height: 100%;
	background: linear-gradient(90deg, #6c5ce7, #a855f7);
	border-radius: 2rpx;
	transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	transform: translateX(0);
}

.tab-pos-dashboard {
	transform: translateX(0);
}

.tab-pos-videos {
	transform: translateX(100%);
}

.tab-pos-comments {
	transform: translateX(200%);
}

.tab-pos-users {
	transform: translateX(300%);
}

/* ==================== Dashboard ==================== */

.dashboard-section {
	animation: fadeIn 0.3s ease;
	padding-top: 24rpx;
}

.stats-row {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16rpx;
	margin-bottom: 36rpx;
}

.stat-card {
	border-radius: 20rpx;
	padding: 24rpx 20rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12rpx;
	position: relative;
	overflow: hidden;
}

.stat-card::before {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: 20rpx;
	border: 1px solid rgba(255, 255, 255, 0.08);
	pointer-events: none;
}

.stat-purple {
	background: linear-gradient(135deg, rgba(108, 92, 231, 0.2) 0%, rgba(108, 92, 231, 0.05) 100%);
}

.stat-blue {
	background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.05) 100%);
}

.stat-green {
	background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.05) 100%);
}

.stat-icon-wrap {
	width: 52rpx;
	height: 52rpx;
	border-radius: 14rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.stat-purple .stat-icon-wrap {
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
}

.stat-blue .stat-icon-wrap {
	background: linear-gradient(135deg, #3b82f6, #60a5fa);
}

.stat-green .stat-icon-wrap {
	background: linear-gradient(135deg, #10b981, #34d399);
}

.stat-body {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.stat-number {
	font-size: 40rpx;
	font-weight: 700;
	color: #fff;
	line-height: 1.2;
}

.stat-card .stat-label {
	font-size: 20rpx;
	color: #999;
	margin-top: 2rpx;
}

/* 最近活动 */
.recent-section {
	margin-bottom: 30rpx;
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
}

.section-title {
	font-size: 28rpx;
	color: #e0e0e0;
	font-weight: 600;
	display: block;
}

.section-more {
	font-size: 22rpx;
	color: #6c5ce7;
}

.recent-list {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 20rpx;
	overflow: hidden;
}

.recent-item {
	display: flex;
	align-items: center;
	padding: 22rpx 24rpx;
	border-bottom: 1px solid rgba(255, 255, 255, 0.04);
}

.recent-item:last-child {
	border-bottom: none;
}

.recent-item:active {
	background: rgba(255, 255, 255, 0.04);
}

.recent-icon {
	width: 48rpx;
	height: 48rpx;
	border-radius: 12rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 18rpx;
	flex-shrink: 0;
}

.ri-purple {
	background: rgba(108, 92, 231, 0.12);
}

.recent-info {
	flex: 1;
	overflow: hidden;
}

.recent-title {
	font-size: 26rpx;
	color: #e0e0e0;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	font-weight: 500;
}

.recent-meta {
	font-size: 20rpx;
	color: #666;
	display: block;
	margin-top: 4rpx;
}

.recent-badge {
	display: flex;
	align-items: center;
	gap: 4rpx;
	margin-left: 12rpx;
	flex-shrink: 0;
}

.badge-num {
	font-size: 20rpx;
	color: #888;
}

/* 最近评论、评论管理 - 样式已移至 CommentCard.vue */

.empty-hint {
	text-align: center;
	padding: 40rpx 0;
}

.empty-hint text {
	color: #444;
	font-size: 24rpx;
}

/* ==================== Shared styles ==================== */

.filter-section {
	margin-bottom: 30rpx;
	position: sticky;
	top: 208rpx; /* (88 header + 120 tabs height) */
	z-index: 105;
	background: #1a1a2e;
	padding-top: 10rpx;
	padding-bottom: 20rpx;
	margin-top: 0;
	margin-left: -30rpx;
	margin-right: -30rpx;
	padding-left: 30rpx;
	padding-right: 30rpx;
}

.search-box-wrapper {
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.08);
	border-radius: 12rpx;
	height: 80rpx;
	display: flex;
	align-items: center;
	padding: 0 24rpx;
	position: relative;
	z-index: 101;
}

.search-icon {
	margin-right: 16rpx;
}

.search-input {
	flex: 1;
	font-size: 26rpx;
	color: #fff;
}

.clear-btn {
	padding: 10rpx;
}

.search-results-list {
	position: absolute;
	top: 90rpx;
	left: 30rpx;
	right: 30rpx;
	background: #1a1a2e;
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 12rpx;
	max-height: 500rpx;
	overflow-y: auto;
	z-index: 102;
	box-shadow: 0 10rpx 40rpx rgba(0,0,0,0.5);
	animation: slideDown 0.2s ease;
}

@keyframes slideDown {
	from { opacity: 0; transform: translateY(-20rpx); }
	to { opacity: 1; transform: translateY(0); }
}

.search-result-item {
	display: flex;
	align-items: center;
	padding: 20rpx 24rpx;
	border-bottom: 1px solid rgba(255,255,255,0.03);
}

.search-result-item:active {
	background: rgba(255,255,255,0.05);
}

.search-result-item.active {
	background: rgba(108, 92, 231, 0.15);
}

.result-info {
	flex: 1;
	overflow: hidden;
}

.result-title {
	font-size: 26rpx;
	color: #e0e0e0;
	display: block;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.result-uploader {
	font-size: 18rpx;
	color: #666;
}

.search-mask {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 99;
}

.admin-item-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.05);
	border-radius: 16rpx;
	margin-bottom: 20rpx;
	overflow: hidden;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.item-expanded {
	background: rgba(255, 255, 255, 0.05);
	border-color: rgba(255, 255, 255, 0.1);
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.2);
}

.admin-item-header {
	display: flex;
	align-items: center;
	padding: 24rpx 30rpx;
}

.admin-video-icon {
	width: 60rpx;
	height: 60rpx;
	background: rgba(108, 92, 231, 0.1);
	border-radius: 12rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 20rpx;
}

.small-icon {
	width: 48rpx;
	height: 48rpx;
	margin-right: 16rpx;
}

.admin-item-info {
	flex: 1;
	overflow: hidden;
}

.admin-item-title {
	font-size: 28rpx;
	color: #e0e0e0;
	font-weight: 600;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	margin-bottom: 8rpx;
}

.admin-item-meta {
	display: flex;
	gap: 20rpx;
}

.meta-tag {
	display: flex;
	align-items: center;
	font-size: 22rpx;
	color: #777;
}

.admin-item-arrow {
	margin-left: 20rpx;
	opacity: 0.6;
}

.admin-item-details {
	padding: 0 30rpx 30rpx;
	border-top: 1px solid rgba(255, 255, 255, 0.03);
	animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateY(-10rpx); }
	to { opacity: 1; transform: translateY(0); }
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 20rpx;
	background: rgba(255, 255, 255, 0.02);
	padding: 24rpx 20rpx;
	border-radius: 12rpx;
	margin: 24rpx 0;
}

.details-stat {
	text-align: center;
}

.stat-value {
	font-size: 30rpx;
	color: #fff;
	display: block;
	font-weight: 500;
}

.details-stat .stat-label {
	font-size: 20rpx;
	color: #555;
	text-transform: uppercase;
	margin-top: 4rpx;
}

.admin-item-actions {
	display: flex;
	align-items: center;
	gap: 24rpx;
	padding: 24rpx 30rpx;
	border-top: 1px solid rgba(255, 255, 255, 0.04);
}

.btn-link {
	font-size: 24rpx;
	color: #6c5ce7;
	display: flex;
	align-items: center;
	padding: 10rpx 0;
	transition: opacity 0.2s;
}

.btn-link:active {
	opacity: 0.6;
}

.btn-link.neutral {
	color: #a0a0b8;
}

.btn-link.danger {
	color: #e74c3c;
}

.btn-link.small-text {
	font-size: 22rpx;
}

.action-link-group {
	display: flex;
	gap: 30rpx;
	width: 100%;
}

/* 评论卡片样式 */
.admin-comment-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 20rpx;
	padding: 24rpx;
	margin-bottom: 24rpx;
	transition: all 0.2s ease;
}

.admin-comment-card:active {
	background: rgba(255, 255, 255, 0.05);
}

.comment-card-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
}

.comment-user-info {
	display: flex;
	align-items: center;
}

.admin-user-avatar {
	width: 40rpx;
	height: 40rpx;
	border-radius: 50%;
	color: #fff;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 20rpx;
	font-weight: bold;
	margin-right: 12rpx;
}

.comment-username {
	font-size: 26rpx;
	color: #e0e0e0;
	font-weight: 500;
}

.comment-video-tag {
	display: flex;
	align-items: center;
	background: rgba(255, 255, 255, 0.05);
	padding: 4rpx 12rpx;
	border-radius: 8rpx;
	max-width: 260rpx;
}

.video-title-tag {
	font-size: 20rpx;
	color: #a0a0b8;
	margin-left: 6rpx;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.comment-card-body {
	padding: 8rpx 0 20rpx;
}

.comment-text-content {
	font-size: 28rpx;
	color: #dcdce6;
	line-height: 1.6;
}

.comment-card-footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
	border-top: 1px solid rgba(255, 255, 255, 0.04);
	padding-top: 16rpx;
}

.comment-meta-left {
	display: flex;
	align-items: center;
}

.comment-timestamp {
	font-size: 22rpx;
	color: #f39c12;
}

.meta-separator {
	color: #444;
	margin: 0 10rpx;
	font-size: 20rpx;
}

.comment-real-date {
	font-size: 22rpx;
	color: #666;
}

.load-more-status {
	text-align: center;
	padding: 40rpx 0;
	font-size: 24rpx;
	color: #444;
}

.empty-state {
	text-align: center;
	padding: 100rpx 60rpx;
}

.empty-state text {
	color: #444;
	font-size: 26rpx;
}

/* 无权限 */
.no-access {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 60vh;
}

.no-access-icon {
	font-size: 80rpx;
}

.no-access-text {
	font-size: 32rpx;
	color: #555;
	margin: 20rpx 0 40rpx;
}

/* 用户管理样式 */
.user-list-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
}

.create-user-inline-btn {
	display: flex;
	align-items: center;
	padding: 8rpx 24rpx;
	font-size: 24rpx;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	color: #fff;
	border-radius: 30rpx;
	margin: 0;
}
.create-user-inline-btn:active {
	opacity: 0.8;
}

.create-user-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 20rpx;
	padding: 30rpx;
	margin-bottom: 30rpx;
}

.form-group {
	margin-bottom: 24rpx;
}

.form-label {
	font-size: 24rpx;
	color: #888;
	margin-bottom: 12rpx;
	display: block;
}

.role-selector {
	display: flex;
	gap: 16rpx;
}

.role-option {
	flex: 1;
	text-align: center;
	padding: 16rpx 0;
	font-size: 26rpx;
	color: #666;
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.08);
	border-radius: 12rpx;
	transition: all 0.3s;
}

.role-active {
	background: linear-gradient(135deg, rgba(108, 92, 231, 0.2), rgba(168, 85, 247, 0.2));
	border-color: rgba(108, 92, 231, 0.5);
	color: #fff;
	font-weight: 600;
}

.create-user-btn {
	width: 100%;
	height: 80rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-top: 8rpx;
}

.user-list-header {
	margin-bottom: 20rpx;
	padding-top: 24rpx;
}

.user-avatar-small {
	width: 60rpx;
	height: 60rpx;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 20rpx;
}

.avatar-letter-s {
	font-size: 26rpx;
	color: #fff;
	font-weight: 700;
}

.role-badge {
	font-size: 20rpx;
	padding: 2rpx 12rpx;
	border-radius: 6rpx;
	margin-right: 12rpx;
}

.role-admin {
	background: rgba(108, 92, 231, 0.2);
	color: #a78bfa;
}

.role-user {
	background: rgba(255, 255, 255, 0.08);
	color: #b0b0c8;
}

.user-actions-row {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 12rpx;
	flex-shrink: 0;
}

/* 重置密码弹层 */
.modal-mask {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.6);
	z-index: 1000;
	display: flex;
	align-items: center;
	justify-content: center;
	animation: fadeIn 0.2s ease;
}

.modal-content {
	width: 620rpx;
	background: #1a1a2e;
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 20rpx;
	padding: 40rpx 30rpx;
	box-shadow: 0 20rpx 40rpx rgba(0, 0, 0, 0.5);
	animation: scaleIn 0.2s cubic-bezier(0.18, 0.89, 0.32, 1.28);
}

@keyframes scaleIn {
	from { opacity: 0; transform: scale(0.9); }
	to { opacity: 1; transform: scale(1); }
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;
}

.modal-title {
	font-size: 32rpx;
	color: #fff;
	font-weight: 600;
}

.modal-close {
	padding: 10rpx;
	margin: -10rpx;
}

.reset-user-hint {
	margin-bottom: 20rpx;
}

.reset-user-hint text {
	font-size: 26rpx;
	color: #b0b0c8;
}
</style>
