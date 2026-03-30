<template>
	<view class="page-container">
		<Header title="我的视频" showBack />

		<view class="video-list">
			<view class="video-group-card" v-for="v in dataList" :key="v.id">
				<view class="video-item-content" @click="goReview(v.id)">
					<view class="video-item-icon-small">
						<uni-icons type="videocam" size="20" color="#6c5ce7" />
					</view>
					<view class="video-item-main">
						<text class="video-item-title">{{ v.title }}</text>
						<view class="video-item-tags">
							<text class="tag-type">{{ v.type === 'local' ? '📁 本地' : '🔗 远程' }}</text>
							<text class="tag-date">{{ formatDate(v.created_at) }}</text>
						</view>
					</view>
					<view class="video-item-actions">
						<text class="delete-btn-text" @click.stop="deleteVideo(v.id)">删除</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
				</view>
			</view>

			<view class="load-more-status" v-if="dataList.length > 0">
				<text v-if="loading">正在加载...</text>
				<text v-else-if="hasMore">继续滚动加载</text>
				<text v-else>—— 已加载全部视频 ——</text>
			</view>

			<view class="empty-state" v-if="!loading && dataList.length === 0">
				<uni-icons type="videocam" size="40" color="#444" />
				<text class="empty-text">暂无上传的视频</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'

const authStore = useAuthStore()

const {
	dataList,
	loading,
	hasMore,
	loadNextPage,
	reset
} = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/videos`,
		method: 'GET',
		header: authStore.getAuthHeader(),
		data: {
			...params,
			user_id: authStore.user?.id
		}
	})
	if (res.statusCode === 200) {
		return {
			data: res.data.videos,
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

async function deleteVideo(id) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除此视频及所有评论吗？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await uni.request({
					url: `${authStore.API_BASE}/api/videos/${id}`,
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

function goReview(id) {
	uni.navigateTo({ url: `/pages/review/review?id=${id}` })
}

function formatDate(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getFullYear()}-${(d.getMonth() + 1).toString().padStart(2, '0')}-${d.getDate().toString().padStart(2, '0')} ${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`
}
</script>

<style scoped>

.video-list {
	padding: 20rpx;
}

.video-group-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	padding: 28rpx;
	margin-bottom: 20rpx;
}

.video-item-content {
	display: flex;
	align-items: center;
}

.video-item-icon-small {
	width: 50rpx;
	height: 50rpx;
	background: rgba(108, 92, 231, 0.1);
	border-radius: 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 20rpx;
}

.video-item-main {
	flex: 1;
	min-width: 0;
}

.video-item-title {
	font-size: 30rpx;
	font-weight: 600;
	color: #fff;
	margin-bottom: 12rpx;
	display: block;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.video-item-tags {
	display: flex;
	align-items: center;
	gap: 20rpx;
}

.tag-type {
	font-size: 22rpx;
	color: #a0a0b8;
}

.tag-date {
	font-size: 22rpx;
	color: #666;
}

.video-item-actions {
	display: flex;
	align-items: center;
	gap: 20rpx;
}

.delete-btn-text {
	font-size: 24rpx;
	color: #888;
	padding: 10rpx 0;
}

.delete-btn-text:active {
	color: #e74c3c;
}

.load-more-status {
	width: 100%;
	text-align: center;
	padding: 60rpx 0;
	font-size: 24rpx;
	color: #444;
}

.empty-state {
	text-align: center;
	padding: 80rpx 0;
}

.empty-text {
	font-size: 28rpx;
	color: #555;
	display: block;
	margin-top: 16rpx;
}
</style>
