<template>
	<view class="page-container">
		<Header title="我的评论" showBack />

		<view class="section" v-if="loading">
			<view class="loading-state">
				<view class="loading-spinner"></view>
				<text>正在加载评论...</text>
			</view>
		</view>

		<view class="section" v-else>
			<view class="video-info-card" @click="goToVideo(videoId)" v-if="videoInfo">
				<view class="video-icon"><uni-icons type="videocam" size="24" color="#a855f7" /></view>
				<view class="video-details">
					<text class="video-title">{{ videoInfo.title }}</text>
					<text class="video-meta">👤 {{ videoInfo.uploader }} · {{ formatDate(videoInfo.created_at) }}</text>
				</view>
				<uni-icons type="right" size="18" color="#666" />
			</view>

			<view class="section-header">
				<text class="section-title">评论列表 ({{ comments.length }} 条)</text>
			</view>

			<view class="comment-card" v-for="c in comments" :key="c.id">
				<view class="comment-card-top">
					<text class="comment-timestamp">视频位置: {{ formatTime(c.timestamp) }}</text>
				</view>
				<text class="comment-text">{{ c.content }}</text>
				<view class="comment-card-bottom">
					<text class="comment-date">{{ formatDate(c.created_at) }}</text>
					<text class="delete-btn" @click.stop="deleteComment(c.id)">
						<uni-icons type="trash" size="14" color="#e74c3c" style="margin-right:4rpx;" />删除
					</text>
				</view>
			</view>

			<view class="empty-state" v-if="comments.length === 0">
				<uni-icons type="chat" size="40" color="#444" />
				<text class="empty-text">该视频下暂无评论</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'

const authStore = useAuthStore()
const videoId = ref(0)
const comments = ref([])
const loading = ref(false)

const videoInfo = computed(() => {
	if (comments.value.length > 0) {
		const c = comments.value[0]
		return {
			title: c.video_title,
			uploader: c.uploader || '未知',
			created_at: c.video_created_at
		}
	}
	return null
})

onLoad((options) => {
	if (options.videoId) {
		videoId.value = parseInt(options.videoId)
		fetchComments()
	} else {
		uni.showToast({ title: '参数错误', icon: 'none' })
		setTimeout(() => uni.navigateBack(), 1500)
	}
})

async function fetchComments() {
	loading.value = true
	try {
		// 为了简单起见，我们重新请求一次该用户的所有评论，然后在前端过滤出该视频的评论
		// 如果有后端接口支持按用户和视频ID查询更好，但目前使用现有的 API
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments/user/${authStore.user.id}`,
			method: 'GET',
			header: authStore.getAuthHeader()
		})
		if (res.statusCode === 200) {
			const allUserComments = res.data.comments || []
			comments.value = allUserComments.filter(c => c.video_id === videoId.value)
			
			// 如果评论都被删光了，返回个人中心
			if (comments.value.length === 0) {
				setTimeout(() => {
					uni.navigateBack()
				}, 1000)
			}
		}
	} catch (e) {
		console.error('Failed to fetch comments:', e)
		uni.showToast({ title: '加载失败', icon: 'none' })
	} finally {
		loading.value = false
	}
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
					// 刷新当前列表
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

function goToVideo(id) {
	uni.navigateTo({ url: `/pages/review/review?id=${id}` })
}

function formatTime(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}

function formatDate(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getFullYear()}-${(d.getMonth() + 1).toString().padStart(2, '0')}-${d.getDate().toString().padStart(2, '0')} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`
}
</script>

<style scoped>

.section {
	margin: 20rpx;
}

.video-info-card {
	padding: 20rpx 0;
	margin-bottom: 20rpx;
	display: flex;
	align-items: center;
	border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.video-info-card:active {
	opacity: 0.8;
}

.video-icon {
	margin-right: 20rpx;
	display: flex;
	align-items: center;
}

.video-details {
	flex: 1;
	min-width: 0;
}

.video-title {
	font-size: 30rpx;
	font-weight: 600;
	color: #fff;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	display: block;
	margin-bottom: 4rpx;
}

.video-meta {
	font-size: 22rpx;
	color: #666;
	display: block;
}

.section-header {
	margin-bottom: 20rpx;
}

.section-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #e0e0e0;
}

.comment-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	padding: 24rpx;
	margin-bottom: 16rpx;
}

.comment-card-top {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 12rpx;
}

.comment-timestamp {
	font-size: 24rpx;
	color: #f39c12;
	flex-shrink: 0;
}

.comment-text {
	font-size: 28rpx;
	color: #c0c0d0;
	display: block;
	line-height: 1.6;
	margin-bottom: 12rpx;
}

.comment-card-bottom {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.comment-date {
	font-size: 22rpx;
	color: #555;
}

.delete-btn {
	font-size: 22rpx;
	color: #e74c3c;
	display: flex;
	align-items: center;
	padding: 10rpx 0;
}

.delete-btn:active {
	opacity: 0.7;
}

.empty-state, .loading-state {
	text-align: center;
	padding: 120rpx 0;
}

.empty-text, .loading-state text {
	font-size: 28rpx;
	color: #555;
	display: block;
	margin-top: 16rpx;
}

.loading-spinner {
	width: 60rpx;
	height: 60rpx;
	border: 4rpx solid rgba(168, 85, 247, 0.2);
	border-radius: 50%;
	border-top-color: #a855f7;
	animation: spin 1s ease-in-out infinite;
	margin: 0 auto;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}
</style>
