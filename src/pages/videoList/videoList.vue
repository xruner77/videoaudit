<template>
	<!-- ============ 管理员视图 ============ -->
	<view v-if="authStore.isAdmin" class="page-container">
		<Header title="管理后台" />
		<view class="admin-container">
			<!-- 管理 Tab -->
			<view class="admin-tabs">
				<view class="tab-header">
					<view :class="['tab-item', adminTab === 'dashboard' && 'tab-active']" @click="switchAdminTab('dashboard')">概览</view>
					<view :class="['tab-item', adminTab === 'videos' && 'tab-active']" @click="switchAdminTab('videos')">视频</view>
					<view :class="['tab-item', adminTab === 'comments' && 'tab-active']" @click="switchAdminTab('comments')">评论</view>
					<view :class="['tab-item', adminTab === 'users' && 'tab-active']" @click="switchAdminTab('users')">用户</view>
				</view>
				<view class="tab-indicator-container">
					<view :class="['tab-indicator', 'tab-pos-' + adminTab]"></view>
				</view>
			</view>

			<!-- ==================== 概览 Dashboard ==================== -->
			<view v-if="adminTab === 'dashboard'" class="dashboard-section">
				<view class="stats-row">
					<view class="stat-card stat-purple" @click="switchAdminTab('videos')">
						<view class="stat-icon-wrap si-purple"><uni-icons type="videocam" size="22" color="#fff" /></view>
						<view class="stat-body">
							<text class="stat-number">{{ dashStats.videos }}</text>
							<text class="stat-label">视频总数</text>
						</view>
					</view>
					<view class="stat-card stat-blue" @click="switchAdminTab('comments')">
						<view class="stat-icon-wrap si-blue"><uni-icons type="chat" size="22" color="#fff" /></view>
						<view class="stat-body">
							<text class="stat-number">{{ dashStats.comments }}</text>
							<text class="stat-label">评论总数</text>
						</view>
					</view>
					<view class="stat-card stat-green" @click="switchAdminTab('users')">
						<view class="stat-icon-wrap si-green"><uni-icons type="person" size="22" color="#fff" /></view>
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
						<text class="section-more" @click="switchAdminTab('videos')">查看全部 →</text>
					</view>
					<view v-if="recentVideos.length === 0" class="empty-hint"><text>暂无视频</text></view>
					<view class="recent-list-videos" v-else>
						<view class="video-manage-card" v-for="v in recentVideos" :key="v.id" @click="goReview(v.id)">
							<view class="vm-preview">
								<video class="vm-preview-video" :src="getVideoThumbUrl(v)" :controls="false" :show-center-play-btn="false" :enable-progress-gesture="false" object-fit="cover" muted preload="metadata" playsinline webkit-playsinline x5-video-player-type="h5-page"></video>
								<view class="vm-preview-overlay"></view>
								<view class="vm-duration" v-if="v.duration"><text>{{ formatDuration(v.duration) }}</text></view>
							</view>
							<view class="vm-info">
								<text class="vm-title">{{ v.title }}</text>
								<view class="vm-meta-row">
									<uni-icons type="person" size="12" color="#666" style="margin-right:4rpx;" />
									<text>{{ v.uploader || '未知' }}</text>
									<text class="vm-type-tag">{{ v.type === 'local' ? '本地' : '远程' }}</text>
								</view>
								<view class="vm-stats-row">
									<view class="vm-stat"><uni-icons type="chat" size="12" color="#888" /><text>{{ v.comment_count || 0 }}</text></view>
									<view class="vm-stat"><uni-icons type="eye" size="12" color="#888" /><text>{{ v.views || 0 }}</text></view>
								</view>
							</view>
						</view>
					</view>
				</view>
				<!-- 最近评论 -->
				<view class="recent-section">
					<view class="section-header">
						<text class="section-title">最近评论</text>
						<text class="section-more" @click="switchAdminTab('comments')">查看全部 →</text>
					</view>
					<view v-if="recentComments.length === 0" class="empty-hint"><text>暂无评论</text></view>
				<template v-else>
					<view class="recent-comment-item" v-for="c in recentComments" :key="c.id">
						<view class="rc-header">
							<view class="rc-avatar" :style="{ background: getUserColor(c.username) }">{{ c.username ? c.username.charAt(0).toUpperCase() : '?' }}</view>
							<view class="rc-user-info">
								<text class="rc-username">{{ c.username }}</text>
								<text class="rc-date">{{ formatDateSimple(c.created_at) }}</text>
							</view>
						</view>
						<text class="rc-content">{{ c.content }}</text>
						<view class="rc-footer">
							<view class="rc-video-tag">
								<uni-icons type="videocam" size="12" color="#888" />
								<text class="rc-video-name">{{ c.video_title || '未知视频' }}</text>
							</view>
							<text class="rc-timestamp">🎬 {{ formatTime(c.timestamp) }}</text>
						</view>
					</view>
				</template>
				</view>
			</view>

			<!-- ==================== 视频管理（左右布局） ==================== -->
			<view v-if="adminTab === 'videos'">
				<view class="filter-section">
					<view class="search-box-wrapper">
						<uni-icons type="search" size="18" color="#888" class="search-icon-admin" />
						<input class="search-input-admin" v-model="adminVideoQuery" placeholder="搜索视频标题..." confirm-type="search" @confirm="onSearchAdminVideos" />
						<view class="clear-btn" v-if="adminVideoQuery" @click="adminVideoQuery = ''; refreshAdminVideos()">
							<uni-icons type="closeempty" size="14" color="#888" />
						</view>
					</view>
				</view>
				<view class="video-manage-card" v-for="v in adminVideoList" :key="v.id" @click="goReview(v.id)">
					<view class="vm-preview">
						<video class="vm-preview-video" :src="getVideoThumbUrl(v)" :controls="false" :show-center-play-btn="false" :enable-progress-gesture="false" object-fit="cover" muted preload="metadata" playsinline webkit-playsinline x5-video-player-type="h5-page"></video>
						<view class="vm-preview-overlay"></view>
						<view class="vm-duration" v-if="v.duration"><text>{{ formatDuration(v.duration) }}</text></view>
					</view>
					<view class="vm-info">
						<text class="vm-title">{{ v.title }}</text>
						<view class="vm-delete-btn" @click.stop="deleteVideo(v.id)">
							<uni-icons type="trash" size="16" color="#e74c3c" />
						</view>
						<view class="vm-meta-row">
							<uni-icons type="person" size="12" color="#666" style="margin-right:4rpx;" />
							<text>{{ v.uploader || '未知' }}</text>
							<text class="vm-type-tag">{{ v.type === 'local' ? '本地' : '远程' }}</text>
						</view>
						<view class="vm-stats-row">
							<view class="vm-stat"><uni-icons type="chat" size="12" color="#888" /><text>{{ v.comment_count || 0 }}</text></view>
							<view class="vm-stat"><uni-icons type="eye" size="12" color="#888" /><text>{{ v.views || 0 }}</text></view>
						</view>
					</view>
				</view>
				<view class="load-more-status" v-if="adminVideoList.length > 0">
					<text v-if="loadingAdminVideos">正在加载...</text>
					<text v-else-if="hasMoreAdminVideos" @click="loadNextAdminVideos">加载更多视频</text>
					<text v-else>—— 已加载全部 ——</text>
				</view>
				<view class="empty-state" v-if="adminVideoList.length === 0 && loadingAdminVideos"><text>正在加载...</text></view>
				<view class="empty-state" v-else-if="adminVideoList.length === 0 && !loadingAdminVideos"><text>暂无相关视频</text></view>
			</view>

			<!-- ==================== 评论管理 ==================== -->
			<view v-if="adminTab === 'comments'">
				<view class="filter-section">
					<view class="search-box-wrapper">
						<uni-icons type="search" size="18" color="#888" class="search-icon-admin" />
						<input class="search-input-admin" v-model="videoSearchQuery" placeholder="按视频标题过滤..." @focus="showSearchResult = true" @input="showSearchResult = true" />
						<view class="clear-btn" v-if="videoSearchQuery" @click="handleClearVideoFilter">
							<uni-icons type="closeempty" size="14" color="#888" />
						</view>
					</view>
					<view class="search-results-list" v-if="showSearchResult && filteredVideoOptions.length > 0">
						<view class="search-result-item" :class="{ active: !selectedVideoId }" @click="selectVideoFilter(null)"><text>全部视频</text></view>
						<view class="search-result-item" v-for="vo in filteredVideoOptions" :key="vo.id" :class="{ active: selectedVideoId === vo.id }" @click="selectVideoFilter(vo)">
							<view class="admin-video-icon small-icon"><uni-icons type="videocam" size="16" color="#6c5ce7" /></view>
							<view class="result-info">
								<text class="result-title">{{ vo.title }} <text style="font-size:20rpx;color:#888;font-weight:normal;margin-left:8rpx;">({{ vo.comment_count || 0 }})</text></text>
								<text class="result-uploader">{{ vo.uploader || '系统' }}</text>
							</view>
						</view>
					</view>
					<view class="search-mask" v-if="showSearchResult" @click="showSearchResult = false"></view>
				</view>
				<view class="recent-comment-item" v-for="c in commentList" :key="c.id" style="position: relative;">
					<view class="rc-header">
						<view class="rc-avatar" :style="{ background: getUserColor(c.username) }">{{ c.username ? c.username.charAt(0).toUpperCase() : '?' }}</view>
						<view class="rc-user-info">
							<text class="rc-username">{{ c.username }}</text>
							<text class="rc-date">{{ formatDateSimple(c.created_at) }}</text>
						</view>
						<view class="rc-delete-btn" @click="deleteComment(c.id)">
							<uni-icons type="trash" size="16" color="#e74c3c" />
						</view>
					</view>
					<text class="rc-content">{{ c.content }}</text>
					<view class="rc-footer">
						<view class="rc-video-tag">
							<uni-icons type="videocam" size="12" color="#888" />
							<text class="rc-video-name">{{ c.video_title || '未知视频' }}</text>
						</view>
						<text class="rc-timestamp">🎬 {{ formatTime(c.timestamp) }}</text>
					</view>
				</view>
				<view class="load-more-status" v-if="commentList.length > 0">
					<text v-if="loadingComments">正在加载...</text>
					<text v-else-if="hasMoreComments" @click="loadNextComments">加载更多评论</text>
					<text v-else>—— 已加载全部 ——</text>
				</view>
				<view class="empty-state" v-if="commentList.length === 0 && loadingComments"><text>正在加载...</text></view>
				<view class="empty-state" v-else-if="commentList.length === 0 && !loadingComments"><text>暂无相关评论</text></view>
			</view>

			<!-- ==================== 用户管理 ==================== -->
			<view v-if="adminTab === 'users'">
				<view class="user-list-header">
					<text class="section-title">用户列表 ({{ userList.length }})</text>
					<view class="add-user-btn" @click="showCreateUser = true">
						<uni-icons type="plusempty" size="18" color="#fff" />
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
								<view :class="['role-badge', u.role === 'admin' ? 'role-admin' : 'role-user']"><text>{{ u.role === 'admin' ? '管理员' : '审片员' }}</text></view>
								<view class="meta-tag"><text>{{ formatDateSimple(u.created_at) }}</text></view>
							</view>
						</view>
						<view class="user-actions-col" v-if="u.id != authStore.user?.id">
							<view style="padding: 10rpx; margin-right: -10rpx;" @click="deleteUser(u.id, u.username)">
								<uni-icons type="trash" size="18" color="#e74c3c" />
							</view>
							<text class="btn-link neutral small-text" @click="openResetPassword(u.id, u.username)">
								<uni-icons type="locked" size="14" color="#a0a0b8" style="margin-right:6rpx;" />重置密码
							</text>
						</view>
					</view>
				</view>
				<view class="empty-state" v-if="userList.length === 0 && !loadingUsers"><text>暂无用户</text></view>

				<!-- 创建用户弹层 -->
				<view class="modal-mask" v-if="showCreateUser" @click="showCreateUser = false">
					<view class="modal-content" @click.stop>
						<view class="modal-header">
							<text class="modal-title">创建新用户</text>
							<view class="modal-close" @click="showCreateUser = false">
								<uni-icons type="closeempty" size="18" color="#999" />
							</view>
						</view>
						<view class="form-group"><text class="form-label">用户名</text><input class="dark-input" v-model="newUsername" placeholder="2-20个字符" maxlength="20" /></view>
						<view class="form-group"><text class="form-label">密码</text><input class="dark-input" v-model="newPassword" placeholder="至少6个字符" maxlength="32" /></view>
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
						<view class="reset-user-hint"><text>为用户「{{ resetTargetName }}」设置新密码</text></view>
						<view class="form-group"><text class="form-label">新密码</text><input class="dark-input" v-model="resetNewPassword" placeholder="至少6个字符" maxlength="32" /></view>
						<button class="btn-primary create-user-btn" :loading="resettingPassword" @click="resetPassword">确认重置</button>
					</view>
				</view>
			</view>
		</view>
	</view>

	<!-- ============ 普通用户视图（完全不变） ============ -->
	<view v-else class="page">
		<Header title="生活网审片系统" />
		<view class="welcome-section">
			<view class="welcome-text">
				<text class="user-greeting">你好，{{ authStore.user?.username || '用户' }}</text>
				<text class="system-welcome">欢迎使用审片系统</text>
			</view>
			<view class="stats-badge">
				<view class="stats-item">
					<text class="stats-value">{{ total }}</text>
					<text class="stats-label">待审视频</text>
				</view>
			</view>
		</view>
		<view class="search-bar-container">
			<view class="search-bar">
				<uni-icons type="search" size="18" color="#888" class="search-icon" />
				<input class="search-input" v-model="searchQuery" placeholder="搜索视频标题..." confirm-type="search" @confirm="onSearch" />
				<view class="clear-btn" v-if="searchQuery" @click="clearSearch"><uni-icons type="closeempty" size="14" color="#888" /></view>
			</view>
		</view>
		<view class="video-grid">
			<view class="video-card" v-for="video in dataList" :key="video.id" @click="goReview(video.id)">
				<view class="video-thumb">
					<video class="thumb-video" :src="getVideoThumbUrl(video)" :controls="false" :show-center-play-btn="false" :enable-progress-gesture="false" object-fit="cover" muted preload="metadata" playsinline webkit-playsinline x5-video-player-type="h5-page"></video>
					<view class="thumb-overlay"></view>
					<view class="video-type-badge"><text><uni-icons type="chat" size="12" color="#fff" style="margin-right:4rpx;"/>{{ video.comment_count !== undefined ? video.comment_count : 0 }}</text></view>
					<view class="video-duration-badge" v-if="video.duration"><text>{{ formatDuration(video.duration) }}</text></view>
				</view>
				<view class="video-info">
					<view class="info-row"><text class="video-title">{{ video.title }}</text></view>
					<view class="video-meta">
						<text class="meta-user"><uni-icons type="person" size="12" color="#aaa" style="margin-right:2rpx;"/>{{ video.uploader || '未知' }}</text>
						<view class="meta-stats">
							<text class="meta-date">{{ formatDate(video.created_at) }}</text>
							<text class="meta-comments" v-if="video.views !== undefined"><uni-icons type="eye" size="12" color="#aaa" style="margin-right:2rpx;"/>{{ video.views }}</text>
						</view>
					</view>
				</view>
			</view>
			<view class="empty-state" v-if="dataList.length === 0 && !loading"><text>暂无相关视频</text></view>
			<view class="load-more-status" v-if="dataList.length > 0">
				<text v-if="loading">正在加载...</text>
				<text v-else-if="hasMore">继续滚动加载</text>
				<text v-else>—— 已加载全部视频 ——</text>
			</view>
		</view>
		<view class="fab" @click="goUpload"><text class="fab-icon"><uni-icons type="plusempty" size="24" color="#fff"/></text></view>
		<view class="footer">
			<image class="footer-logo" src="/static/logo_company.png" mode="aspectFit"></image>
			<text class="footer-copyright">© 桂林三新网络传媒有限责任公司 版权所有</text>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad, onShow, onReachBottom } from '@dcloudio/uni-app'
