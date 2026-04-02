<template>
	<view class="page-container">
		<Header title="我的视频" showBack />

		<view class="mv-container">
			<VideoCard
				v-for="v in dataList"
				:key="v.id"
				:video="v"
				:showDelete="true"
				:showUploader="false"
				:showDate="true"
				@click="goReview(v.id)"
				@delete="deleteVideo(v.id)"
			/>

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
import VideoCard from '../../components/VideoCard.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'

const authStore = useAuthStore()

const { dataList, loading, hasMore, loadNextPage, reset } = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/videos`,
		method: 'GET',
		header: authStore.getAuthHeader(),
		data: { ...params, user_id: authStore.user?.id }
	})
	if (res.statusCode === 200) return { data: res.data.videos, total: res.data.total }
	return { data: [], total: 0 }
})

onShow(() => {
	if (authStore.isLoggedIn) { reset(); loadNextPage() }
})

onReachBottom(() => { loadNextPage() })

async function deleteVideo(id) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除此视频及所有评论吗？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await uni.request({ url: `${authStore.API_BASE}/api/videos/${id}`, method: 'DELETE', header: authStore.getAuthHeader() })
				if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); reset(); loadNextPage() }
				else throw new Error(resp.data?.error || '删除失败')
			} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
		}
	})
}

function goReview(id) { uni.navigateTo({ url: `/pages/review/review?id=${id}` }) }
</script>

<style scoped>
.mv-container { padding: 20rpx 30rpx 60rpx; }
.load-more-status { width: 100%; text-align: center; padding: 40rpx 0; font-size: 24rpx; color: #444; }
.empty-state { text-align: center; padding: 100rpx 0; }
.empty-text { font-size: 28rpx; color: #555; display: block; margin-top: 16rpx; }
</style>
