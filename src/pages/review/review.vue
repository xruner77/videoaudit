<template>
	<view class="page">
		<Header :title="videoTitle || '审片'" showBack />

		<!-- 初始加载状态 -->
		<view v-if="pageLoading" class="page-loading">
			<view class="spinner"></view>
			<text class="loading-text">正在加载视频数据...</text>
		</view>

		<view v-else class="review-container">
			<!-- 视频播放器区域 -->
			<VideoPlayer
				:videoUrl="videoUrl"
				:isPlaying="isPlaying"
				:isLoading="isLoading"
				:currentTime="currentTime"
				:duration="duration"
				:playbackRate="playbackRate"
				:isFullscreen="isFullscreen"
				:isRotated="isRotated"
				:commentDots="commentDots"
				:activeCommentId="activeCommentId"
				:seekMessage="seekMessage"
				:progressPercent="progressPercent"
				@togglePlay="togglePlay"
				@skip="skip"
				@cycleSpeed="cycleSpeed"
				@toggleFullscreen="toggleFullscreen"
				@dotClick="onDotClick"
				@timeupdate="onTimeUpdate"
				@play="onPlay"
				@pause="onPause"
				@ended="onEnded"
				@metaloaded="onMetaLoaded"
				@progressUpdate="onProgressUpdate"
			/>

			<!-- 视频信息展示区 -->
			<view class="video-meta-section">
				<view class="meta-details">
					<view class="meta-item">
						<uni-icons type="person" size="14" color="#999"/>
						<text class="meta-text">{{ videoUploader || '未知' }}</text>
					</view>
					<text class="meta-dot">·</text>
					<view class="meta-item">
						<uni-icons type="calendar" size="14" color="#999"/>
						<text class="meta-text">{{ videoUploadDate }}</text>
					</view>
					<view style="flex: 1"></view>
					<view class="meta-item">
						<uni-icons type="eye" size="14" color="#999"/>
						<text class="meta-text">{{ videoViews }}</text>
					</view>
				</view>
			</view>

			<!-- 评论输入 -->
			<CommentForm
				:isLoggedIn="authStore.isLoggedIn"
				:replyTo="replyTo"
				:commentTimestamp="commentTimestamp"
				:currentTime="currentTime"
				:videoId="videoId"
				@cancelReply="cancelReply"
				@pauseForComment="pauseForComment"
				@submitted="onCommentSubmitted"
			/>

			<!-- 评论列表 -->
			<CommentThread
				:comments="dataList"
				:activeCommentId="activeCommentId"
				:isAdmin="authStore.isAdmin"
				:currentUserId="authStore.user?.sub"
				:loadingComments="loadingComments"
				:hasMoreComments="hasMoreComments"
				@seekTo="onCommentSeekTo"
				@reply="onReply"
				@deleted="onCommentDeleted"
				@loadMore="loadNextPage"
			/>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, onUnmounted } from 'vue'
import { onLoad, onReachBottom } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import VideoPlayer from './components/VideoPlayer.vue'
import CommentForm from './components/CommentForm.vue'
import CommentThread from './components/CommentThread.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'
import { formatTime, formatDate } from '../../composables/useUtils'
import { request } from '../../composables/useRequest'

const authStore = useAuthStore()

// --- 视频状态 ---
const pageLoading = ref(true)
const videoId = ref(0)
const videoUrl = ref('')
const videoTitle = ref('')
const videoUploader = ref('')
const videoUploadDate = ref('')
const videoViews = ref(0)
const isPlaying = ref(false)
const isLoading = ref(false)
let bufferTimer = null
let lastTimeUpdateTs = 0
const currentTime = ref(0)
const duration = ref(0)
const playbackRate = ref(1)
const commentTimestamp = ref(-1)

// --- 评论分页 ---
const {
	dataList,
	loading: loadingComments,
	hasMore: hasMoreComments,
	loadNextPage,
	reset: resetComments
} = usePagination(async (params) => {
	if (!videoId.value) return { data: [], total: 0 }
	const res = await request({
		url: `${authStore.API_BASE}/api/comments/${videoId.value}`,
		method: 'GET',
		data: params
	})
	if (res.statusCode === 200) {
		return {
			data: res.data.comments,
			total: res.data.total
		}
	}
	return { data: [], total: 0 }
}, { limit: 20 })

// --- 其他状态 ---
const markers = ref([])
const activeCommentId = ref(null)
const replyTo = ref(null)
const seekMessage = ref('')
const isFullscreen = ref(false)
const isRotated = ref(false)
let seekTimer = null
let videoContext = null