import { useAuthStore } from '@/stores/authStore'
import Header from '@/components/Header.vue'
import { usePagination } from '@/composables/usePagination'

const authStore = useAuthStore()

// ==================== 共用 ====================
function goReview(id) { uni.navigateTo({ url: `/pages/review/review?id=${id}` }) }
function formatDuration(seconds) {
	if (!seconds) return '00:00'
	const m = Math.floor(seconds / 60); const s = seconds % 60
	return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}
function formatTime(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60); const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}
function getVideoThumbUrl(video) {
	if (!video || !video.url) return ''
	let url = video.type === 'local' ? `${authStore.API_BASE}${video.url}` : video.url
	if (!url.includes('#t=')) url += '#t=0.5'
	return url
}
const avatarColors = ['#5b52f6', '#a855f7', '#ec4899', '#f43f5e', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#6366f1']
function getUserColor(username) {
	if (!username) return avatarColors[0]
	let hash = 0
	for (let i = 0; i < username.length; i++) hash = username.charCodeAt(i) + ((hash << 5) - hash)
	return avatarColors[Math.abs(hash) % avatarColors.length]
}

// ==================== 管理员：Dashboard ====================
const adminTab = ref('dashboard')
const dashStats = ref({ videos: 0, comments: 0, users: 0 })
const recentVideos = ref([])
const recentComments = ref([])

async function fetchDashboard() {
	try {
		const [videoRes, commentRes, userRes] = await Promise.all([
			uni.request({ url: `${authStore.API_BASE}/api/videos`, method: 'GET', header: authStore.getAuthHeader(), data: { limit: 5 } }),
			uni.request({ url: `${authStore.API_BASE}/api/admin/comments`, method: 'GET', header: authStore.getAuthHeader(), data: { limit: 5 } }),
			uni.request({ url: `${authStore.API_BASE}/api/admin/users`, method: 'GET', header: authStore.getAuthHeader() })
		])
		if (videoRes.statusCode === 200) { dashStats.value.videos = videoRes.data.total || 0; recentVideos.value = videoRes.data.videos || [] }
		if (commentRes.statusCode === 200) { dashStats.value.comments = commentRes.data.total || 0; recentComments.value = commentRes.data.comments || [] }
		if (userRes.statusCode === 200) { const users = userRes.data.users || []; dashStats.value.users = users.length; userList.value = users }
	} catch (e) { console.error('Dashboard fetch failed:', e) }
}

function switchAdminTab(newTab) {
	adminTab.value = newTab
	uni.pageScrollTo({ scrollTop: 0, duration: 200 })
	if (newTab === 'dashboard') fetchDashboard()
	else if (newTab === 'videos' && adminVideoList.value.length === 0) refreshAdminVideos()
	else if (newTab === 'comments' && commentList.value.length === 0) refreshComments()
	else if (newTab === 'users' && userList.value.length === 0) fetchUsers()
}
function formatDateSimple(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getMonth() + 1}月${d.getDate()}日`
}

// ==================== 管理员：视频管理 ====================
const adminVideoQuery = ref('')
const { dataList: adminVideoList, loading: loadingAdminVideos, hasMore: hasMoreAdminVideos, loadNextPage: loadNextAdminVideos, reset: resetAdminVideos } = usePagination(async (params) => {
	const res = await uni.request({ url: `${authStore.API_BASE}/api/videos`, method: 'GET', header: authStore.getAuthHeader(), data: { ...params, q: adminVideoQuery.value } })
	if (res.statusCode === 200) return { data: res.data.videos, total: res.data.total }
	return { data: [], total: 0 }
}, { limit: 20 })

function refreshAdminVideos() { resetAdminVideos(); loadNextAdminVideos() }
function onSearchAdminVideos() { refreshAdminVideos() }

async function deleteVideo(id) {
	uni.showModal({ title: '管理员操作', content: '确定要删除此视频及关联的所有评论？', success: async (res) => {
		if (!res.confirm) return
		try {
			const resp = await uni.request({ url: `${authStore.API_BASE}/api/videos/${id}`, method: 'DELETE', header: authStore.getAuthHeader() })
			if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); refreshAdminVideos() }
			else throw new Error(resp.data?.error || '删除失败')
		} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
	}})
}

// ==================== 管理员：评论管理 ====================
const videoOptions = ref([])
const selectedVideoId = ref(null)
const videoSearchQuery = ref('')
const showSearchResult = ref(false)

const { dataList: commentList, loading: loadingComments, hasMore: hasMoreComments, loadNextPage: loadNextComments, reset: resetComments } = usePagination(async (params) => {
	const res = await uni.request({ url: `${authStore.API_BASE}/api/admin/comments`, method: 'GET', header: authStore.getAuthHeader(), data: { ...params, video_id: selectedVideoId.value || undefined } })
	if (res.statusCode === 200) return { data: res.data.comments, total: res.data.total }
	return { data: [], total: 0 }
}, { limit: 20 })

async function fetchVideoOptions() {
	try {
		const res = await uni.request({ url: `${authStore.API_BASE}/api/videos/all-names`, header: authStore.getAuthHeader() })
		if (res.data && res.data.videos) videoOptions.value = res.data.videos
	} catch (e) { console.error('Fetch video options failed:', e) }
}
const filteredVideoOptions = computed(() => {
	if (!videoSearchQuery.value) return videoOptions.value
	const q = videoSearchQuery.value.toLowerCase()
	return videoOptions.value.filter(v => v.title.toLowerCase().includes(q) || (v.uploader && v.uploader.toLowerCase().includes(q)))
})
function refreshComments() { resetComments(); loadNextComments() }
function selectVideoFilter(video) {
	if (!video) { selectedVideoId.value = null; videoSearchQuery.value = '' }
	else { selectedVideoId.value = video.id; videoSearchQuery.value = video.title }
	showSearchResult.value = false; refreshComments()
}
function handleClearVideoFilter() { videoSearchQuery.value = ''; selectedVideoId.value = null; refreshComments() }

async function deleteComment(id) {
	uni.showModal({ title: '管理员操作', content: '确定要删除此评论？', success: async (res) => {
		if (!res.confirm) return
		try {
			const resp = await uni.request({ url: `${authStore.API_BASE}/api/comments/${id}`, method: 'DELETE', header: authStore.getAuthHeader() })
			if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); refreshComments() }
			else throw new Error(resp.data?.error || '删除失败')
		} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
	}})
}

// ==================== 管理员：用户管理 ====================
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

async function fetchUsers() {
	loadingUsers.value = true
	try {
		const res = await uni.request({ url: `${authStore.API_BASE}/api/admin/users`, method: 'GET', header: authStore.getAuthHeader() })
		if (res.statusCode === 200) userList.value = res.data.users || []
	} catch (e) { console.error('Fetch users failed:', e) }
	finally { loadingUsers.value = false }
}
async function createUser() {
	if (!newUsername.value.trim()) return uni.showToast({ title: '请输入用户名', icon: 'none' })
	if (!newPassword.value || newPassword.value.length < 6) return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
	creatingUser.value = true
	try {
		const res = await uni.request({ url: `${authStore.API_BASE}/api/admin/users`, method: 'POST', header: { 'Content-Type': 'application/json', ...authStore.getAuthHeader() }, data: { username: newUsername.value.trim(), password: newPassword.value, role: newRole.value } })
		if (res.statusCode === 201) { uni.showToast({ title: '用户创建成功', icon: 'success' }); newUsername.value = ''; newPassword.value = ''; newRole.value = 'user'; showCreateUser.value = false; fetchUsers() }
		else throw new Error(res.data?.error || '创建失败')
	} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
	finally { creatingUser.value = false }
}
async function deleteUser(id, username) {
	uni.showModal({ title: '管理员操作', content: `确定要删除用户「${username}」及其所有数据？`, success: async (res) => {
		if (!res.confirm) return
		try {
			const resp = await uni.request({ url: `${authStore.API_BASE}/api/admin/users/${id}`, method: 'DELETE', header: authStore.getAuthHeader() })
			if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); fetchUsers() }
			else throw new Error(resp.data?.error || '删除失败')
		} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
	}})}
function openResetPassword(id, username) {
	resetTargetId.value = id; resetTargetName.value = username; resetNewPassword.value = ''; showResetPassword.value = true
}
async function resetPassword() {
	if (!resetNewPassword.value || resetNewPassword.value.length < 6) return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
	resettingPassword.value = true
	try {
		const res = await uni.request({ url: `${authStore.API_BASE}/api/admin/users/${resetTargetId.value}/reset-password`, method: 'PUT', header: { 'Content-Type': 'application/json', ...authStore.getAuthHeader() }, data: { password: resetNewPassword.value } })
		if (res.statusCode === 200) { uni.showToast({ title: '密码已重置', icon: 'success' }); showResetPassword.value = false }
		else throw new Error(res.data?.error || '重置失败')
	} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
	finally { resettingPassword.value = false }
}

// ==================== 普通用户：视频列表 ====================
const searchQuery = ref('')
const loaded = ref(false)
const { dataList, loading, hasMore, total, loadNextPage, reset, silentRefresh } = usePagination(async (params) => {
	const res = await uni.request({ url: `${authStore.API_BASE}/api/videos`, method: 'GET', header: authStore.getAuthHeader(), data: { ...params, q: searchQuery.value } })
	if (res.statusCode === 200 && res.data) return { data: res.data.videos, total: res.data.total }
	return { data: [], total: 0 }
}, { limit: 10 })

function onSearch() { reset(); loadNextPage().then(() => { loaded.value = true }) }
function clearSearch() { searchQuery.value = ''; onSearch() }
function goUpload() { uni.navigateTo({ url: '/pages/upload/upload' }) }
function formatDate(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getMonth() + 1}月${d.getDate()}日`
}

