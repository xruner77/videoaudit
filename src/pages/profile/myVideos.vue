<template>
	<view class="page-container">
		<Header title="我的视频" showBack />

		<view class="mv-container">
			<view class="video-manage-card" v-for="v in dataList" :key="v.id" @click="goReview(v.id)">
				<view class="vm-preview">
					<video
						class="vm-preview-video"
						:src="getVideoThumbUrl(v)"
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
					<view class="vm-duration" v-if="v.duration"><text>{{ formatDuration(v.duration) }}</text></view>
				</view>
				<view class="vm-info">
					<text class="vm-title">{{ v.title }}</text>
					<view class="vm-delete-btn" @click.stop="deleteVideo(v.id)">
						<uni-icons type="trash" size="16" color="#e74c3c" />
					</view>
					<view class="vm-meta-row">
						<text class="vm-type-tag">{{ v.type === 'local' ? '本地' : '远程' }}</text>
						<text class="vm-date">{{ formatDateSimple(v.created_at) }}</text>
					</view>
					<view class="vm-stats-row">
						<view class="vm-stat"><uni-icons type="chat" size="12" color="#888" /><text>{{ v.comment_count || 0 }}</text></view>
						<view class="vm-stat"><uni-icons type="eye" size="12" color="#888" /><text>{{ v.views || 0 }}</text></view>
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

function formatDuration(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}

function getVideoThumbUrl(video) {
	if (!video || !video.url) return ''
	let url = video.type === 'local' ? `${authStore.API_BASE}${video.url}` : video.url
	if (!url.includes('#t=')) url += '#t=0.5'
	return url
}

function formatDateSimple(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getMonth() + 1}月${d.getDate()}日`
}
</script>

<style scoped>

.mv-container {
	padding: 20rpx 30rpx 60rpx;
}

/* 视频管理 - 左右布局 */
.video-manage-card { position: relative; display: flex; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 16rpx; margin-bottom: 20rpx; overflow: hidden; transition: all 0.2s ease; }
.video-manage-card:active { background: rgba(255,255,255,0.06); }
.vm-preview { width: 210rpx; min-height: 140rpx; position: relative; flex-shrink: 0; background: #1a1a2e; overflow: hidden; }
.vm-preview-video { width: 100%; height: 100%; display: block; pointer-events: none; }
.vm-preview-overlay { position: absolute; inset: 0; background: linear-gradient(to bottom, rgba(0,0,0,0.2) 0%, transparent 40%, rgba(0,0,0,0.4) 100%); pointer-events: none; }
.vm-duration { position: absolute; bottom: 8rpx; right: 8rpx; }
.vm-duration text { font-size: 20rpx; color: #fff; font-weight: bold; text-shadow: 0 2rpx 4rpx rgba(0,0,0,0.8); }
.vm-info { flex: 1; padding: 16rpx 20rpx; display: flex; flex-direction: column; justify-content: center; min-width: 0; gap: 8rpx; position: relative; }
.vm-title { font-size: 26rpx; font-weight: 600; color: #e0e0e0; display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; line-height: 1.4; padding-right: 48rpx; }
.vm-delete-btn { position: absolute; top: 16rpx; right: 20rpx; padding: 10rpx; margin: -10rpx; display: flex; align-items: center; justify-content: center; z-index: 10; transition: opacity 0.2s; }
.vm-delete-btn:active { opacity: 0.6; }
.vm-meta-row { display: flex; align-items: center; gap: 8rpx; font-size: 22rpx; color: #777; }
.vm-type-tag { background: rgba(108,92,231,0.12); color: #a78bfa; font-size: 18rpx; padding: 2rpx 10rpx; border-radius: 6rpx; }
.vm-date { font-size: 20rpx; color: #555; }
.vm-stats-row { display: flex; align-items: center; gap: 20rpx; }
.vm-stat { display: flex; align-items: center; gap: 4rpx; font-size: 22rpx; color: #888; }

.load-more-status { width: 100%; text-align: center; padding: 40rpx 0; font-size: 24rpx; color: #444; }

.empty-state { text-align: center; padding: 100rpx 0; }
.empty-text { font-size: 28rpx; color: #555; display: block; margin-top: 16rpx; }

</style>