const speeds = [0.5, 0.75, 1, 1.25, 1.5, 2]

// --- 计算属性 ---
const progressPercent = computed(() => {
	if (duration.value <= 0) return 0
	return (currentTime.value / duration.value) * 100
})

const commentDots = computed(() => {
	if (duration.value <= 0) return []
	
	const sorted = [...markers.value].sort((a, b) => a.timestamp - b.timestamp)
	const result = []
	const threshold = 1.2
	
	const clusters = []
	sorted.forEach(c => {
		const percent = (c.timestamp / duration.value) * 100
		let added = false
		for (const cluster of clusters) {
			if (Math.abs(cluster.centerPercent - percent) < threshold) {
				cluster.items.push(c)
				added = true
				break
			}
		}
		if (!added) {
			clusters.push({ centerPercent: percent, items: [c] })
		}
	})

	clusters.forEach(cluster => {
		const items = cluster.items.sort((a, b) => a.id - b.id)
		items.forEach((c, idx) => {
			const percent = (c.timestamp / duration.value) * 100
			let offset = 0
			if (items.length > 1) {
				const mid = (items.length - 1) / 2
				offset = (idx - mid) * 14
			}
			result.push({
				id: c.id,
				time: c.timestamp,
				percent,
				offset,
				username: c.username,
				zIndex: 10 + idx
			})
		})
	})

	return result
})

// --- 生命周期 ---
onLoad((options) => {
	videoId.value = parseInt(options.id)
	fetchVideoDetail()
	fetchMarkers()
	resetComments()
	loadNextPage()
	videoContext = uni.createVideoContext('reviewVideo')
	
	if (typeof document !== 'undefined') {
		document.addEventListener('fullscreenchange', onDocFullscreenChange)
	}
})

onReachBottom(() => {
	loadNextPage()
})

onUnmounted(() => {
	if (videoContext) {
		videoContext.pause()
		videoContext = null
	}
	if (bufferTimer) {
		clearInterval(bufferTimer)
		bufferTimer = null
	}
	if (seekTimer) {
		clearTimeout(seekTimer)
		seekTimer = null
	}
	if (typeof document !== 'undefined') {
		document.removeEventListener('fullscreenchange', onDocFullscreenChange)
	}
})

// --- API ---
async function fetchVideoDetail() {
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/videos/${videoId.value}`,
			method: 'GET'
		})
		if (res.statusCode === 200 && res.data.video) {
			const video = res.data.video
			videoTitle.value = video.title
			videoUploader.value = video.uploader || '未知'
			
			if (video.created_at) {
				videoUploadDate.value = formatDate(video.created_at)
			}
			
			videoViews.value = video.views || 0

			const url = video.type === 'local'
				? `${authStore.API_BASE}${video.url}`
				: video.url
			
			videoUrl.value = url.includes('#t=') ? url : url + '#t=0.1'
				
			incrementViewCount(video.id)
		}
	} catch (e) {
		console.error('Failed to fetch video:', e)
	} finally {
		pageLoading.value = false
	}
}

async function fetchMarkers() {
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/comments/${videoId.value}/markers`,
			method: 'GET'
		})
		if (res.statusCode === 200) {
			markers.value = res.data.markers || []
		}
	} catch (e) {
		console.error('Failed to fetch markers:', e)
	}
}

function incrementViewCount(id) {
	request({
		url: `${authStore.API_BASE}/api/videos/${id}/view`,
		method: 'POST'
	}).catch(() => {}) // 静默忽略——浏览计数为非关键操作
}

// --- VideoPlayer 事件处理 ---
function onTimeUpdate({ currentTime: ct, duration: dur }) {
	currentTime.value = ct
	duration.value = dur
	lastTimeUpdateTs = Date.now()
	if (isLoading.value && !seekMessage.value) {
		isLoading.value = false
	}
}

function onPlay() {
	isPlaying.value = true
	isLoading.value = false
	startBufferCheck()
}

function onPause() {
	isPlaying.value = false
	isLoading.value = false
}

function onEnded() {
	isPlaying.value = false
	isLoading.value = false
}

function onMetaLoaded({ duration: dur }) {
	if (dur) {
		duration.value = dur
	}
	isLoading.value = false
}

function onProgressUpdate({ time, shouldSeek }) {
	currentTime.value = time
	activeCommentId.value = null
	if (commentTimestamp.value >= 0) {
		commentTimestamp.value = time
	}
	if (shouldSeek && videoContext) {
		videoContext.seek(time)
	}
}

function onDotClick({ id, time }) {
	seekTo(id, time)
}

