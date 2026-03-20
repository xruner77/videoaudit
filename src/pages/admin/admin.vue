<template>
	<view class="page-container">
		<Header title="后台管理" showBack />

		<view class="admin-container" v-if="authStore.isAdmin">
			<!-- 管理 Tab -->
			<view class="admin-tabs">
				<view class="tab-header">
					<view 
						:class="['tab-item', tab === 'videos' && 'tab-active']" 
						@click="switchTab('videos')"
					>
						视频管理
					</view>
					<view 
						:class="['tab-item', tab === 'comments' && 'tab-active']" 
						@click="switchTab('comments')"
					>
						评论管理
					</view>
				</view>
				<!-- 底部指示条 -->
				<view class="tab-indicator-container">
					<view :class="['tab-indicator', tab === 'comments' && 'at-right']"></view>
				</view>
			</view>

			<!-- 视频管理 -->
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

				<view 
					class="admin-item-card" 
					v-for="v in videoList" 
					:key="v.id"
					:class="{ 'item-expanded': expandedId === v.id }"
				>
					<view class="admin-item-header" @click="toggleExpand(v.id)">
						<view class="admin-video-icon">
							<uni-icons type="videocam" size="20" color="#6c5ce7" />
						</view>
						<view class="admin-item-info">
							<text class="admin-item-title">{{ v.title }}</text>
							<view class="admin-item-meta">
								<view class="meta-tag">
									<uni-icons type="person" size="12" color="#666" style="margin-right:4rpx;"/>
									<text>{{ v.uploader || '未知' }}</text>
								</view>
								<view class="meta-tag">
									<uni-icons :type="v.type === 'local' ? 'folder-add' : 'paperplane'" size="12" color="#666" style="margin-right:4rpx;"/>
									<text>{{ v.type === 'local' ? '本地' : '远程' }}</text>
								</view>
							</view>
						</view>
						<view class="admin-item-arrow">
							<uni-icons :type="expandedId === v.id ? 'top' : 'bottom'" size="14" color="#555" />
						</view>
					</view>

					<!-- 展开详情区域 -->
					<view class="admin-item-details" v-if="expandedId === v.id">
						<view class="details-grid">
							<view class="details-stat">
								<text class="stat-value">{{ formatDuration(v.duration) }}</text>
								<text class="stat-label">时长</text>
							</view>
							<view class="details-stat">
								<text class="stat-value">{{ v.views || 0 }}</text>
								<text class="stat-label">播放量</text>
							</view>
							<view class="details-stat">
								<text class="stat-value">{{ v.comment_count || 0 }}</text>
								<text class="stat-label">评论数</text>
							</view>
						</view>
						<view class="admin-item-actions">
							<text class="action-btn" @click.stop="goReview(v.id)">查看视频</text>
							<view style="flex: 1"></view>
							<text class="action-btn action-danger" @click.stop="deleteVideo(v.id)">删除</text>
						</view>
					</view>
				</view>
				<view class="load-more-status" v-if="videoList.length > 0">
					<text v-if="loadingVideos">正在加载...</text>
					<text v-else-if="hasMoreVideos" @click="loadNextVideos">加载更多视频</text>
					<text v-else>—— 已加载全部 ——</text>
				</view>
				<view class="empty-state" v-if="videoList.length === 0 && !loadingVideos">
					<text>暂无相关视频</text>
				</view>
			</view>

			<!-- 评论管理 -->
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
								<text class="result-title">{{ v.title }}</text>
								<text class="result-uploader">{{ v.uploader || '系统' }}</text>
							</view>
						</view>
					</view>
					<view class="search-mask" v-if="showSearchResult" @click="showSearchResult = false"></view>
				</view>

				<view class="admin-item" v-for="c in commentList" :key="c.id">
					<view class="admin-item-info">
						<text class="admin-item-title">{{ c.content }}</text>
						<text class="admin-item-meta">
							<uni-icons type="person" size="12" color="#666" style="margin-right:4rpx;"/>
							<text>{{ c.username }}</text>
							<text style="margin: 0 10rpx; color: #444;">·</text>
							<uni-icons type="videocam" size="12" color="#666" style="margin-right:4rpx;"/>
							<text>{{ c.video_title || '未知视频' }}</text>
							<text style="margin: 0 10rpx; color: #444;">·</text>
							<text>{{ formatTime(c.timestamp) }}</text>
						</text>
					</view>
					<view class="admin-item-actions">
						<text class="action-btn action-danger" @click="deleteComment(c.id)">删除</text>
					</view>
				</view>
				
				<view class="load-more-status" v-if="commentList.length > 0">
					<text v-if="loadingComments">正在加载...</text>
					<text v-else-if="hasMoreComments" @click="loadNextComments">加载更多评论</text>
					<text v-else>—— 已加载全部 ——</text>
				</view>

				<view class="empty-state" v-if="commentList.length === 0 && !loadingComments">
					<text>暂无相关评论</text>
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
import { onShow } from '@dcloudio/uni-app'
import Header from '@/components/Header.vue'
import { usePagination } from '@/composables/usePagination'

