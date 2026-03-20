<template>
	<view class="page">
		<Header title="生活网审片系统" />
		
		<!-- 欢迎语与统计 -->
		<view class="welcome-section" v-if="authStore.isLoggedIn">
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
				<input 
					class="search-input" 
					v-model="searchQuery" 
					placeholder="搜索视频标题..." 
					confirm-type="search"
					@confirm="onSearch"
				/>
				<view class="clear-btn" v-if="searchQuery" @click="clearSearch">
					<uni-icons type="closeempty" size="14" color="#888" />
				</view>
			</view>
		</view>

		<view class="video-grid">
			<view class="video-card" v-for="video in dataList" :key="video.id" @click="goReview(video.id)">
				<view class="video-thumb">
					<video 
						class="thumb-video" 
						:src="getVideoThumbUrl(video)" 
						:controls="false" 
						:show-center-play-btn="false" 
						:enable-progress-gesture="false"
						object-fit="cover"
						muted
						preload="metadata"
						playsinline
						webkit-playsinline
						x5-video-player-type="h5-page"
					></video>
					<view class="thumb-overlay"></view>
					<view class="video-type-badge">
						<text><uni-icons type="chat" size="12" color="#fff" style="margin-right:4rpx;"/>{{ video.comment_count !== undefined ? video.comment_count : 0 }}</text>
					</view>
					<view class="video-duration-badge" v-if="video.duration">
						<text>{{ formatDuration(video.duration) }}</text>
					</view>
				</view>
				<view class="video-info">
					<view class="info-row">
						<text class="video-title">{{ video.title }}</text>
					</view>
					<view class="video-meta">
						<text class="meta-user"><uni-icons type="person" size="12" color="#aaa" style="margin-right:2rpx;"/>{{ video.uploader || '未知' }}</text>
						<view class="meta-stats">
							<text class="meta-date">{{ formatDate(video.created_at) }}</text>
							<text class="meta-comments" v-if="video.views !== undefined"><uni-icons type="eye" size="12" color="#aaa" style="margin-right:2rpx;"/>{{ video.views }}</text>
						</view>
					</view>
				</view>
			</view>

			<view class="empty-state" v-if="dataList.length === 0 && !loading">
				<text>暂无相关视频</text>
			</view>
			<view class="load-more-status" v-if="dataList.length > 0">
				<text v-if="loading">正在加载...</text>
				<text v-else-if="hasMore">继续滚动加载</text>
				<text v-else>—— 已加载全部视频 ——</text>
			</view>
		</view>

		<view class="fab" @click="goUpload" v-if="authStore.isLoggedIn">
			<text class="fab-icon"><uni-icons type="plusempty" size="24" color="#fff"/></text>
		</view>

		<!-- 首页底部页脚 -->
		<view class="footer">
			<image class="footer-logo" src="/static/logo_company.png" mode="aspectFit"></image>
			<text class="footer-copyright">© 桂林三新网络传媒有限责任公司 版权所有</text>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { onLoad, onShow, onReachBottom } from '@dcloudio/uni-app'
import { useAuthStore } from '@/stores/authStore'
import Header from '@/components/Header.vue'
import { usePagination } from '@/composables/usePagination'

const authStore = useAuthStore()
const searchQuery = ref('')

const loaded = ref(false)

const {
	dataList,
	loading,
	hasMore,
	total,
	loadNextPage,
	reset,
	silentRefresh
} = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/videos`,
		method: 'GET',
		data: {
			...params,
			q: searchQuery.value
		}
	})
	if (res.statusCode === 200 && res.data) {
		return {
			data: res.data.videos,
			total: res.data.total
		}
	}
	return { data: [], total: 0 }
}, { limit: 10 })

onLoad(() => {
	onSearch()
})

onShow(() => {
	if (loaded.value) {
		silentRefresh({ q: searchQuery.value })
	}
})

onReachBottom(() => {
	loadNextPage()
})

function onSearch() {
	reset()
	loadNextPage().then(() => { loaded.value = true })
}

function clearSearch() {
	searchQuery.value = ''
	onSearch()
}

function goReview(videoId) {
	uni.navigateTo({ url: `/pages/review/review?id=${videoId}` })
}

function goUpload() {
	uni.navigateTo({ url: '/pages/upload/upload' })
}

function formatDate(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getMonth() + 1}月${d.getDate()}日`
}