// --- 播放器控制 ---
function startBufferCheck() {
	if (bufferTimer) clearInterval(bufferTimer)
	lastTimeUpdateTs = Date.now()
	bufferTimer = setInterval(() => {
		if (isPlaying.value && Date.now() - lastTimeUpdateTs > 800) {
			isLoading.value = true
		}
		if (!isPlaying.value) {
			clearInterval(bufferTimer)
			bufferTimer = null
		}
	}, 500)
}

function togglePlay() {
	if (!videoContext) return
	if (isPlaying.value) {
		videoContext.pause()
	} else {
		videoContext.play()
	}
}

function skip(seconds) {
	if (!videoContext) return
	const newTime = Math.max(0, Math.min(currentTime.value + seconds, duration.value))
	videoContext.seek(newTime)
}

function seekTo(commentId, time) {
	if (commentId !== undefined && commentId !== null) {
		activeCommentId.value = commentId
	}
	if (videoContext && time >= 0) {
		if (seekTimer) clearTimeout(seekTimer)
		seekMessage.value = `正在跳转至 ${formatTime(time)}`
		
		currentTime.value = time
		
		if (commentTimestamp.value >= 0) {
			commentTimestamp.value = time
		}
		
		videoContext.seek(time)
		videoContext.pause()
		
		seekTimer = setTimeout(() => {
			seekMessage.value = ''
		}, 1200)
	}
}

function cycleSpeed() {
	const currentIndex = speeds.indexOf(playbackRate.value)
	const nextIndex = (currentIndex + 1) % speeds.length
	playbackRate.value = speeds[nextIndex]
	if (videoContext) {
		videoContext.playbackRate(playbackRate.value)
	}
}

async function toggleFullscreen() {
	if (!isFullscreen.value) {
		try {
			if (document.documentElement.requestFullscreen) {
				await document.documentElement.requestFullscreen()
			}
			isFullscreen.value = true
		} catch (e) {
			isFullscreen.value = true
		}
	} else {
		try {
			if (document.exitFullscreen && document.fullscreenElement) {
				await document.exitFullscreen()
			}
		} catch (e) { console.warn('exitFullscreen failed:', e) }
		isFullscreen.value = false
		isRotated.value = false
	}
}

function onDocFullscreenChange() {
	if (typeof document !== 'undefined') {
		const isNativeFullscreen = !!document.fullscreenElement
		if (!isNativeFullscreen && isFullscreen.value) {
			isFullscreen.value = false
			isRotated.value = false
		}
	}
}

// --- CommentForm 事件处理 ---
function pauseForComment() {
	if (videoContext && isPlaying.value) {
		videoContext.pause()
	}
	commentTimestamp.value = currentTime.value
}

function cancelReply() {
	replyTo.value = null
}

function onCommentSubmitted() {
	commentTimestamp.value = -1
	replyTo.value = null
	resetComments()
	loadNextPage()
	fetchMarkers()
}

// --- CommentThread 事件处理 ---
function onCommentSeekTo({ id, time }) {
	seekTo(id, time)
}

function onReply({ id, username, timestamp }) {
	replyTo.value = { id, username }
	seekTo(id, timestamp)
	commentTimestamp.value = timestamp
}

function onCommentDeleted() {
	resetComments()
	loadNextPage()
	fetchMarkers()
}
</script>

<style scoped>
.page {
	min-height: 100vh;
	background: #0f0f1a;
}

.review-container {
	padding-bottom: 200rpx;
}

/* 全局加载提示 */
.page-loading {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	height: 60vh;
}

.spinner {
	width: 60rpx;
	height: 60rpx;
	border: 6rpx solid rgba(255, 255, 255, 0.1);
	border-top-color: #a29bfe;
	border-radius: 50%;
	animation: spin 1s linear infinite;
	margin-bottom: 20rpx;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

.loading-text {
	color: #888;
	font-size: 26rpx;
	letter-spacing: 2rpx;
}

/* 视频元信息 */
.video-meta-section {
	padding: 20rpx 24rpx;
	margin: 0 24rpx 24rpx;
	background: rgba(255, 255, 255, 0.04);
	border-radius: 16rpx;
	border: 1px solid rgba(255, 255, 255, 0.05);
}

.meta-details {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 12rpx;
}

.meta-item {
	font-size: 24rpx;
	color: #999;
	display: flex;
	align-items: center;
	gap: 6rpx;
}

.meta-text {
	font-size: 24rpx;
	color: #999;
}

.meta-dot {
	color: #555;
	font-size: 24rpx;
	margin: 0 4rpx;
}
</style>
