<template>
	<view class="page">
		<Header :title="videoTitle || '审片'" showBack />

		<view class="review-container">
			<!-- 视频播放器区域 -->
			<view class="player-section" :class="{ 'is-fullscreen': isFullscreen, 'is-rotated': isRotated }">
				<view class="video-wrapper" @click="togglePlay">
					<video
						id="reviewVideo"
						class="video-player"
						:src="videoUrl"
						:controls="false"
						:show-center-play-btn="false"
						playsinline
						webkit-playsinline
						x5-video-player-type="h5-page"
						@timeupdate="onTimeUpdate"
						@play="onPlay"
						@pause="isPlaying = false; isLoading = false"
						@ended="isPlaying = false; isLoading = false"
						@loadedmetadata="onMetaLoaded"
					></video>
					
					<!-- 缓冲 Loading 动画 -->
					<view class="loading-overlay" v-show="isLoading || seekMessage">
						<view class="loading-spinner"></view>
						<text class="seek-message-text" v-if="seekMessage">{{ seekMessage }}</text>
					</view>
					
					<!-- 居中播放按钮 -->
					<view class="center-play-btn" v-show="!isPlaying && !isLoading && !seekMessage">
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
								<!-- 评论打点 (头像) -->
								<view
									class="comment-dot"
									:class="{ 'dot-active': selectedCommentId === dot.id }"
									v-for="dot in commentDots"
									:key="dot.id"
									:style="{ 
										left: dot.percent + '%', 
										transform: `translate(-50%, -50%) translateX(${dot.offset}rpx)`,
										zIndex: dot.zIndex,
										background: getAvatarColor(dot.username)
									}"
									@click.stop="seekTo(dot.id, dot.time)"
								>
									<text class="dot-avatar-letter">{{ getAvatarLetter(dot.username) }}</text>
								</view>
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
								<view class="icon-svg" :class="isFullscreen ? 'icon-shrink' : 'icon-expand'"></view>
							</view>
						</view>
					</view>
				</view>
			</view>

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
			<view class="comment-input-section" v-if="authStore.isLoggedIn">
				<!-- 回复提示 -->
				<view class="reply-hint" v-if="replyTo">
					<text class="reply-hint-text">回复 @{{ replyTo.username }}</text>
					<uni-icons type="closeempty" size="16" color="#888" @click="cancelReply" />
				</view>
				<view class="input-row">
					<view class="img-pick-btn" @click="chooseImage">
						<uni-icons type="camera" size="24" color="#b8b8b8" />
					</view>
					<textarea
						class="dark-input comment-text-textarea"
						v-model="commentText"
						:placeholder="replyTo ? '回复 ' + replyTo.username + '...' : '输入审核意见...'"
						@focus="pauseForComment"
						maxlength="500"
						auto-height
						:fixed="true"
					/>
					<button class="btn-primary send-btn" @click="submitComment" :loading="submitting">发送</button>
				</view>
				<!-- 图片预览 -->
				<view class="img-preview-row" v-if="selectedImages.length > 0">
					<view class="img-preview-item" v-for="(img, idx) in selectedImages" :key="idx">
						<image :src="img.path" class="img-thumb" mode="aspectFill" @click="previewSelectedImage(idx)" />
						<view class="img-upload-progress" v-if="img.uploading">
							<text class="progress-text">{{ img.progress }}%</text>
						</view>
						<view class="img-remove" @click="removeImage(idx)">
							<uni-icons type="closeempty" size="12" color="#fff" />
						</view>
					</view>
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
				<view class="comment-list-header">
					<text class="section-title">审核意见 ({{ comments.length }})</text>
					<view class="sort-btn" @click="showSortPopup = true">
						<text class="sort-text">{{ hasSorted ? sortLabel : '切换排序' }}</text>
						<!-- 自定义上下箭头 SVG -->
						<view class="sort-icon-wrapper">
							<svg viewBox="0 0 24 24" width="12" height="12" stroke="#a0a0b8" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
								<polyline points="7 15 12 20 17 15"></polyline>
								<polyline points="7 9 12 4 17 9"></polyline>
							</svg>
						</view>
					</view>
				</view>

				<!-- 父评论 -->
				<template v-for="c in sortedComments" :key="c.id">
					<view class="comment-item" :class="{ 'comment-item-active': activeCommentId === c.id }">
						<view class="comment-main" @click="seekTo(c.id, c.timestamp)">
							<view class="comment-avatar" :style="{ background: getAvatarColor(c.username) }">
								<text class="avatar-letter">{{ getAvatarLetter(c.username) }}</text>
							</view>
							<view class="comment-body">
								<view class="comment-header">
									<text class="comment-username">{{ c.username }}</text>
									<text class="comment-time" :class="{ 'time-active': activeCommentId === c.id }">
										{{ formatTime(c.timestamp) }}
									</text>
								</view>
								<view class="comment-content-box" v-if="c.content || c.image_url">
									<text class="comment-content" v-if="c.content">{{ c.content }}</text>
									<view class="comment-image-list" v-if="getImages(c.image_url).length > 0">
										<image 
											v-for="(img, idx) in getImages(c.image_url)" 
											:key="idx" 
											:src="getFullUrl(img)" 
											class="comment-image" 
											mode="widthFix" 
											@click.stop="previewCommentImage(c.image_url, idx)" 
										/>
									</view>
								</view>
								<view class="comment-actions">
									<text class="comment-date">{{ formatRelativeTime(c.created_at) }}</text>
									<text class="reply-btn" @click.stop="onReply(c)">回复</text>
									<text class="reply-btn delete-btn" v-if="authStore.isAdmin || c.user_id == authStore.user?.sub" @click.stop="deleteComment(c.id)">删除</text>
								</view>
							</view>
						</view>

						<!-- 子评论（全展平，顺序往下排列） -->
						<view class="replies-container" v-if="c.replies && c.replies.length > 0">
							<view class="reply-item" v-for="r in c.replies" :key="r.id" @click.stop="onCommentClick(c)">
								<view class="comment-avatar unified-avatar" :style="{ background: getAvatarColor(r.username) }">
									<text class="avatar-letter-small">{{ getAvatarLetter(r.username) }}</text>
								</view>
								<view class="reply-body">
									<view class="reply-header">
										<text class="reply-username">{{ r.username }}</text>
									</view>
									<view class="reply-content-box">
										<text class="reply-content">
											<text class="reply-to-at">回复 @{{ getReplyToUsername(r.parent_id) }}：</text>
											<text v-if="r.content">{{ r.content }}</text>
										</text>
										<view class="comment-image-list mt-8" v-if="getImages(r.image_url).length > 0">
											<image 
												v-for="(img, idx) in getImages(r.image_url)" 
												:key="idx" 
												:src="getFullUrl(img)" 
												class="reply-image" 
												mode="widthFix" 
												@click.stop="previewCommentImage(r.image_url, idx)" 
											/>
										</view>
									</view>
									<view class="reply-actions">
										<text class="reply-date">{{ formatRelativeTime(r.created_at) }}</text>
										<text class="reply-btn" @click.stop="onReply(r)">回复</text>
										<text class="reply-btn delete-btn" v-if="authStore.isAdmin || r.user_id == authStore.user?.sub" @click.stop="deleteComment(r.id)">删除</text>
									</view>
								</view>
							</view>
						</view>
					</view>
				</template>

				<view class="load-more-status" v-if="comments.length > 0">
					<text v-if="loadingComments">正在加载...</text>
					<text v-else-if="hasMoreComments" @click="fetchComments(currentCommentPage + 1)">加载更多评论</text>
					<text v-else>—— 已加载全部评论 ——</text>
				</view>
				<view class="empty-comments" v-if="comments.length === 0 && !loadingComments">
					<text>暂无审核意见</text>
				</view>
			</view>
		</view>

		<!-- 排序弹窗 -->
		<view class="popup-mask" v-if="showSortPopup" @click="showSortPopup = false">
			<view class="popup-content" @click.stop>
				<view class="popup-header">
					<text class="popup-title">选择排序方式</text>
					<uni-icons type="closeempty" size="20" color="#888" @click="showSortPopup = false" />
				</view>
				<view class="sort-options">
					<view class="sort-option" :class="{ active: sortType === 'newest' }" @click="selectSortType('newest')">最新发布</view>
					<view class="sort-option" :class="{ active: sortType === 'oldest' }" @click="selectSortType('oldest')">最早发布</view>
					<view class="sort-option" :class="{ active: sortType === 'timestamp' }" @click="selectSortType('timestamp')">时间戳</view>
					<view class="sort-option" :class="{ active: sortType === 'reviewer' }" @click="selectSortType('reviewer')">审评者</view>
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
const markers = ref([]) // For timeline dots
const activeCommentId = ref(null) // For highlighting comments in list and dots
const currentCommentPage = ref(1)
const hasMoreComments = ref(true)
const loadingComments = ref(false)
const commentLimit = 10 // Number of comments to load per page
const selectedImages = ref([]) // [{path, progress, uploading, url}]
const replyTo = ref(null)
const seekMessage = ref('')
const isFullscreen = ref(false)
const isRotated = ref(false)
const showSortPopup = ref(false)
const sortType = ref('timestamp')
const hasSorted = ref(false)
let seekTimer = null
let videoContext = null

