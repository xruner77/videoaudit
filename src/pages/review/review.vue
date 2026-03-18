<template>
	<view class="page">
		<Header title="审片" showBack />

		<view class="review-container">
			<!-- 视频播放器区域 -->
			<view class="player-section">
				<view class="video-wrapper" @click="togglePlay">
					<video
						id="reviewVideo"
						class="video-player"
						:src="videoUrl"
						:controls="false"
						:show-center-play-btn="false"
						playsinline
						webkit-playsinline
						@timeupdate="onTimeUpdate"
						@play="onPlay"
						@pause="isPlaying = false; isLoading = false"
						@ended="isPlaying = false; isLoading = false"
						@loadedmetadata="onMetaLoaded"
					></video>
					
					<!-- 缓冲 Loading 动画 -->
					<view class="loading-overlay" v-show="isLoading">
						<view class="loading-spinner"></view>
					</view>
					
					<!-- 居中播放按钮 -->
					<view class="center-play-btn" v-show="!isPlaying">
						<text class="center-play-icon">▶</text>
					</view>
				</view>

				<!-- 自定义控制条 -->
				<view class="controls">
					<view class="controls-top">
						<!-- 进度条 -->
						<view 
						class="progress-bar" 
						@touchstart.prevent="onProgressTouchStart"
						@touchmove.prevent="onProgressTouchMove"
						@touchend.prevent="onProgressTouchEnd"
						@click="onProgressClick"
					>		<view class="progress-track" id="progressTrack">
								<view class="progress-fill" :style="{ width: progressPercent + '%' }"></view>
								<view class="progress-thumb" :style="{ left: progressPercent + '%' }"></view>
								<!-- 评论打点 -->
								<view
									class="comment-dot"
									v-for="dot in commentDots"
									:key="dot.time"
									:style="{ left: dot.percent + '%' }"
									@click.stop="seekTo(dot.time)"
								></view>
							</view>
						</view>
					</view>

					<view class="controls-bottom">
						<view class="controls-left">
							<text class="time-current">{{ formatTime(currentTime) }}</text>
							<text class="time-separator">/</text>
							<text class="time-total">{{ formatTime(duration) }}</text>
						</view>
						<view class="controls-center">
							<view class="skip-btn-circle" @click="skip(-5)">
								<view class="skip-icon-rw">
									<view class="triangle-left"></view>
									<view class="triangle-left"></view>
								</view>
							</view>
							<view class="play-btn-circle" @click="togglePlay">
								<view class="pause-icon" v-if="isPlaying">
									<view class="pause-bar"></view>
									<view class="pause-bar"></view>
								</view>
								<view class="play-icon-css" v-else></view>
							</view>
							<view class="skip-btn-circle" @click="skip(5)">
								<view class="skip-icon-ff">
									<view class="triangle-right"></view>
									<view class="triangle-right"></view>
								</view>
							</view>
						</view>
						<view class="controls-right">
							<text class="speed-text" @click="cycleSpeed">{{ playbackRate }}倍</text>
							<view class="ctrl-icon-btn" @click="toggleFullscreen">
								<uni-icons type="scan" size="22" color="#d0d0e0" />
							</view>
						</view>
					</view>
				</view>
			</view>

			<!-- 视频信息展示区 -->
			<view class="video-meta-section">
				<text class="meta-title">{{ videoTitle }}</text>
				<view class="meta-details">
					<text class="meta-item"><uni-icons type="person" size="14" color="#888" style="margin-right:4rpx;"/>{{ videoUploader || '未知' }}</text>
					<text class="meta-item"><uni-icons type="calendar" size="14" color="#888" style="margin-right:4rpx;"/>{{ videoUploadDate }}</text>
					<text class="meta-item"><uni-icons type="eye" size="14" color="#888" style="margin-right:4rpx;"/>{{ videoViews }}</text>
				</view>
			</view>

			<!-- 评论输入 -->
			<view class="comment-input-section" v-if="authStore.isLoggedIn">
				<view class="input-row">
					<input
						class="dark-input comment-text-input"
						v-model="commentText"
						placeholder="输入审核意见..."
						@focus="pauseForComment"
						maxlength="500"
					/>
					<button class="btn-primary send-btn" @click="submitComment" :loading="submitting">发送</button>
				</view>
				<text class="input-hint" v-if="commentTimestamp >= 0">
					📍 将标记在 {{ formatTime(commentTimestamp) }}
				</text>
			</view>
			<view class="login-hint" v-else>
				<text @click="goLogin">请先登录后发表评论 →</text>
			</view>

			<!-- 评论列表 -->
			<view class="comment-list">
				<text class="section-title">审核意见 ({{ comments.length }})</text>

				<view class="comment-item" v-for="c in comments" :key="c.id" @click="seekTo(c.timestamp)">
					<view class="comment-header">
						<view class="comment-user">
							<view class="comment-avatar">
								<uni-icons type="person-filled" size="18" color="#d0d0e0" />
							</view>
							<text class="comment-username">{{ c.username }}</text>
						</view>
						<text class="comment-time">
							⏱ {{ formatTime(c.timestamp) }}
						</text>
					</view>
					<text class="comment-content">{{ c.content }}</text>
					<view class="comment-actions" v-if="canDeleteComment(c)">
						<text class="delete-btn" @click.stop="deleteComment(c.id)">删除</text>
					</view>
				</view>

				<view class="empty-comments" v-if="comments.length === 0">
					<text>暂无审核意见</text>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed, onUnmounted, getCurrentInstance } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'

