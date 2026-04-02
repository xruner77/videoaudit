<template>
	<!-- 评论卡片：头像+用户名+内容+视频标签+时间戳 -->
	<view class="recent-comment-item" @click="clickable ? $emit('click') : null" :style="clickable ? 'cursor:pointer;' : ''">
		<view class="rc-header">
			<view class="rc-avatar" :style="{ background: getUserColor(comment.username) }">
				{{ comment.username ? comment.username.charAt(0).toUpperCase() : '?' }}
			</view>
			<view class="rc-user-info">
				<text class="rc-username">{{ comment.username }}</text>
				<text class="rc-date">{{ formatDateSimple(comment.created_at) }}</text>
			</view>
			<view class="rc-delete-btn" v-if="showDelete" @click.stop="$emit('delete')">
				<uni-icons type="trash" size="16" color="#e74c3c" />
			</view>
		</view>
		<text class="rc-content">{{ comment.content }}</text>
		<view class="rc-footer">
			<view class="rc-video-tag">
				<uni-icons type="videocam" size="12" color="#888" />
				<text class="rc-video-name">{{ comment.video_title || '未知视频' }}</text>
			</view>
			<text class="rc-timestamp">🎬 {{ formatTime(comment.timestamp) }}</text>
		</view>
	</view>
</template>

<script setup>
import { getUserColor, formatDateSimple, formatTime } from '@/composables/useUtils'

defineProps({
	comment: { type: Object, required: true },
	showDelete: { type: Boolean, default: false },
	clickable: { type: Boolean, default: false }
})

defineEmits(['click', 'delete'])
</script>

<style scoped>
.recent-comment-item {
	background: rgba(255,255,255,0.03);
	border: 1px solid rgba(255,255,255,0.06);
	border-radius: 16rpx;
	padding: 22rpx 24rpx;
	margin-bottom: 16rpx;
	transition: all 0.2s ease;
}
.recent-comment-item:active { background: rgba(255,255,255,0.05); }

.rc-header { display: flex; align-items: center; margin-bottom: 14rpx; }
.rc-avatar {
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
	flex-shrink: 0;
}
.rc-user-info { flex: 1; display: flex; align-items: center; gap: 12rpx; }
.rc-username { font-size: 24rpx; color: #e0e0e0; font-weight: 500; }
.rc-date { font-size: 20rpx; color: #555; }

.rc-delete-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 10rpx;
	margin: -10rpx;
	margin-left: auto;
	transition: opacity 0.2s;
}
.rc-delete-btn:active { opacity: 0.6; }

.rc-content {
	font-size: 26rpx;
	color: #dcdce6;
	line-height: 1.5;
	display: block;
	margin-bottom: 12rpx;
}
.rc-footer { display: flex; justify-content: space-between; align-items: center; }
.rc-video-tag { display: flex; align-items: center; gap: 6rpx; }
.rc-video-name {
	font-size: 20rpx;
	color: #666;
	max-width: 300rpx;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.rc-timestamp { font-size: 20rpx; color: #f39c12; flex-shrink: 0; }
</style>
