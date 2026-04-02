<template>
	<!-- 视频管理卡片：左图右文横排布局 -->
	<view class="video-manage-card" @click="$emit('click')">
		<view class="vm-preview">
			<video
				class="vm-preview-video"
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
			<view class="vm-preview-overlay"></view>
			<view class="vm-duration" v-if="video.duration"><text>{{ formatDuration(video.duration) }}</text></view>
		</view>
		<view class="vm-info">
			<text class="vm-title">{{ video.title }}</text>
			<view class="vm-delete-btn" v-if="showDelete" @click.stop="$emit('delete')">
				<uni-icons type="trash" size="16" color="#e74c3c" />
			</view>
			<view class="vm-meta-row">
				<uni-icons v-if="showUploader" type="person" size="12" color="#666" style="margin-right:4rpx;" />
				<text v-if="showUploader">{{ video.uploader || '未知' }}</text>
				<text class="vm-type-tag">{{ video.type === 'local' ? '本地' : '远程' }}</text>
				<text class="vm-date" v-if="showDate">{{ formatDateSimple(video.created_at) }}</text>
			</view>
			<view class="vm-stats-row">
				<view class="vm-stat"><uni-icons type="chat" size="12" color="#888" /><text>{{ video.comment_count || 0 }}</text></view>
				<view class="vm-stat"><uni-icons type="eye" size="12" color="#888" /><text>{{ video.views || 0 }}</text></view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { formatDuration, getVideoThumbUrl, formatDateSimple } from '@/composables/useUtils'

defineProps({
	video: { type: Object, required: true },
	showDelete: { type: Boolean, default: false },
	showUploader: { type: Boolean, default: true },
	showDate: { type: Boolean, default: false }
})

defineEmits(['click', 'delete'])
</script>

<style scoped>
.video-manage-card {
	position: relative;
	display: flex;
	background: rgba(255,255,255,0.03);
	border: 1px solid rgba(255,255,255,0.05);
	border-radius: 16rpx;
	margin-bottom: 20rpx;
	overflow: hidden;
	transition: all 0.2s ease;
}
.video-manage-card:active { background: rgba(255,255,255,0.06); }

.vm-preview {
	width: 210rpx;
	min-height: 140rpx;
	position: relative;
	flex-shrink: 0;
	background: #1a1a2e;
	overflow: hidden;
}
.vm-preview-video { width: 100%; height: 100%; display: block; pointer-events: none; }
.vm-preview-overlay {
	position: absolute;
	inset: 0;
	background: linear-gradient(to bottom, rgba(0,0,0,0.2) 0%, transparent 40%, rgba(0,0,0,0.4) 100%);
	pointer-events: none;
}
.vm-duration { position: absolute; bottom: 8rpx; right: 8rpx; }
.vm-duration text { font-size: 20rpx; color: #fff; font-weight: bold; text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.8); }

.vm-info {
	flex: 1;
	padding: 16rpx 20rpx;
	display: flex;
	flex-direction: column;
	justify-content: center;
	min-width: 0;
	gap: 8rpx;
	position: relative;
}
.vm-title {
	font-size: 26rpx;
	font-weight: 600;
	color: #e0e0e0;
	display: -webkit-box;
	-webkit-line-clamp: 2;
	line-clamp: 2;
	-webkit-box-orient: vertical;
	overflow: hidden;
	text-overflow: ellipsis;
	line-height: 1.4;
	padding-right: 48rpx;
}
.vm-delete-btn {
	position: absolute;
	top: 16rpx;
	right: 20rpx;
	padding: 10rpx;
	margin: -10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 10;
	transition: opacity 0.2s;
}
.vm-delete-btn:active { opacity: 0.6; }
.vm-meta-row { display: flex; align-items: center; gap: 8rpx; font-size: 22rpx; color: #777; }
.vm-type-tag {
	background: rgba(108,92,231,0.12);
	color: #a78bfa;
	font-size: 18rpx;
	padding: 2rpx 10rpx;
	border-radius: 6rpx;
	margin-left: 4rpx;
}
.vm-date { font-size: 20rpx; color: #555; }
.vm-stats-row { display: flex; align-items: center; gap: 20rpx; }
.vm-stat { display: flex; align-items: center; gap: 4rpx; font-size: 22rpx; color: #888; }
</style>