const authStore = useAuthStore()

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
const commentText = ref('')
const commentTimestamp = ref(-1)
const submitting = ref(false)
const comments = ref([])
let videoContext = null

const speeds = [0.5, 0.75, 1, 1.25, 1.5, 2]

const progressPercent = computed(() => {
	if (duration.value <= 0) return 0
	return (currentTime.value / duration.value) * 100
})

const commentDots = computed(() => {
	if (duration.value <= 0) return []
	return comments.value.map(c => ({
		time: c.timestamp,
		percent: (c.timestamp / duration.value) * 100
	}))
})

onLoad((options) => {
	videoId.value = parseInt(options.id)
	fetchVideoDetail()
	fetchComments()
	videoContext = uni.createVideoContext('reviewVideo')
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
})

const instance = getCurrentInstance()

async function fetchVideoDetail() {
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/videos`,
			method: 'GET'
		})
		if (res.statusCode === 200) {
			const video = (res.data.videos || []).find(v => v.id == videoId.value)
			if (video) {
				videoTitle.value = video.title
				videoUploader.value = video.uploader || '未知'
				
				// 格式化日期
				if (video.created_at) {
					const d = new Date(video.created_at)
					videoUploadDate.value = `${d.getFullYear()}-${(d.getMonth() + 1).toString().padStart(2, '0')}-${d.getDate().toString().padStart(2, '0')}`
				}
				
				// 真实观看次数
				videoViews.value = video.views || 0

				// 本地视频需要拼接后端地址
				videoUrl.value = video.type === 'local'
					? `${authStore.API_BASE}${video.url}`
					: video.url
					
				// 增加播放量
				incrementViewCount(video.id)
			}
		}
	} catch (e) {
		console.error('Failed to fetch video:', e)
	}
}

function incrementViewCount(id) {
	uni.request({
		url: `${authStore.API_BASE}/api/videos/${id}/view`,
		method: 'POST'
	})
}

async function fetchComments() {
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments/${videoId.value}`,
			method: 'GET'
		})
		if (res.statusCode === 200) {
			comments.value = res.data.comments || []
		}
	} catch (e) {
		console.error('Failed to fetch comments:', e)
	}
}

function onPlay() {
	isPlaying.value = true
	isLoading.value = false
	startBufferCheck()
}

function onTimeUpdate(e) {
	// 如果用户正在拖动进度条，不要让视频实际播放进度覆盖拖动进度
	if (isDragging.value) return
	currentTime.value = e.detail.currentTime
	duration.value = e.detail.duration
	// 收到 timeupdate 说明在正常播放，关闭 loading
	lastTimeUpdateTs = Date.now()
	if (isLoading.value) {
		isLoading.value = false
	}
}