function formatDuration(seconds) {
	if (!seconds) return '00:00'
	const m = Math.floor(seconds / 60)
	const s = seconds % 60
	return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

function getVideoThumbUrl(video) {
	if (!video || !video.url) return ''
	let url = video.type === 'local' ? `${authStore.API_BASE}${video.url}` : video.url
	// 添加 #t=0.5 强制浏览器（特别是微信）渲染首帧或指定时间的画面
	if (!url.includes('#t=')) {
		url += '#t=0.5'
	}
	return url
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #0f0f1a;
	padding-bottom: 60rpx;
}

.search-bar-container {
	padding: 20rpx 30rpx;
	background: #0f0f1a;
	position: sticky;
	top: 0;
	z-index: 100;
}

.search-bar {
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.08);
	border-radius: 40rpx;
	height: 72rpx;
	display: flex;
	align-items: center;
	padding: 0 24rpx;
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

.video-grid {
	padding: 20rpx 30rpx 100rpx;
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 20rpx;
}

.video-card {
	width: 100%;
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	overflow: hidden;
	transition: transform 0.2s;
}

.video-card:active {
	transform: scale(0.97);
}

.video-thumb {
	height: 220rpx;
	background: #1a1a2e;
	position: relative;
	overflow: hidden;
}

.thumb-video {
	width: 100%;
	height: 100%;
	display: block;
	pointer-events: none;
}

.thumb-overlay {
	position: absolute;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: linear-gradient(to bottom, rgba(0,0,0,0.4) 0%, transparent 40%, rgba(0,0,0,0.6) 100%);
	pointer-events: none;
}

.video-type-badge {
	position: absolute;
	top: 12rpx;
	right: 12rpx;
	background: none;
}

.video-type-badge text {
	font-size: 22rpx;
	color: #fff;
	font-weight: bold;
	text-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.8);
}

.video-duration-badge {
	position: absolute;
	bottom: 12rpx;
	right: 12rpx;
}

.video-duration-badge text {
	font-size: 22rpx;
	color: #fff;
	font-weight: bold;
	text-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.8);
}

.video-info {
	padding: 16rpx 20rpx 24rpx;
}

.info-row {
	margin-bottom: 8rpx;
}

.video-title {
	font-size: 26rpx;
	font-weight: 600;
	color: #e0e0e0;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.video-meta {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.meta-stats {
	display: flex;
	align-items: center;
	gap: 16rpx;
}

.meta-user, .meta-date, .meta-comments {
	font-size: 22rpx;
	color: #666;
}

.empty-state {
	width: 100%;
	text-align: center;
	padding: 120rpx 0;
}

.empty-icon {
	font-size: 80rpx;
	display: block;
}

.empty-text {
	font-size: 32rpx;
	color: #555;
	display: block;
	margin-top: 20rpx;
}

.empty-hint {
	font-size: 24rpx;
	color: #444;
	display: block;
	margin-top: 10rpx;
}

.load-more-status {
	grid-column: span 2;
	width: 100%;
	text-align: center;
	padding: 80rpx 0;
	font-size: 24rpx;
	color: #444;
}

.fab {
	position: fixed;
	bottom: 160rpx;
	right: 40rpx;
	width: 100rpx;
	height: 100rpx;
	border-radius: 50%;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 8rpx 24rpx rgba(108, 92, 231, 0.4);
	z-index: 100;
}

.fab:active {
	transform: scale(0.9);
}

.fab-icon {
	font-size: 48rpx;
	color: #fff;
	font-weight: 300;
}

/* 页脚样式 */
.footer {
	padding: 20rpx 0 ; /* 进一步压缩占位 */
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
}

.footer-logo {
	width: 180rpx; /* 适度缩小 Logo */
	height: 60rpx;
	margin-bottom: 8rpx; /* 极细间距 */
	opacity: 0.8;
}

.footer-copyright {
	font-size: 22rpx;
	color: #444;
	letter-spacing: 1rpx;
}

/* 欢迎区域样式 */
.welcome-section {
	padding: 30rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: linear-gradient(to bottom, #1a1a2e 0%, #0f0f1a 100%);
}

.welcome-text {
	display: flex;
	flex-direction: column;
}

.user-greeting {
	font-size: 34rpx;
	font-weight: 600;
	color: #fff;
	margin-bottom: 4rpx;
}

.system-welcome {
	font-size: 22rpx;
	color: #888;
}

.stats-badge {
	background: rgba(108, 92, 231, 0.1);
	border: 1px solid rgba(108, 92, 231, 0.2);
	padding: 16rpx 24rpx;
	border-radius: 20rpx;
}

.stats-item {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.stats-value {
	font-size: 32rpx;
	font-weight: bold;
	color: #6c5ce7;
	line-height: 1;
	margin-bottom: 4rpx;
}

.stats-label {
	font-size: 18rpx;
	color: #666;
	text-transform: uppercase;
}
</style>