const sortLabel = computed(() => {
	switch(sortType.value) {
		case 'newest': return '最新发布';
		case 'oldest': return '最早发布';
		case 'timestamp': return '时间戳';
		case 'reviewer': return '审评者';
		default: return '时间戳';
	}
})

const sortedComments = computed(() => {
	// Group comments by parent_id
	const commentMap = new Map(comments.value.map(c => [c.id, { ...c, replies: [] }]));

	const rootComments = [];
	commentMap.forEach(comment => {
		if (comment.parent_id && commentMap.has(comment.parent_id)) {
			commentMap.get(comment.parent_id).replies.push(comment);
		} else {
			rootComments.push(comment);
		}
	});

	// Sort replies within each root comment by creation time
	rootComments.forEach(root => {
		root.replies.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
	});

	// Sort root comments based on the selected sortType
	const list = [...rootComments];
	switch(sortType.value) {
		case 'timestamp':
			return list.sort((a, b) => a.timestamp - b.timestamp);
		case 'newest':
			return list.sort((a, b) => b.id - a.id);
		case 'oldest':
			return list.sort((a, b) => a.id - b.id);
		case 'reviewer':
			return list.sort((a, b) => a.username.localeCompare(b.username));
		default:
			return list;
	}
});

function selectSortType(type) {
	sortType.value = type
	showSortPopup.value = false
	hasSorted.value = true
}