function onMetaLoaded(e) {
	if (e.detail && e.detail.duration) {
		duration.value = e.detail.duration
	}
	isLoading.value = false
}

function startBufferCheck() {
	if (bufferTimer) clearInterval(bufferTimer)
	lastTimeUpdateTs = Date.now()
	bufferTimer = setInterval(() => {
		// 如果正在播放但超过800ms没收到timeupdate，说明在缓冲
		if (isPlaying.value && Date.now() - lastTimeUpdateTs > 800) {
			isLoading.value = true
		}
		// 如果不在播放状态，停止检测
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

function seekTo(time) {
	if (videoContext && time >= 0) {
		videoContext.seek(time)
		videoContext.pause()
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

function toggleFullscreen() {
	if (videoContext) {
		videoContext.requestFullScreen()
	}
}

// --- 进度条手势控制 ---
const isDragging = ref(false)

function onProgressTouchStart(e) {
	if (!duration.value) return
	isDragging.value = true
	updateProgressByEvent(e)
}

function onProgressTouchMove(e) {
	if (!duration.value || !isDragging.value) return
	updateProgressByEvent(e)
}

function onProgressTouchEnd(e) {
	if (!duration.value) return
	isDragging.value = false
	updateProgressByEvent(e, true)
}

function onProgressClick(e) {
	if (!duration.value) return
	updateProgressByEvent(e, true)
}

function updateProgressByEvent(e, shouldSeek = false) {
	let clientX = 0
	
	if (e.touches && e.touches.length > 0) {
		clientX = e.touches[0].clientX
	} else if (e.changedTouches && e.changedTouches.length > 0) {
		clientX = e.changedTouches[0].clientX
	} else if (e.clientX !== undefined) {
		clientX = e.clientX
	} else {
		return
	}

	const query = uni.createSelectorQuery().in(instance.proxy)
	query.select('.progress-bar').boundingClientRect(data => {
		if (data) {
			let x = clientX - data.left
			x = Math.max(0, Math.min(x, data.width))
			const ratio = x / data.width
			const targetTime = ratio * duration.value
			
			// 拖动过程中仅更新 UI
			currentTime.value = targetTime
			
			// 只有在拖动结束或点击时，才真正调用 API 执行 seek
			if (shouldSeek && videoContext) {
				videoContext.seek(targetTime)
			}
		}
	}).exec()
}

function pauseForComment() {
	if (videoContext && isPlaying.value) {
		videoContext.pause()
	}
	commentTimestamp.value = currentTime.value
}

async function submitComment() {
	if (!commentText.value.trim()) {
		return uni.showToast({ title: '请输入评论内容', icon: 'none' })
	}

	submitting.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments`,
			method: 'POST',
			header: {
				'Content-Type': 'application/json',
				...authStore.getAuthHeader()
			},
			data: {
				video_id: videoId.value,
				content: commentText.value.trim(),
				timestamp: commentTimestamp.value >= 0 ? commentTimestamp.value : currentTime.value
			}
		})

		if (res.statusCode === 201) {
			uni.showToast({ title: '评论成功', icon: 'success' })
			commentText.value = ''
			commentTimestamp.value = -1
			fetchComments()
		} else {
			throw new Error(res.data?.error || '评论失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message || '评论失败', icon: 'none' })
	} finally {
		submitting.value = false
	}
}

function canDeleteComment(comment) {
	if (!authStore.isLoggedIn) return false
	return authStore.isAdmin || comment.user_id == authStore.user?.id
}

async function deleteComment(commentId) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除这条评论吗？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await uni.request({
					url: `${authStore.API_BASE}/api/comments/${commentId}`,
					method: 'DELETE',
					header: authStore.getAuthHeader()
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					fetchComments()
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

function goLogin() {
	uni.navigateTo({ url: '/pages/login/login' })
}

function formatTime(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
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

/* 播放器 */
.player-section {
	position: sticky;
	top: 88rpx;
	z-index: 99;
	background: #000;
	margin-bottom: 24rpx;
	box-shadow: 0 12rpx 30rpx rgba(0,0,0,0.6);
}

.video-wrapper {
	position: relative;
	cursor: pointer;
}

.video-player {
	width: 100%;
	height: 420rpx;
	display: block;
}

.loading-overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.4);
	display: flex;
	align-items: center;
	justify-content: center;
	pointer-events: none;
}

.loading-spinner {
	width: 80rpx;
	height: 80rpx;
	border: 6rpx solid rgba(255, 255, 255, 0.3);
	border-radius: 50%;
	border-top-color: #fff;
	animation: spin 1s ease-in-out infinite;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

.center-play-btn {
	position: absolute;
	top: 50%;
	left: 50%;
	transform: translate(-50%, -50%);
	width: 100rpx;
	height: 100rpx;
	border-radius: 50%;
	background: rgba(108, 92, 231, 0.8);
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.5);
	pointer-events: none;
}

.center-play-icon {
	font-size: 44rpx;
	color: #fff;
	margin-left: 6rpx;
}

.controls {
	padding: 12rpx 20rpx 16rpx;
	background: linear-gradient(to top, rgba(0,0,0,0.9), rgba(0,0,0,0.3));
}

.controls-top {
	margin-bottom: 12rpx;
}

.progress-bar {
	padding: 16rpx 0;
	cursor: pointer;
}

.progress-track {
	height: 6rpx;
	background: rgba(255, 255, 255, 0.2);
	border-radius: 3rpx;
	position: relative;
}

.progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #6c5ce7, #a855f7);
	border-radius: 3rpx;
	transition: width 0.1s linear;
}

.progress-thumb {
	position: absolute;
	top: 50%;
	width: 20rpx;
	height: 20rpx;
	border-radius: 50%;
	background: #a855f7;
	transform: translate(-50%, -50%);
	box-shadow: 0 0 8rpx rgba(168, 85, 247, 0.6);
}

.comment-dot {
	position: absolute;
	top: -4rpx;
	width: 14rpx;
	height: 14rpx;
	border-radius: 50%;
	background: #f39c12;
	transform: translateX(-50%);
	box-shadow: 0 0 8rpx rgba(243, 156, 18, 0.6);
}

.controls-bottom {
	display: flex;
	justify-content: space-between;
	align-items: center;
	height: 80rpx;
	padding: 0 10rpx;
}

.controls-left {
	flex: 1;
	display: flex;
	align-items: center;
}

.time-current {
	font-size: 26rpx;
	color: #ffffff;
	font-variant-numeric: tabular-nums;
}

.time-separator {
	font-size: 24rpx;
	color: #888899;
	margin: 0 4rpx;
}

.time-total {
	font-size: 26rpx;
	color: #888899;
	font-variant-numeric: tabular-nums;
}

.controls-center {
	display: flex;
	align-items: center;
	gap: 40rpx;
}

.ctrl-icon-btn {
	padding: 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.ctrl-icon-btn:active {
	opacity: 0.7;
}

.skip-btn-circle {
	width: 70rpx;
	height: 70rpx;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.06);
	display: flex;
	align-items: center;
	justify-content: center;
	transition: background 0.2s, transform 0.1s;
}

.skip-btn-circle:active {
	background: rgba(255, 255, 255, 0.4);
	transform: scale(0.95);
}

/* CSS rendered generic icons */
.triangle-right {
	width: 0;
	height: 0;
	border-top: 14rpx solid transparent;
	border-bottom: 14rpx solid transparent;
	border-left: 18rpx solid #ffffff;
}

.triangle-left {
	width: 0;
	height: 0;
	border-top: 14rpx solid transparent;
	border-bottom: 14rpx solid transparent;
	border-right: 18rpx solid #ffffff;
}

.skip-icon-ff, .skip-icon-rw {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 2rpx;
}
.skip-icon-ff {
	margin-left: 4rpx;
}
.skip-icon-rw {
	margin-right: 4rpx;
}

.play-btn-circle {
	width: 90rpx;
	height: 90rpx;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.12);
	display: flex;
	align-items: center;
	justify-content: center;
	transition: background 0.2s;
}

.play-btn-circle:active {
	background: rgba(255, 255, 255, 0.2);
}

.pause-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 12rpx;
	width: 100%;
	height: 100%;
}

.pause-bar {
	width: 8rpx;
	height: 36rpx;
	background-color: #ffffff;
	border-radius: 4rpx;
}

.play-icon-css {
	width: 0;
	height: 0;
	border-top: 20rpx solid transparent;
	border-bottom: 20rpx solid transparent;
	border-left: 28rpx solid #ffffff;
	margin-left: 8rpx; /* Optical center for a right-pointing triangle */
}

.controls-right {
	flex: 1;
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 30rpx;
}

.speed-text {
	font-size: 26rpx;
	color: #e2e2ea;
	cursor: pointer;
}

/* 视频元信息 */
.video-meta-section {
	padding: 0 24rpx 40rpx 24rpx;
	border-bottom: 1px solid rgba(255, 255, 255, 0.05);
	margin-bottom: 40rpx;
}

.meta-title {
	font-size: 34rpx;
	font-weight: 700;
	color: #fff;
	display: block;
	margin-bottom: 20rpx;
}

.meta-details {
	display: flex;
	align-items: center;
	flex-wrap: wrap;
	gap: 30rpx;
}

.meta-item {
	font-size: 24rpx;
	color: #888;
	display: flex;
	align-items: center;
}

/* 评论输入 */
.comment-input-section {
	position: fixed;
	bottom: 0;
	left: 0;
	width: 100%;
	box-sizing: border-box;
	background: rgba(15, 15, 26, 0.95);
	backdrop-filter: blur(10px);
	padding: 24rpx 24rpx calc(24rpx + env(safe-area-inset-bottom));
	border-top: 1px solid rgba(255, 255, 255, 0.08);
	z-index: 100;
}

.input-row {
	display: flex;
	gap: 16rpx;
	align-items: center;
}

.comment-text-input {
	flex: 1;
}

.send-btn {
	width: 140rpx;
	height: 72rpx;
	line-height: 72rpx;
	padding: 0;
	font-size: 28rpx;
}

.input-hint {
	font-size: 22rpx;
	color: #f39c12;
	margin-top: 10rpx;
	display: block;
}

.login-hint {
	position: fixed;
	bottom: 0;
	left: 0;
	width: 100%;
	box-sizing: border-box;
	background: rgba(15, 15, 26, 0.95);
	backdrop-filter: blur(10px);
	padding: 30rpx 24rpx calc(30rpx + env(safe-area-inset-bottom));
	border-top: 1px solid rgba(255, 255, 255, 0.08);
	z-index: 100;
	text-align: center;
}

.login-hint text {
	color: #6c5ce7;
	font-size: 26rpx;
}

/* 评论列表 */
.comment-list {
	padding: 24rpx;
}

.section-title {
	font-size: 30rpx;
	font-weight: 700;
	color: #c0c0d0;
	margin-bottom: 20rpx;
	display: block;
}

.comment-item {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.05);
	border-radius: 12rpx;
	padding: 20rpx;
	margin-bottom: 16rpx;
}

.comment-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
}

.comment-user {
	display: flex;
	align-items: center;
	gap: 16rpx;
}

.comment-avatar {
	width: 52rpx;
	height: 52rpx;
	border-radius: 50%;
	background: #3d3d52;
	display: flex;
	align-items: center;
	justify-content: center;
}

.comment-username {
	font-size: 30rpx;
	color: #a0a0b8;
	font-weight: 500;
}

.comment-time {
	font-size: 28rpx;
	color: #f39c12; /* Distinct color for timestamp */
	background: rgba(243, 156, 18, 0.15);
	padding: 6rpx 16rpx;
	border-radius: 8rpx;
	font-variant-numeric: tabular-nums;
}

.comment-content {
	font-size: 32rpx;
	color: #ffffff;
	line-height: 1.6;
	word-break: break-all;
}

.comment-actions {
	margin-top: 12rpx;
	text-align: right;
}

.delete-btn {
	font-size: 22rpx;
	color: #e74c3c;
}

.empty-comments {
	text-align: center;
	padding: 40rpx;
}

.empty-comments text {
	color: #555;
	font-size: 26rpx;
}
</style>
