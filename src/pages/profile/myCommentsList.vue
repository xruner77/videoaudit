<template>
	<view class="page-container">
		<Header title="我的评论" showBack />

		<view class="mc-container">
			<view class="recent-comment-item" v-for="c in dataList" :key="c.id" @click="goToVideo(c.video_id)">
				<view class="rc-header">
					<view class="rc-avatar" :style="{ background: getUserColor(authStore.user?.username) }">
						{{ authStore.user?.username ? authStore.user.username.charAt(0).toUpperCase() : '?' }}
					</view>
					<view class="rc-user-info">
						<text class="rc-username">{{ authStore.user?.username }}</text>
						<text class="rc-date">{{ formatDateSimple(c.created_at) }}</text>
					</view>
					<view class="rc-delete-btn" @click.stop="deleteComment(c.id)">
						<uni-icons type="trash" size="16" color="#e74c3c" />
					</view>
				</view>
				<text class="rc-content">{{ c.content }}</text>
				<view class="rc-footer">
					<view class="rc-video-tag">
						<uni-icons type="videocam" size="12" color="#888" />
						<text class="rc-video-name">{{ c.video_title || '未知视频' }}</text>
					</view>
					<text class="rc-timestamp">🎬 {{ formatTime(c.timestamp) }}</text>
				</view>
			</view>

			<view class="load-more-status" v-if="dataList.length > 0">
				<text v-if="loading">正在加载...</text>
				<text v-else-if="hasMore">加载更多评论记录...</text>
				<text v-else>—— 已加载全部评论 ——</text>
			</view>

			<view class="empty-state" v-if="!loading && dataList.length === 0">
				<uni-icons type="chat" size="40" color="#444" />
				<text class="empty-text">暂无评论记录</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'
import { getUserColor, formatDateSimple, formatTime } from '../../composables/useUtils'

const authStore = useAuthStore()

const {
	dataList,
	loading,
	hasMore,
	loadNextPage,
	reset
} = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/comments/user/${authStore.user?.id}`,
		method: 'GET',
		header: authStore.getAuthHeader(),
		data: params
	})
	if (res.statusCode === 200) {
		return {
			data: res.data.comments,
			total: res.data.total
		}
	}
	return { data: [], total: 0 }
})

onShow(() => {
	if (authStore.isLoggedIn) {
		reset()
		loadNextPage()
	}
})

onReachBottom(() => {
	loadNextPage()
})

function goToVideo(videoId) {
	uni.navigateTo({ url: `/pages/review/review?id=${videoId}` })
}

async function deleteComment(id) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除这条评论吗？',
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
					reset()
					loadNextPage()
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}


</script>

<style scoped>

.mc-container {
	padding: 20rpx 30rpx 60rpx;
}

/* 评论卡片 - 对齐后台样式 */
.recent-comment-item {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	padding: 22rpx 24rpx;
	margin-bottom: 16rpx;
	transition: all 0.2s ease;
}
.recent-comment-item:active {
	background: rgba(255, 255, 255, 0.06);
}

.rc-header {
	display: flex;
	align-items: center;
	margin-bottom: 14rpx;
}
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
.rc-user-info {
	flex: 1;
	display: flex;
	align-items: center;
	gap: 12rpx;
}
.rc-username {
	font-size: 24rpx;
	color: #e0e0e0;
	font-weight: 500;
}
.rc-date {
	font-size: 20rpx;
	color: #555;
}
.rc-delete-btn { padding: 10rpx; margin: -10rpx; display: flex; align-items: center; justify-content: center; transition: opacity 0.2s; }
.rc-delete-btn:active { opacity: 0.6; }
.rc-content {
	font-size: 28rpx;
	color: #dcdce6;
	line-height: 1.5;
	display: block;
	margin-bottom: 12rpx;
}
.rc-footer {
	display: flex;
	justify-content: space-between;
	align-items: center;
}
.rc-video-tag {
	display: flex;
	align-items: center;
	gap: 6rpx;
}
.rc-video-name {
	font-size: 20rpx;
	color: #666;
	max-width: 300rpx;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}
.rc-timestamp {
	font-size: 20rpx;
	color: #f39c12;
	flex-shrink: 0;
}

.load-more-status {
	width: 100%;
	text-align: center;
	padding: 40rpx 0;
	font-size: 24rpx;
	color: #444;
}

.empty-state {
	text-align: center;
	padding: 100rpx 0;
}
.empty-text {
	font-size: 28rpx;
	color: #555;
	display: block;
	margin-top: 16rpx;
}

</style>
