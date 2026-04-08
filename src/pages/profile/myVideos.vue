<template>
	<view class="page-container">
		<Header title="我的视频" showBack />

		<DataState
			:initLoading="loading && dataList.length === 0"
			:isEmpty="dataList.length === 0"
			emptyText="暂无上传的视频"
			emptyIcon="videocam"
			:showPagination="dataList.length > 0"
			:loadingMore="loading && dataList.length > 0"
			:hasMore="hasMore"
			skeletonType="video"
			@loadMore="loadNextPage"
		>
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
			</view>
		</DataState>
	</view>
</template>

<script setup>
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import VideoCard from '../../components/VideoCard.vue'
import DataState from '../../components/DataState.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'
import { request } from '../../composables/useRequest'

const authStore = useAuthStore()

const { dataList, loading, hasMore, loadNextPage, reset } = usePagination(async (params) => {
	const res = await request({
		url: `${authStore.API_BASE}/api/videos`,
		method: 'GET',
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
				const resp = await request({ url: `${authStore.API_BASE}/api/videos/${id}`, method: 'DELETE' })
				if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); reset(); loadNextPage() }
				else throw new Error(resp.data?.error || '删除失败')
			} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
		}
	})
}

function goReview(id) { uni.navigateTo({ url: `/pages/review/review?id=${id}` }) }
</script>

<style scoped>
.mv-container { padding: 20rpx 30rpx 20rpx; }
</style>