// ==================== 生命周期 ====================
onLoad(() => {
	if (!authStore.isLoggedIn) { uni.reLaunch({ url: '/pages/login/login' }); return }
	if (authStore.isAdmin) { fetchDashboard(); fetchVideoOptions() }
	else { onSearch() }
})
onShow(() => {
	if (authStore.isAdmin) { fetchDashboard(); fetchVideoOptions() }
	else { if (loaded.value) silentRefresh({ q: searchQuery.value }) }
})
onReachBottom(() => {
	if (authStore.isAdmin) {
		if (adminTab.value === 'videos') loadNextAdminVideos()
		else if (adminTab.value === 'comments') loadNextComments()
	} else { loadNextPage() }
})
</script>

<style scoped>
/* ==================== 普通用户样式 ==================== */
.page { min-height: 100vh; background: #0f0f1a; padding-bottom: 60rpx; }
.search-bar-container { padding: 20rpx 30rpx; background: #0f0f1a; position: sticky; top: 0; z-index: 100; }
.search-bar { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 40rpx; height: 72rpx; display: flex; align-items: center; padding: 0 24rpx; }
.search-icon { margin-right: 16rpx; }
.search-input { flex: 1; font-size: 26rpx; color: #fff; }
.clear-btn { padding: 10rpx; }
.video-grid { padding: 20rpx 30rpx 100rpx; display: grid; grid-template-columns: repeat(2, 1fr); gap: 20rpx; }
.video-card { width: 100%; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.06); border-radius: 16rpx; overflow: hidden; transition: transform 0.2s; }
.video-card:active { transform: scale(0.97); }
.video-thumb { height: 220rpx; background: #1a1a2e; position: relative; overflow: hidden; }
.thumb-video { width: 100%; height: 100%; display: block; pointer-events: none; }
.thumb-overlay { position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.4) 0%, transparent 40%, rgba(0,0,0,0.6) 100%); pointer-events: none; }
.video-type-badge { position: absolute; top: 12rpx; right: 12rpx; }
.video-type-badge text { font-size: 22rpx; color: #fff; font-weight: bold; text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.8); }
.video-duration-badge { position: absolute; bottom: 12rpx; right: 12rpx; }
.video-duration-badge text { font-size: 22rpx; color: #fff; font-weight: bold; text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.8); }
.video-info { padding: 16rpx 20rpx 24rpx; }
.info-row { margin-bottom: 8rpx; }
.video-title { font-size: 26rpx; font-weight: 600; color: #e0e0e0; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.video-meta { display: flex; justify-content: space-between; align-items: center; }
.meta-stats { display: flex; align-items: center; gap: 16rpx; }
.meta-user, .meta-date, .meta-comments { font-size: 22rpx; color: #666; }
.empty-state { width: 100%; text-align: center; padding: 100rpx 0; box-sizing: border-box; }
.empty-state text { color: #444; font-size: 26rpx; }
.load-more-status { grid-column: span 2; width: 100%; text-align: center; padding: 40rpx 0; font-size: 24rpx; color: #444; }
.fab { position: fixed; bottom: 160rpx; right: 40rpx; width: 100rpx; height: 100rpx; border-radius: 50%; background: linear-gradient(135deg, #6c5ce7, #a855f7); display: flex; align-items: center; justify-content: center; box-shadow: 0 8rpx 24rpx rgba(108,92,231,0.4); z-index: 100; }
.fab:active { transform: scale(0.9); }
.fab-icon { font-size: 48rpx; color: #fff; font-weight: 300; }
.footer { padding: 20rpx 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.footer-logo { width: 180rpx; height: 60rpx; margin-bottom: 8rpx; opacity: 0.8; }
.footer-copyright { font-size: 22rpx; color: #444; letter-spacing: 1rpx; }
.welcome-section { padding: 30rpx; display: flex; justify-content: space-between; align-items: center; background: linear-gradient(to bottom, #1a1a2e 0%, #0f0f1a 100%); }
.welcome-text { display: flex; flex-direction: column; }
.user-greeting { font-size: 34rpx; font-weight: 600; color: #fff; margin-bottom: 4rpx; }
.system-welcome { font-size: 22rpx; color: #888; }
.stats-badge { background: rgba(108,92,231,0.1); border: 1px solid rgba(108,92,231,0.2); padding: 16rpx 24rpx; border-radius: 20rpx; }
.stats-item { display: flex; flex-direction: column; align-items: center; }
.stats-value { font-size: 32rpx; font-weight: bold; color: #6c5ce7; line-height: 1; margin-bottom: 4rpx; }
.stats-label { font-size: 18rpx; color: #666; text-transform: uppercase; }

/* ==================== 管理员样式 ==================== */
.admin-container { padding: 0 30rpx 40rpx; }
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
.tab-header { display: flex; justify-content: space-around; padding: 10rpx 0; }
.tab-item { font-size: 26rpx; color: #777; padding: 20rpx 0; transition: all 0.3s ease; font-weight: 500; }
.tab-active { color: #fff; font-weight: 600; }
.tab-indicator-container { height: 4rpx; background: rgba(255,255,255,0.05); border-radius: 2rpx; position: relative; }
.tab-indicator { position: absolute; top: 0; left: 0; width: 25%; height: 100%; background: linear-gradient(90deg, #6c5ce7, #a855f7); border-radius: 2rpx; transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
.tab-pos-dashboard { transform: translateX(0); }
.tab-pos-videos { transform: translateX(100%); }
.tab-pos-comments { transform: translateX(200%); }
.tab-pos-users { transform: translateX(300%); }

/* Dashboard */
.dashboard-section { animation: fadeIn 0.3s ease; padding-top: 24rpx; }
.stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16rpx; margin-bottom: 36rpx; }
.stat-card { border-radius: 20rpx; padding: 24rpx 20rpx; display: flex; flex-direction: column; align-items: center; gap: 12rpx; position: relative; overflow: hidden; }
.stat-card::before { content: ''; position: absolute; inset: 0; border-radius: 20rpx; border: 1px solid rgba(255,255,255,0.08); pointer-events: none; }
.stat-purple { background: linear-gradient(135deg, rgba(108,92,231,0.2) 0%, rgba(108,92,231,0.05) 100%); }
.stat-blue { background: linear-gradient(135deg, rgba(59,130,246,0.2) 0%, rgba(59,130,246,0.05) 100%); }
.stat-green { background: linear-gradient(135deg, rgba(16,185,129,0.2) 0%, rgba(16,185,129,0.05) 100%); }
.stat-icon-wrap { width: 52rpx; height: 52rpx; border-radius: 14rpx; display: flex; align-items: center; justify-content: center; }
.si-purple { background: linear-gradient(135deg, #6c5ce7, #a855f7); }
.si-blue { background: linear-gradient(135deg, #3b82f6, #60a5fa); }
.si-green { background: linear-gradient(135deg, #10b981, #34d399); }
.stat-body { display: flex; flex-direction: column; align-items: center; }
.stat-number { font-size: 40rpx; font-weight: 700; color: #fff; line-height: 1.2; }
.stat-card .stat-label { font-size: 20rpx; color: #999; margin-top: 2rpx; }
.recent-section { margin-bottom: 30rpx; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20rpx; }
.section-title { font-size: 28rpx; color: #e0e0e0; font-weight: 600; display: block; }
.section-more { font-size: 22rpx; color: #6c5ce7; }
.recent-list { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 20rpx; overflow: hidden; }
.recent-item { display: flex; align-items: center; padding: 22rpx 24rpx; border-bottom: 1px solid rgba(255,255,255,0.04); }
.recent-item:last-child { border-bottom: none; }
.recent-item:active { background: rgba(255,255,255,0.04); }
.recent-icon { width: 48rpx; height: 48rpx; border-radius: 12rpx; display: flex; align-items: center; justify-content: center; margin-right: 18rpx; flex-shrink: 0; }
.ri-purple { background: rgba(108,92,231,0.12); }
.recent-info { flex: 1; overflow: hidden; }
.recent-title { font-size: 26rpx; color: #e0e0e0; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 500; }
.recent-meta { font-size: 20rpx; color: #666; display: block; margin-top: 4rpx; }
.recent-badge { display: flex; align-items: center; gap: 4rpx; margin-left: 12rpx; flex-shrink: 0; }
.badge-num { font-size: 20rpx; color: #888; }
.recent-comment-item { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 16rpx; padding: 22rpx 24rpx; margin-bottom: 16rpx; }
.rc-header { display: flex; align-items: center; margin-bottom: 14rpx; }
.rc-avatar { width: 40rpx; height: 40rpx; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20rpx; font-weight: bold; margin-right: 12rpx; flex-shrink: 0; }
.rc-user-info { flex: 1; display: flex; align-items: center; gap: 12rpx; }
.rc-username { font-size: 24rpx; color: #e0e0e0; font-weight: 500; }
.rc-date { font-size: 20rpx; color: #555; }
.rc-content { font-size: 26rpx; color: #dcdce6; line-height: 1.5; display: block; margin-bottom: 12rpx; }
.rc-footer { display: flex; justify-content: space-between; align-items: center; }
.rc-video-tag { display: flex; align-items: center; gap: 6rpx; }
.rc-video-name { font-size: 20rpx; color: #666; max-width: 240rpx; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.rc-timestamp { font-size: 20rpx; color: #f39c12; }
.rc-delete-btn { display: flex; align-items: center; justify-content: center; padding: 10rpx; margin: -10rpx; margin-left: auto; transition: opacity 0.2s; }
.rc-delete-btn:active { opacity: 0.6; }
.empty-hint { text-align: center; padding: 40rpx 0; }
.empty-hint text { color: #444; font-size: 24rpx; }

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
.search-box-wrapper { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12rpx; height: 80rpx; display: flex; align-items: center; padding: 0 24rpx; position: relative; z-index: 101; }
.search-icon-admin { margin-right: 16rpx; }
.search-input-admin { flex: 1; font-size: 26rpx; color: #fff; }
.search-results-list { position: absolute; top: 90rpx; left: 30rpx; right: 30rpx; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 12rpx; max-height: 500rpx; overflow-y: auto; z-index: 102; box-shadow: 0 10rpx 40rpx rgba(0,0,0,0.5); animation: slideDown 0.2s ease; }
@keyframes slideDown { from { opacity: 0; transform: translateY(-20rpx); } to { opacity: 1; transform: translateY(0); } }
.search-result-item { display: flex; align-items: center; padding: 20rpx 24rpx; border-bottom: 1px solid rgba(255,255,255,0.03); }
.search-result-item:active { background: rgba(255,255,255,0.05); }
.search-result-item.active { background: rgba(108,92,231,0.15); }
.result-info { flex: 1; overflow: hidden; }
.result-title { font-size: 26rpx; color: #e0e0e0; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.result-uploader { font-size: 18rpx; color: #666; }
.search-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; z-index: 99; }

/* 视频管理 - 左右布局 */
.video-manage-card { position: relative; display: flex; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 16rpx; margin-bottom: 20rpx; overflow: hidden; transition: all 0.2s ease; }
.video-manage-card:active { background: rgba(255,255,255,0.06); }
.vm-preview { width: 210rpx; min-height: 140rpx; position: relative; flex-shrink: 0; background: #1a1a2e; overflow: hidden; }
.vm-preview-video { width: 100%; height: 100%; display: block; pointer-events: none; }
.vm-preview-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.2) 0%, transparent 40%, rgba(0,0,0,0.4) 100%); pointer-events: none; }
.vm-duration { position: absolute; bottom: 8rpx; right: 8rpx; }
.vm-duration text { font-size: 20rpx; color: #fff; font-weight: bold; text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.8); }
.vm-info { flex: 1; padding: 16rpx 20rpx; display: flex; flex-direction: column; justify-content: center; min-width: 0; gap: 8rpx; position: relative; }
.vm-title { font-size: 26rpx; font-weight: 600; color: #e0e0e0; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; padding-right: 48rpx; }
.vm-delete-btn { position: absolute; top: 16rpx; right: 20rpx; padding: 10rpx; margin: -10rpx; display: flex; align-items: center; justify-content: center; z-index: 10; transition: opacity 0.2s; }
.vm-delete-btn:active { opacity: 0.6; }
.vm-meta-row { display: flex; align-items: center; gap: 8rpx; font-size: 22rpx; color: #777; }
.vm-type-tag { background: rgba(108,92,231,0.12); color: #a78bfa; font-size: 18rpx; padding: 2rpx 10rpx; border-radius: 6rpx; margin-left: 8rpx; }
.vm-stats-row { display: flex; align-items: center; gap: 20rpx; }
.vm-stat { display: flex; align-items: center; gap: 4rpx; font-size: 22rpx; color: #888; }
.vm-actions { display: flex; align-items: center; gap: 24rpx; margin-top: 4rpx; }
.vm-btn { font-size: 22rpx; display: flex; align-items: center; padding: 6rpx 0; transition: opacity 0.2s; }
.vm-btn:active { opacity: 0.6; }
.vm-btn-view { color: #6c5ce7; }
.vm-btn-del { color: #e74c3c; }

/* 评论卡片 */
.admin-comment-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); border-radius: 20rpx; padding: 24rpx; margin-bottom: 24rpx; transition: all 0.2s ease; }
.admin-comment-card:active { background: rgba(255,255,255,0.05); }
.comment-card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20rpx; }
.comment-user-info { display: flex; align-items: center; }
.admin-user-avatar { width: 40rpx; height: 40rpx; border-radius: 50%; color: #fff; display: flex; align-items: center; justify-content: center; font-size: 20rpx; font-weight: bold; margin-right: 12rpx; }
.comment-username { font-size: 26rpx; color: #e0e0e0; font-weight: 500; }
.comment-video-tag { display: flex; align-items: center; background: rgba(255,255,255,0.05); padding: 4rpx 12rpx; border-radius: 8rpx; max-width: 260rpx; }
.video-title-tag { font-size: 20rpx; color: #a0a0b8; margin-left: 6rpx; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.comment-card-body { padding: 8rpx 0 20rpx; }
.comment-text-content { font-size: 28rpx; color: #dcdce6; line-height: 1.6; }
.comment-card-footer { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid rgba(255,255,255,0.04); padding-top: 16rpx; }
.comment-meta-left { display: flex; align-items: center; }
.comment-timestamp { font-size: 22rpx; color: #f39c12; }
.meta-separator { color: #444; margin: 0 10rpx; font-size: 20rpx; }
.comment-real-date { font-size: 22rpx; color: #666; }
.btn-link { font-size: 24rpx; color: #6c5ce7; display: flex; align-items: center; padding: 10rpx 0; transition: opacity 0.2s; }
.btn-link:active { opacity: 0.6; }
.btn-link.neutral { color: #a0a0b8; }
.btn-link.danger { color: #e74c3c; }
.btn-link.small-text { font-size: 22rpx; }
.admin-video-icon { width: 60rpx; height: 60rpx; background: rgba(108,92,231,0.1); border-radius: 12rpx; display: flex; align-items: center; justify-content: center; margin-right: 20rpx; }
.small-icon { width: 48rpx; height: 48rpx; margin-right: 16rpx; }

/* 用户管理 */
.user-list-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20rpx; padding-top: 24rpx; }
.add-user-btn { width: 56rpx; height: 56rpx; background: linear-gradient(135deg, #6c5ce7, #a855f7); border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4rpx 12rpx rgba(108,92,231,0.3); transition: transform 0.2s; }
.add-user-btn:active { transform: scale(0.92); }

/* Modal 弹层 */
.modal-mask { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: flex; align-items: center; justify-content: center; animation: fadeIn 0.2s ease; }
.modal-content { width: 620rpx; background: #1a1a2e; border: 1px solid rgba(255,255,255,0.1); border-radius: 20rpx; padding: 40rpx 30rpx; box-shadow: 0 20rpx 40rpx rgba(0,0,0,0.5); animation: scaleIn 0.2s cubic-bezier(0.18, 0.89, 0.32, 1.28); }
@keyframes scaleIn { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30rpx; }
.modal-title { font-size: 32rpx; color: #fff; font-weight: 600; }
.modal-close { padding: 10rpx; margin: -10rpx; }
.form-group { margin-bottom: 24rpx; }
.form-label { font-size: 24rpx; color: #888; margin-bottom: 12rpx; display: block; }
.role-selector { display: flex; gap: 16rpx; }
.role-option { flex: 1; text-align: center; padding: 16rpx 0; font-size: 26rpx; color: #666; background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08); border-radius: 12rpx; transition: all 0.3s; }
.role-active { background: linear-gradient(135deg, rgba(108,92,231,0.2), rgba(168,85,247,0.2)); border-color: rgba(108,92,231,0.5); color: #fff; font-weight: 600; }
.create-user-btn { width: 100%; height: 80rpx; display: flex; align-items: center; justify-content: center; margin-top: 16rpx; }
.admin-item-card { background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 16rpx; margin-bottom: 20rpx; overflow: hidden; position: relative; }
.admin-item-header { display: flex; align-items: center; padding: 24rpx 30rpx; position: relative; }
.user-actions-col { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; gap: 12rpx; margin-left: 20rpx; }
.admin-item-info { flex: 1; min-width: 0; overflow: hidden; }
.admin-item-title { font-size: 28rpx; color: #e0e0e0; font-weight: 600; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 8rpx; }
.admin-item-meta { display: flex; gap: 20rpx; }
.meta-tag { display: flex; align-items: center; font-size: 22rpx; color: #777; }
.user-avatar-small { width: 60rpx; height: 60rpx; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-right: 20rpx; }
.avatar-letter-s { font-size: 26rpx; color: #fff; font-weight: 700; }
.role-badge { font-size: 20rpx; padding: 2rpx 12rpx; border-radius: 6rpx; margin-right: 12rpx; }
.role-admin { background: rgba(108,92,231,0.2); color: #a78bfa; }
.role-user { background: rgba(255,255,255,0.08); color: #b0b0c8; }
.user-actions-row { display: flex; flex-direction: column; align-items: flex-end; gap: 12rpx; flex-shrink: 0; }
.reset-user-hint { margin-bottom: 20rpx; }
.reset-user-hint text { font-size: 26rpx; color: #b0b0c8; }

@keyframes fadeIn { from { opacity: 0; transform: translateY(-10rpx); } to { opacity: 1; transform: translateY(0); } }
</style>
