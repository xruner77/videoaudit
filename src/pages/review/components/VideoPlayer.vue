<template>
	<view class="player-section" :class="{ 'is-fullscreen': isFullscreen, 'is-rotated': isRotated }">
		<view class="video-wrapper" @click="emit('togglePlay')">
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
				@play="emit('play')"
				@pause="emit('pause')"
				@ended="emit('ended')"
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
							:class="{ 'dot-active': activeCommentId === dot.id }"
							v-for="dot in commentDots"
							:key="dot.id"
							:style="{ 
								left: dot.percent + '%', 
								transform: `translate(-50%, -50%) translateX(${dot.offset}rpx)`,
								zIndex: dot.zIndex,
								background: getAvatarColor(dot.username)
							}"
							@click.stop="emit('dotClick', { id: dot.id, time: dot.time })"
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
					<view class="skip-btn-circle" @click="emit('skip', -5)">
						<view class="skip-icon-rw">
							<view class="triangle-left"></view>
							<view class="triangle-left"></view>
						</view>
					</view>
					<view class="play-btn-circle" @click="emit('togglePlay')">
						<view class="pause-icon" v-if="isPlaying">
							<view class="pause-bar"></view>
							<view class="pause-bar"></view>
						</view>
						<view class="play-icon-css" v-else></view>
					</view>
					<view class="skip-btn-circle" @click="emit('skip', 5)">
						<view class="skip-icon-ff">
							<view class="triangle-right"></view>
							<view class="triangle-right"></view>
						</view>
					</view>
				</view>
				<view class="controls-right">
					<text class="speed-text" @click="emit('cycleSpeed')">{{ playbackRate }}倍</text>
					<view class="ctrl-icon-btn" @click="emit('toggleFullscreen')">
						<view class="icon-svg" :class="isFullscreen ? 'icon-shrink' : 'icon-expand'"></view>
					</view>
				</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, getCurrentInstance } from 'vue'
import { formatTime, getUserColor as getAvatarColor } from '../../../composables/useUtils'

const props = defineProps({
	videoUrl: String,
	isPlaying: Boolean,
	isLoading: Boolean,
	currentTime: Number,
	duration: Number,
	playbackRate: Number,
	isFullscreen: Boolean,
	isRotated: Boolean,
	commentDots: Array,
	activeCommentId: [Number, String],
	seekMessage: String,
	progressPercent: Number
})

const emit = defineEmits([
	'togglePlay', 'skip', 'cycleSpeed', 'toggleFullscreen',
	'dotClick', 'timeupdate', 'play', 'pause', 'ended', 'metaloaded',
	'progressUpdate'
])

const instance = getCurrentInstance()
const isDragging = ref(false)

function getAvatarLetter(username) {
	return username ? username.charAt(0).toUpperCase() : '?'
}

// --- Video events (filter timeupdate during drag) ---
function onTimeUpdate(e) {
	if (isDragging.value) return
	emit('timeupdate', {
		currentTime: e.detail.currentTime,
		duration: e.detail.duration
	})
}

function onMetaLoaded(e) {
	emit('metaloaded', {
		duration: (e.detail && e.detail.duration) || 0
	})
}

// --- 进度条手势控制 ---
function onProgressTouchStart(e) {
	if (!props.duration) return
	isDragging.value = true
	updateProgressByEvent(e)
}

function onProgressTouchMove(e) {
	if (!props.duration || !isDragging.value) return
	updateProgressByEvent(e)
}

function onProgressTouchEnd(e) {
	if (!props.duration) return
	isDragging.value = false
	updateProgressByEvent(e, true)
}

function onProgressClick(e) {
	if (!props.duration) return
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
			const targetTime = ratio * props.duration
			
			emit('progressUpdate', { time: targetTime, shouldSeek })
		}
	}).exec()
}
</script>

<style scoped>
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
	margin-left: 4px;
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
</style>
