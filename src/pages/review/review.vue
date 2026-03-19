<template>
	<view class="page">
		<Header title="审片" showBack />

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
							<view class="ctrl-icon-btn" @click="toggleRotate" v-if="isFullscreen">
								<view class="icon-svg icon-rotate"></view>
							</view>
							<view class="ctrl-icon-btn" @click="toggleFullscreen">
								<view class="icon-svg" :class="isFullscreen ? 'icon-shrink' : 'icon-expand'"></view>
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
				<!-- 回复提示 -->
				<view class="reply-hint" v-if="replyTo">
					<text class="reply-hint-text">回复 @{{ replyTo.username }}</text>
					<uni-icons type="closeempty" size="16" color="#888" @click="cancelReply" />
				</view>
				<view class="input-row">
					<view class="img-pick-btn" @click="chooseImage">
						<uni-icons type="camera-filled" size="24" color="#6c5ce7" />
					</view>
					<input
						class="dark-input comment-text-input"
						v-model="commentText"
						:placeholder="replyTo ? '回复 ' + replyTo.username + '...' : '输入审核意见...'"
						@focus="pauseForComment"
						maxlength="500"
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
						<text class="sort-text">{{ sortLabel }}</text>
						<uni-icons type="bottom" size="14" color="#a0a0b8" style="margin-left: 2px;" />
					</view>
				</view>

				<!-- 父评论 -->
				<template v-for="c in parentComments" :key="c.id">
					<view class="comment-item">
						<view class="comment-main" @click="seekTo(c.id, c.timestamp)">
							<view class="comment-header">
								<view class="comment-user">
									<view class="comment-avatar" :style="{ background: getAvatarColor(c.username) }">
										<text class="avatar-letter">{{ getAvatarLetter(c.username) }}</text>
									</view>
									<view class="user-meta">
										<text class="comment-username">{{ c.username }}</text>
										<text class="comment-date">{{ c.created_at }}</text>
									</view>
								</view>
								<text class="comment-time" :class="{ 'time-active': selectedCommentId === c.id }">
									⏱ {{ formatTime(c.timestamp) }}
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
								<text class="reply-btn" @click.stop="startReply(c)">回复</text>
							</view>
						</view>

						<!-- 子评论（全展平，顺序往下排列） -->
						<view class="replies-container" v-if="getAllThreadReplies(c.id).length > 0">
							<view class="reply-item" v-for="r in getVisibleReplies(c.id)" :key="r.id" @click.stop="seekTo(c.id, c.timestamp)">
								<view class="reply-header">
									<view class="reply-user-info">
										<view class="comment-avatar unified-avatar" :style="{ background: getAvatarColor(r.username) }">
											<text class="avatar-letter-small">{{ getAvatarLetter(r.username) }}</text>
										</view>
										<view class="user-meta">
											<text class="reply-username">{{ r.username }}</text>
											<text class="reply-date">{{ r.created_at }}</text>
										</view>
									</view>
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
									<text class="reply-btn" @click.stop="startReply(r)">回复</text>
								</view>
							</view>
							
							<!-- 展开/收起控制 -->
							<view class="replies-fold-ctrl" v-if="getAllThreadReplies(c.id).length > 3" @click.stop="toggleExpand(c.id)">
								<text class="fold-text" v-if="!isExpanded(c.id)">
									查看更多 {{ getAllThreadReplies(c.id).length - 3 }} 条回复...
								</text>
								<text class="fold-text" v-else>
									收起回复
								</text>
								<uni-icons :type="isExpanded(c.id) ? 'top' : 'bottom'" size="12" color="#a855f7" />
							</view>
						</view>
					</view>
				</template>

				<view class="empty-comments" v-if="comments.length === 0">
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
const selectedCommentId = ref(null)
const seekMessage = ref('')
const isFullscreen = ref(false)
const isRotated = ref(false)
const showSortPopup = ref(false)
const sortType = ref('timestamp')
const selectedImages = ref([]) // [{path, progress, uploading, url}]
const replyTo = ref(null)
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
	const list = [...comments.value]
	switch(sortType.value) {
		case 'timestamp':
			return list.sort((a, b) => a.timestamp - b.timestamp)
		case 'newest':
			return list.sort((a, b) => b.id - a.id)
		case 'oldest':
			return list.sort((a, b) => a.id - b.id)
		case 'reviewer':
			return list.sort((a, b) => a.username.localeCompare(b.username))
		default:
			return list
	}
})

function selectSortType(type) {
	sortType.value = type
	showSortPopup.value = false
}

const parentComments = computed(() => {
	return sortedComments.value.filter(c => !c.parent_id)
})