function getFullUrl(path) {
	if (!path) return ''
	if (path.startsWith('http')) return path
	return authStore.API_BASE + path
}

function getImages(imageUrl) {
	if (!imageUrl) return []
	try {
		const parsed = JSON.parse(imageUrl)
		return Array.isArray(parsed) ? parsed : [imageUrl]
	} catch (e) {
		return [imageUrl]
	}
}

function getReplyToUsername(parentId) {
	if (!parentId) return ''
	const p = comments.value.find(c => c.id === parentId)
	return p ? p.username : '未知'
}

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

const speeds = [0.5, 0.75, 1, 1.25, 1.5, 2]

const progressPercent = computed(() => {
	if (duration.value <= 0) return 0
	return (currentTime.value / duration.value) * 100
})

const commentDots = computed(() => {
	if (duration.value <= 0) return []
	
	// Use markers for timeline dots
	const sorted = [...markers.value].sort((a, b) => a.timestamp - b.timestamp)
	const result = []
	const threshold = 1.2 // 1.2% 的阈值，认为在同一个区域
	
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
		// 集群内的项：最新发布的（ID更大）排在后面（在 Vue 中后渲染的在上层）
		const items = cluster.items.sort((a, b) => a.id - b.id)
		items.forEach((c, idx) => {
			const percent = (c.timestamp / duration.value) * 100
			// 只有 1 个时不偏移，多个时进行交替偏移
			let offset = 0
			if (items.length > 1) {
				const mid = (items.length - 1) / 2
				offset = (idx - mid) * 14 // 每个偏移 14rpx
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

onLoad((options) => {
	videoId.value = parseInt(options.id)
	fetchVideoDetail()
	fetchMarkers() // Fetch markers for timeline dots
	fetchComments(1) // Fetch first page of comments
	videoContext = uni.createVideoContext('reviewVideo')
	
	// 监听浏览器原生全屏变化 (用于支持 ESC 退出)
	// 因为是在 onLoad 中，最好在 nextTick 或保证 document 存在
	if (typeof document !== 'undefined') {
		document.addEventListener('fullscreenchange', onDocFullscreenChange)
	}
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
				const url = video.type === 'local'
					? `${authStore.API_BASE}${video.url}`
					: video.url
				
				// 添加 #t=0.1 强制浏览器（特别是微信）渲染首帧或指定时间的画面
				videoUrl.value = url.includes('#t=') ? url : url + '#t=0.1'
					
				// 增加播放量
				incrementViewCount(video.id)
			}
		}
	} catch (e) {
		console.error('Failed to fetch video:', e)
	}
}

async function fetchMarkers() {
	try {
		const res = await uni.request({
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

async function fetchComments(page = 1) {
	if (loadingComments.value) return
	loadingComments.value = true
	
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments/${videoId.value}`,
			method: 'GET',
			data: {
				page: page,
				limit: commentLimit
			}
		})
		if (res.statusCode === 200) {
			if (page === 1) {
				comments.value = res.data.comments || []
			} else {
				comments.value = [...comments.value, ...(res.data.comments || [])]
			}
			currentCommentPage.value = page
			hasMoreComments.value = comments.value.length < (res.data.total || 0)
		}
	} catch (e) {
		console.error('Failed to fetch comments:', e)
	} finally {
		loadingComments.value = false
	}
}

function incrementViewCount(id) {
	uni.request({
		url: `${authStore.API_BASE}/api/videos/${id}/view`,
		method: 'POST'
	})
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
	if (isLoading.value && !seekMessage.value) {
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

function seekTo(commentId, time) {
	if (commentId !== undefined && commentId !== null) {
		activeCommentId.value = commentId
	}
	if (videoContext && time >= 0) {
		if (seekTimer) clearTimeout(seekTimer)
		seekMessage.value = `正在跳转至 ${formatTime(time)}`
		
		// 立即更新当前时间，避免其他逻辑用到旧时间
		currentTime.value = time
		
		// 如果当前正在发表审核意见，同步更新标记的时间戳
		if (commentTimestamp.value >= 0) {
			commentTimestamp.value = time
		}
		
		videoContext.seek(time)
		videoContext.pause()
		
		// 假定网络慢，展示至少 1 秒的跳转提示，或者直到有了新的 timeUpdate
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
		// 进入全屏 (Web级，可保留自定义控件)
		try {
			if (document.documentElement.requestFullscreen) {
				await document.documentElement.requestFullscreen()
			}
			isFullscreen.value = true
		} catch (e) {
			// 如果 API 失败，也强制渲染为撑满窗口
			isFullscreen.value = true
		}
	} else {
		// 退出全屏
		try {
			if (document.exitFullscreen && document.fullscreenElement) {
				await document.exitFullscreen()
			}
		} catch (e) {}
		isFullscreen.value = false
		isRotated.value = false
	}
}

function onDocFullscreenChange() {
	if (typeof document !== 'undefined') {
		const isNativeFullscreen = !!document.fullscreenElement
		if (!isNativeFullscreen && isFullscreen.value) {
			// 用户按下了 ESC 键退出了全屏
			isFullscreen.value = false
			isRotated.value = false
		}
	}
}

function toggleRotate() {
	isRotated.value = !isRotated.value
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
			activeCommentId.value = null // 手动操作进度条时清除选中状态
			if (commentTimestamp.value >= 0) {
				commentTimestamp.value = targetTime
			}
			
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

function chooseImage() {
	if (selectedImages.value.length >= 3) {
		return uni.showToast({ title: '最多上传 3 张图片', icon: 'none' })
	}
	uni.chooseImage({
		count: 3 - selectedImages.value.length,
		sizeType: ['compressed'],
		success: (res) => {
			const newImages = res.tempFilePaths.map(p => ({
				path: p,
				progress: 0,
				uploading: false,
				url: ''
			}))
			selectedImages.value.push(...newImages)
		}
	})
}

function removeImage(idx) {
	selectedImages.value.splice(idx, 1)
}

function previewSelectedImage(idx) {
	const urls = selectedImages.value.map(img => img.path)
	uni.previewImage({ urls, current: urls[idx] })
}

function previewCommentImage(imageUrl, idx) {
	const imgs = getImages(imageUrl)
	const urls = imgs.map(img => getFullUrl(img))
	uni.previewImage({ urls, current: urls[idx || 0] })
}

function onCommentClick(comment) {
	seekTo(comment.id, comment.timestamp)
}

function onReply(comment) {
	replyTo.value = { id: comment.id, username: comment.username }
	// 立即跳转并锁定时间戳到父评论的时间
	seekTo(comment.id, comment.timestamp)
	commentTimestamp.value = comment.timestamp
}

function cancelReply() {
	replyTo.value = null
}

async function uploadImage(imgObj) {
	return new Promise((resolve, reject) => {
		imgObj.uploading = true
		const uploadTask = uni.uploadFile({
			url: `${authStore.API_BASE}/api/comments/upload-image`,
			filePath: imgObj.path,
			name: 'image',
			header: authStore.getAuthHeader(),
			success: (res) => {
				const data = typeof res.data === 'string' ? JSON.parse(res.data) : res.data
				if (res.statusCode === 201 && data.url) {
					imgObj.url = data.url
					imgObj.uploading = false
					resolve(data.url)
				} else {
					imgObj.uploading = false
					reject(new Error(data.error || '图片上传失败'))
				}
			},
			fail: (err) => {
				imgObj.uploading = false
				reject(new Error('图片上传失败'))
			}
		})

		uploadTask.onProgressUpdate((res) => {
			imgObj.progress = res.progress
		})
	})
}

async function submitComment() {
	const text = commentText.value.trim()
	if (!text && selectedImages.value.length === 0) {
		return uni.showToast({ title: '请输入评论内容或选择图片', icon: 'none' })
	}

	submitting.value = true
	try {
		// Upload all selected images
		const uploadPromises = selectedImages.value.map(img => {
			if (img.url) return Promise.resolve(img.url) // Already uploaded
			return uploadImage(img)
		})
		
		const imageUrls = await Promise.all(uploadPromises)
		const imageUrlData = imageUrls.length > 0 ? JSON.stringify(imageUrls) : ''

		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments`,
			method: 'POST',
			header: {
				'Content-Type': 'application/json',
				...authStore.getAuthHeader()
			},
			data: {
				video_id: videoId.value,
				content: text,
				timestamp: commentTimestamp.value >= 0 ? commentTimestamp.value : currentTime.value,
				image_url: imageUrlData || undefined,
				parent_id: replyTo.value ? replyTo.value.id : undefined
			}
		})

		if (res.statusCode === 201) {
			uni.showToast({ title: '评论成功', icon: 'success' })
			commentText.value = ''
			commentTimestamp.value = -1
			selectedImages.value = []
			replyTo.value = null
			fetchComments(1) // Refresh comments from page 1
			fetchMarkers() // Refresh markers
		} else {
			throw new Error(res.data?.error || '评论失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message || '评论失败', icon: 'none' })
	} finally {
		submitting.value = false
	}
}

async function deleteComment(commentId) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除这条评论吗？',
		success: async (res) => {
			if (res.confirm) {
				try {
					const response = await uni.request({
						url: `${authStore.API_BASE}/api/comments/${commentId}`,
						method: 'DELETE',
						header: authStore.getAuthHeader()
					})
					if (response.statusCode === 200) {
						uni.showToast({ title: '删除成功', icon: 'success' })
						fetchComments(1) // Refresh comments
						fetchMarkers() // Refresh markers
					} else {
						throw new Error(response.data?.error || '删除失败')
					}
				} catch (e) {
					uni.showToast({ title: e.message || '删除失败', icon: 'none' })
				}
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

function formatRelativeTime(dateStr) {
	if (!dateStr) return '';
	const now = new Date();
	const past = new Date(dateStr.replace(/-/g, '/'));
	const diff = (now - past) / 1000;
	if (diff < 60) return '刚刚';
	if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
	if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
	if (diff < 2592000) return Math.floor(diff / 86400) + '天前';
	return dateStr.split(' ')[0];
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
	display: flex;
	flex-direction: column;
}

.player-section.is-fullscreen {
	position: fixed !important;
	top: 0 !important;
	left: 0 !important;
	width: 100vw !important;
	height: 100vh !important;
	z-index: 9999 !important;
	margin: 0 !important;
	box-shadow: none;
}

.is-fullscreen .controls {
	position: absolute;
	bottom: 0;
	left: 0;
	right: 0;
	background: rgba(10, 10, 26, 0.65) !important;
	padding: 10px 15px calc(10px + env(safe-area-inset-bottom)) !important;
	z-index: 100;
}

.is-fullscreen .controls-bottom {
	height: 50px !important;
}

.is-fullscreen .comment-dot {
	width: 20px !important;
	height: 20px !important;
}

.is-fullscreen .comment-dot.dot-active {
	width: 28px !important;
	height: 28px !important;
}

.is-fullscreen .dot-avatar-letter {
	font-size: 11px !important;
}

.is-fullscreen .speed-text,
.is-fullscreen .time-current,
.is-fullscreen .time-total {
	font-size: 14px !important;
}

.player-section.is-rotated {
	width: 100vh !important;
	height: 100vw !important;
	transform: rotate(90deg);
	transform-origin: top left;
	left: 100vw !important;
}

.video-wrapper {
	position: relative;
	cursor: pointer;
	flex: 1;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #000;
}

.video-player {
	width: 100%;
	height: 420rpx;
	display: block;
}

.is-fullscreen .video-player {
	height: 100% !important;
}

.loading-overlay {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.5);
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	pointer-events: none;
	backdrop-filter: blur(2px);
}

.loading-spinner {
	width: 80rpx;
	height: 80rpx;
	border: 4rpx solid rgba(255, 255, 255, 0.2);
	border-radius: 50%;
	border-top-color: #d6d6d6;
	animation: spin 1s ease-in-out infinite;
	box-shadow: 0 0 15rpx rgba(214, 214, 214, 0.5);
}

.seek-message-text {
	margin-top: 24rpx;
	font-size: 28rpx;
	color: #fff;
	font-weight: 500;
	text-shadow: 0 2rpx 4rpx rgba(0, 0, 0, 0.8);
	letter-spacing: 2rpx;
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
	background: rgba(0, 0, 0, 0.2);
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 4rpx 20rpx rgba(0, 0, 0, 0.3);
	pointer-events: none;
}

.center-play-icon {
	font-size: 44rpx;
	color: #fff;
	margin-left: 6rpx;
}

.controls {
	padding: 12rpx 20rpx 40rpx;
	background: #1a1a2e;
}

.controls-top {
	margin-bottom: 12rpx;
}

.progress-bar {
	padding: 16rpx 0;
	cursor: pointer;
}

.progress-track {
	height: 2px;
	background: rgba(255, 255, 255, 0.2);
	border-radius: 2px;
	position: relative;
}

.progress-fill {
	height: 100%;
	background: #d6d6d6;
	border-radius: 3rpx;
	transition: width 0.1s linear;
}

.progress-thumb {
	position: absolute;
	top: 50%;
	width: 20rpx;
	height: 20rpx;
	border-radius: 50%;
	background: #ffffff;
	transform: translate(-50%, -50%);
}

.comment-dot {
	position: absolute;
	top: 50%;
	width: 32rpx;
	height: 32rpx;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	box-shadow: 0 4rpx 10rpx rgba(0, 0, 0, 0.4);
	cursor: pointer;
	overflow: hidden;
	transition: all 0.2s ease;
}

.comment-dot.dot-active {
	border: 4rpx solid #f39c12;
	width: 40rpx;
	height: 40rpx;
	z-index: 1000 !important;
	box-shadow: 0 0 20rpx rgba(243, 156, 18, 0.6);
}

.dot-avatar-letter {
	color: #fff;
	font-size: 18rpx;
	font-weight: bold;
}

.controls-bottom {
	display: grid;
	grid-template-columns: 1fr auto 1fr;
	align-items: center;
	height: 80rpx;
	padding: 0 10px;
}

.controls-left {
	display: flex;
	align-items: center;
	z-index: 10;
}

.time-current {
	font-size: 13px;
	color: #ffffff;
	font-variant-numeric: tabular-nums;
}

.time-separator {
	font-size: 12px;
	color: #888899;
	margin: 0 4px;
}

.time-total {
	font-size: 13px;
	color: #888899;
	font-variant-numeric: tabular-nums;
}

.controls-center {
	display: flex;
	align-items: center;
	gap: 12px;
	justify-content: center;
}

.ctrl-icon-btn {
	padding: 4px;
	display: flex;
	align-items: center;
	justify-content: center;
}

.ctrl-icon-btn:active {
	opacity: 0.7;
}

.icon-svg {
	width: 22px;
	height: 22px;
	background-size: cover;
	background-position: center;
	background-repeat: no-repeat;
}

.icon-expand {
	background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="%23d0d0e0" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>');
}

.icon-shrink {
	background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="%23d0d0e0" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14h6v6M20 10h-6V4M14 10l7-7M10 14l-7 7"/></svg>');
}

.icon-rotate {
	background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" stroke="%23d0d0e0" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/></svg>');
}

.skip-btn-circle {
	width: 36px;
	height: 36px;
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
	border-top: 7px solid transparent;
	border-bottom: 7px solid transparent;
	border-left: 9px solid #ffffff;
}

.triangle-left {
	width: 0;
	height: 0;
	border-top: 7px solid transparent;
	border-bottom: 7px solid transparent;
	border-right: 9px solid #ffffff;
}

.skip-icon-ff, .skip-icon-rw {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 1px;
}
.skip-icon-ff {
	margin-left: 2px;
}
.skip-icon-rw {
	margin-right: 2px;
}

.play-btn-circle {
	width: 48px;
	height: 48px;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.12);
	display: flex;
	align-items: center;
	justify-content: center;
	transition: background 0.2s;
}

.is-fullscreen .play-btn-circle,
.is-fullscreen .skip-btn-circle {
	width: 36px !important;
	height: 36px !important;
}

.play-btn-circle:active {
	background: rgba(255, 255, 255, 0.2);
}

.pause-icon {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 6px;
	width: 100%;
	height: 100%;
}

.pause-bar {
	width: 4px;
	height: 18px;
	background-color: #ffffff;
	border-radius: 2px;
}

.play-icon-css {
	width: 0;
	height: 0;
	border-top: 10px solid transparent;
	border-bottom: 10px solid transparent;
	border-left: 14px solid #ffffff;
	margin-left: 4px; /* Optical center for a right-pointing triangle */
}

.controls-right {
	display: flex;
	justify-content: flex-end;
	align-items: center;
	gap: 10px;
	z-index: 10;
}

.speed-text {
	font-size: 13px;
	color: #e2e2ea;
	cursor: pointer;
	white-space: nowrap;
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

/* 评论输入 */
.comment-input-section {
	position: fixed;
	bottom: 0;
	left: 0;
	width: 100%;
	box-sizing: border-box;
	background: #1a1a2e;
	padding: 24rpx 24rpx calc(24rpx + env(safe-area-inset-bottom));
	border-top: 1px solid rgba(255, 255, 255, 0.08);
	z-index: 100;
}

.input-row {
	display: flex;
	gap: 16rpx;
	align-items: center; /* 居中对齐，保证看起来是一行 */
}

.comment-text-textarea {
	flex: 1;
	line-height: 1.6;
	min-height: 1.6em;
	padding: 10rpx 20rpx;
	box-sizing: border-box;
	background: rgba(0, 0, 0, 0.2);
	border-radius: 12rpx;
}

.send-btn {
	width: 140rpx;
	height: 64rpx;
	padding: 0;
	font-size: 26rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.img-pick-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 64rpx;
	height: 64rpx;
	flex-shrink: 0;
	cursor: pointer;
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
	background: #1a1a2e;
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

.comment-list-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 24rpx;
}

.section-title {
	font-size: 30rpx;
	font-weight: 700;
	color: #c0c0d0;
}

.sort-btn {
	display: flex;
	align-items: center;
	padding: 8rpx 0;
	cursor: pointer;
}

.sort-btn:active {
	opacity: 0.7;
}

.sort-icon-wrapper {
	margin-left: 6rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.sort-text {
	font-size: 24rpx;
	color: #e0e0e0;
}

.comment-item {
	padding: 24rpx 12rpx; /* Horizontal padding for better background box */
	border-top: 1px solid rgba(255, 255, 255, 0.04);
	margin: 0 -12rpx; /* Align content by pulling it back with negative margin */
	transition: background 0.2s ease;
}

.comment-item:first-child {
	border-top: none;
}

.comment-item-active {
	background: rgba(255, 255, 255, 0.05);
	border-radius: 12rpx;
}

.comment-main {
	display: flex;
	gap: 20rpx;
}

.comment-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8rpx;
}

.comment-body {
	flex: 1;
	min-width: 0;
}

.comment-avatar {
	width: 60rpx;
	height: 60rpx;
	border-radius: 50%;
	background: #3d3d52;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.avatar-letter {
	font-size: 26rpx;
	color: #fff;
	font-weight: 600;
}

.avatar-letter-small {
	font-size: 20rpx;
	color: #fff;
	font-weight: 600;
}



.comment-username {
	font-size: 28rpx;
	color: #c0c0d0;
	font-weight: 500;
	line-height: 1.1;
}

.comment-date {
	font-size: 22rpx;
	color: #777788;
}

.comment-time {
	font-size: 26rpx;
	color: #f39c12;
	background: rgba(243, 156, 18, 0.1);
	padding: 4rpx 14rpx;
	border-radius: 6rpx;
	font-variant-numeric: tabular-nums;
	transition: all 0.2s ease;
	height: fit-content;
}

.time-active {
	background: #f39c12 !important;
	color: #fff !important;
}

.comment-content {
	font-size: 30rpx;
	color: #eeeeee;
	line-height: 1.6;
	word-break: break-all;
	display: block;
}

.comment-content-box {
	display: flex;
	flex-direction: column;
}

.comment-image-wrapper {
	margin-top: 12rpx;
	display: block;
}

.comment-actions {
	margin-top: 8rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.reply-btn {
	font-size: 24rpx;
	color: #888899;
	cursor: pointer;
}

.reply-btn:active {
	opacity: 0.7;
}

/* 图片选择 & 预览 */
.img-pick-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	flex-shrink: 0;
	cursor: pointer;
}

.img-pick-btn:active {
	opacity: 0.7;
}

.reply-hint {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12rpx 16rpx;
	margin-bottom: 12rpx;
	background: rgba(255, 255, 255, 0.05);
	border-radius: 8rpx;
}

.reply-hint-text {
	font-size: 24rpx;
	color: #999;
}

.img-preview-row {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
	margin-top: 16rpx;
}

.img-preview-item {
	position: relative;
	width: 100rpx;
	height: 100rpx;
}

.img-thumb {
	width: 100%;
	height: 100%;
	border-radius: 8rpx;
	object-fit: cover;
	background: #252538;
}

.img-upload-progress {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.6);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 8rpx;
}

.progress-text {
	font-size: 20rpx;
	color: #fff;
}

.img-remove {
	position: absolute;
	top: -12rpx;
	right: -12rpx;
	width: 34rpx;
	height: 34rpx;
	border-radius: 50%;
	background: #000;
	opacity: 0.9;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.img-remove:active {
	background: #e74c3c; /* 只有点击时变红 */
}

/* 评论中的图片列表 */
.comment-image-list {
	display: flex;
	flex-wrap: wrap;
	gap: 12rpx;
	margin-top: 16rpx;
}

.comment-image {
	width: 180rpx;
	height: 180rpx;
	object-fit: cover;
	border-radius: 8rpx;
}

.reply-image {
	width: 140rpx;
	height: 140rpx;
	object-fit: cover;
	border-radius: 8rpx;
}

.mt-8 {
	margin-top: 8rpx !important;
}

.reply-item {
	display: flex;
	gap: 16rpx;
	padding: 16rpx 0;
	cursor: pointer;
	box-sizing: border-box;
}

.reply-body {
	flex: 1;
	min-width: 0;
}

.replies-container {
	margin-top: 12rpx;
	padding-left: 80rpx; /* Align replies under the parent's comment body */
	display: flex;
	flex-direction: column;
	gap: 4rpx;
}

.reply-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 4rpx;
}



.unified-avatar {
	width: 40rpx !important;
	height: 40rpx !important;
}

.reply-username {
	font-size: 24rpx;
	color: #c0c0d0;
	font-weight: 500;
	line-height: 1;
	margin: 0;
}

.reply-date {
	font-size: 20rpx;
	color: #666;
}

.reply-content-box {
	display: flex;
	flex-direction: column;
}

.reply-content {
	font-size: 27rpx;
	color: #eeeeee;
	line-height: 1.6;
	word-break: break-all;
	display: block;
	margin: 0;
}

.reply-to-at {
	color: #9999aa;
	font-weight: 600;
	margin-right: 8rpx;
}

.reply-image-wrapper {
	margin-top: 12rpx;
	display: block;
}

.reply-image {
	max-width: 200rpx;
	border-radius: 8rpx;
}

.reply-actions {
	margin-top: 8rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.replies-fold-ctrl {
	padding: 20rpx 0 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8rpx;
	cursor: pointer;
}

.replies-fold-ctrl:active {
	opacity: 0.7;
}

.fold-text {
	font-size: 26rpx;
	color: #999;
	font-weight: 500;
}

.delete-btn {
	font-size: 22rpx;
	color: #e74c3c;
	margin-left: 20rpx;
}

.empty-comments {
	text-align: center;
	padding: 40rpx;
}

.empty-comments text {
	color: #555;
	font-size: 26rpx;
}

.load-more-status {
	text-align: center;
	padding: 60rpx 0;
	font-size: 24rpx;
	color: #444;
}

/* --------------- 排序弹窗 --------------- */
.popup-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0, 0, 0, 0.6);
	z-index: 1000;
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
}

.popup-content {
	background: #1f1f2e;
	border-top-left-radius: 20rpx;
	border-top-right-radius: 20rpx;
	padding: 30rpx 40rpx calc(40rpx + env(safe-area-inset-bottom));
	animation: slideUp 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@keyframes slideUp {
	from { transform: translateY(100%); }
	to { transform: translateY(0); }
}

.popup-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;
}

.popup-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #fff;
}

.sort-options {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20rpx;
}

.sort-option {
	background: rgba(255, 255, 255, 0.06);
	color: #c0c0d0;
	padding: 26rpx 0;
	text-align: center;
	border-radius: 12rpx;
	font-size: 28rpx;
	transition: all 0.2s;
}

.sort-option.active {
	background: #5b52f6;
	color: #fff;
	font-weight: 600;
}
</style>