const authStore = useAuthStore()

const tab = ref('videos')
const videoOptions = ref([]) // For selector
const selectedVideoId = ref(null)
const expandedId = ref(null)

// Video pagination
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


// Comment pagination
const videoSearchQuery = ref('') // Search in selector
const commentSearchQuery = ref('') // Real search q
const showSearchResult = ref(false) // Selector dropdown

const {
	dataList: commentList,
	loading: loadingComments,
	hasMore: hasMoreComments,
	loadNextPage: loadNextComments,
	reset: resetComments
} = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/comments/admin`,
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
	refreshVideos()
	refreshComments()
	fetchVideoOptions()
})

function switchTab(newTab) {
	tab.value = newTab
	if (newTab === 'videos') {
		if (videoList.value.length === 0) refreshVideos()
	} else {
		if (commentList.value.length === 0) refreshComments()
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

function onSearchVideos() {
	refreshVideos()
}

function refreshComments() {
	resetComments()
	loadNextComments()
}

function handleClearVideoQuery() {
	videoQuery.value = ''
	refreshVideos()
}


function onVideoFilterChange(e) {
	const index = e.detail.value
	if (index === 0) {
		selectedVideoId.value = null
	} else {
		selectedVideoId.value = videos.value[index - 1]?.id || null
	}
}

function selectVideoFilter(video) {
	if (!video) {
		selectedVideoId.value = null
		videoSearchQuery.value = ''
	} else {
		selectedVideoId.value = video.id
		videoSearchQuery.value = video.title
	}
	showSearchResult.value = false
	refreshComments()
}

function handleClearVideoFilter() {
	videoSearchQuery.value = ''
	selectedVideoId.value = null
	refreshComments()
}

function toggleExpand(id) {
	expandedId.value = expandedId.value === id ? null : id
}

function formatDuration(seconds) {
	if (!seconds) return '00:00'
	const m = Math.floor(seconds / 60)
	const s = seconds % 60
	return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

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

function formatTime(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<style scoped>

.admin-container {
	padding: 0 30rpx 40rpx;
}

.admin-tabs {
	position: sticky;
	top: 0;
	background: #0f0f1a;
	z-index: 100;
	padding-top: 20rpx;
	margin-bottom: 40rpx;
}

.tab-header {
	display: flex;
	justify-content: space-around;
	padding: 10rpx 0;
}

.tab-item {
	font-size: 30rpx;
	color: #666;
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
	width: 50%;
	height: 100%;
	background: linear-gradient(90deg, #6c5ce7, #a855f7);
	border-radius: 2rpx;
	transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	transform: translateX(0);
}

.at-right {
	transform: translateX(100%);
}

.filter-section {
	margin-bottom: 30rpx;
	position: relative;
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
	left: 0;
	right: 0;
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

.result-thumb {
	width: 80rpx;
	height: 60rpx;
	background: #000;
	border-radius: 6rpx;
	margin-right: 20rpx;
	overflow: hidden;
}

.result-thumb video {
	width: 100%;
	height: 100%;
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

.stat-label {
	font-size: 20rpx;
	color: #555;
	text-transform: uppercase;
	margin-top: 4rpx;
}

.admin-item-actions {
	display: flex;
	align-items: center;
	gap: 24rpx;
	padding-top: 10rpx;
}

.action-btn {
	font-size: 26rpx;
	color: #6c5ce7;
	padding: 12rpx 24rpx;
	background: rgba(108, 92, 231, 0.1);
	border-radius: 8rpx;
	font-weight: 500;
}

.action-btn:active {
	opacity: 0.7;
}

.action-danger {
	color: #ff5e5e;
	background: rgba(255, 94, 94, 0.1);
}

/* 兼容现有样式的评论管理项 */
.admin-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.05);
	border-radius: 12rpx;
	padding: 24rpx 30rpx;
	margin-bottom: 12rpx;
}

.admin-item-meta-old {
	font-size: 22rpx;
	color: #666;
	margin-top: 6rpx;
	display: block;
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
</style>