function getReplies(parentId) {
	return comments.value.filter(c => c.parent_id === parentId).sort((a, b) => a.id - b.id)
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

function getAllThreadReplies(rootId) {
	// 简单查找所有以 rootId 为根的后代
	let result = []
	let processedIds = new Set()
	let queue = [rootId]
	
	while(queue.length > 0) {
		let pid = queue.shift()
		let children = comments.value.filter(c => c.parent_id === pid)
		for (let child of children) {
			if (!processedIds.has(child.id)) {
				result.push(child)
				processedIds.add(child.id)
				queue.push(child.id)
			}
		}
	}
	// 按 ID 排序确保时间顺序（对应回复顺序向下排列）
	return result.sort((a, b) => a.id - b.id)
}

const expandedComments = ref({}) // { rootId: true/false }

function toggleExpand(rootId) {
	expandedComments.value[rootId] = !expandedComments.value[rootId]
}

function isExpanded(rootId) {
	return !!expandedComments.value[rootId]
}

function getVisibleReplies(rootId) {
	const all = getAllThreadReplies(rootId)
	if (isExpanded(rootId) || all.length <= 3) {
		return all
	}
	return all.slice(0, 3)
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
		selectedCommentId.value = commentId
	}
	if (videoContext && time >= 0) {
		if (seekTimer) clearTimeout(seekTimer)
		seekMessage.value = `正在跳转至 ${formatTime(time)}`
		
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

function startReply(comment) {
	replyTo.value = { id: comment.id, username: comment.username }
	pauseForComment()
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
	if (!text && !selectedImage.value) {
		return uni.showToast({ title: '请输入评论内容或选择图片', icon: 'none' })
	}

	submitting.value = true
	try {
		// 上传所有未上传的图片
		const uploadPromises = selectedImages.value.map(img => {
			if (img.url) return Promise.resolve(img.url)
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
	border: 6rpx solid rgba(255, 255, 255, 0.2);
	border-radius: 50%;
	border-top-color: #a855f7;
	animation: spin 1s ease-in-out infinite;
	box-shadow: 0 0 15rpx rgba(168, 85, 247, 0.5);
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
	padding: 8rpx 16rpx;
	background: rgba(255, 255, 255, 0.05);
	border-radius: 8rpx;
	cursor: pointer;
}

.sort-btn:active {
	background: rgba(255, 255, 255, 0.1);
}

.sort-text {
	font-size: 24rpx;
	color: #e0e0e0;
}

.comment-item {
	padding: 24rpx 0;
	border-top: 1px solid #2e2e2e;
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

.user-meta {
	display: flex;
	flex-direction: column;
	gap: 6rpx;
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
	line-height: 1.1;
}

.comment-time {
	font-size: 28rpx;
	color: #f39c12; /* Distinct color for timestamp */
	background: rgba(243, 156, 18, 0.15);
	padding: 6rpx 16rpx;
	border-radius: 8rpx;
	font-variant-numeric: tabular-nums;
	transition: all 0.2s ease;
}

.time-active {
	background: #f39c12 !important;
	color: #fff !important;
}

.comment-content {
	font-size: 32rpx;
	color: #ffffff;
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
	margin-top: 12rpx;
	text-align: right;
}

.reply-btn {
	font-size: 24rpx;
	color: #6c5ce7;
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
	background: rgba(108, 92, 231, 0.12);
	border-radius: 8rpx;
}

.reply-hint-text {
	font-size: 24rpx;
	color: #a0a0e0;
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

/* 嵌入式子评论 */
.replies-container {
	margin-top: 24rpx;
	padding: 24rpx;
	background: #1e1e29;
	border-radius: 12rpx;
}

.reply-item {
	padding: 16rpx 0;
	cursor: pointer;
}

.reply-item + .reply-item {
	border-top: 1px solid rgba(255, 255, 255, 0.05);
	margin-top: 16rpx;
}

.reply-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 16rpx;
}

.reply-user-info {
	display: flex;
	align-items: center;
	gap: 16rpx;
}

.unified-avatar {
	width: 44rpx !important;
	height: 44rpx !important;
}

.reply-username {
	font-size: 24rpx;
	color: #c0c0d0;
	font-weight: 500;
	line-height: 1.1;
}

.reply-date {
	font-size: 20rpx;
	color: #777788;
	line-height: 1.1;
}

.reply-content-box {
	display: flex;
	flex-direction: column;
	padding-left: 52rpx;
}

.reply-content {
	font-size: 28rpx;
	color: #ffffff;
	line-height: 1.6;
	word-break: break-all;
	display: block;
}

.reply-to-at {
	color: #a855f7;
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
	margin-top: 12rpx;
	text-align: right;
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
	color: #a855f7;
	font-weight: 500;
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
