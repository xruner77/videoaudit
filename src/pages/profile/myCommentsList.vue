<template>
	<view class="page-container">
		<Header title="我的评论" showBack />

		<DataState
			:initLoading="loading && dataList.length === 0"
			:isEmpty="dataList.length === 0"
			emptyText="暂无评论记录"
			emptyIcon="chat"
			:showPagination="dataList.length > 0"
			:loadingMore="loading && dataList.length > 0"
			:hasMore="hasMore"
			skeletonType="comment"
			@loadMore="loadNextPage"
		>
			<view class="mc-container">
				<CommentCard
					v-for="c in dataList"
					:key="c.id"
					:comment="c"
					:showDelete="true"
					:clickable="true"
					@click="goToVideo(c.video_id)"
					@delete="deleteComment(c.id)"
				/>
			</view>
		</DataState>
	</view>
</template>

<script setup>
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import CommentCard from '../../components/CommentCard.vue'
import DataState from '../../components/DataState.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'
import { request } from '../../composables/useRequest'

const authStore = useAuthStore()

const { dataList, loading, hasMore, loadNextPage, reset } = usePagination(async (params) => {
	const res = await request({
		url: `${authStore.API_BASE}/api/comments/user/${authStore.user?.id}`,
		method: 'GET',
		data: params
	})
	if (res.statusCode === 200) return { data: res.data.comments, total: res.data.total }
	return { data: [], total: 0 }
})

onShow(() => {
	if (authStore.isLoggedIn) { reset(); loadNextPage() }
})

onReachBottom(() => { loadNextPage() })

function goToVideo(videoId) { uni.navigateTo({ url: `/pages/review/review?id=${videoId}` }) }

async function deleteComment(id) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除这条评论吗？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await request({ url: `${authStore.API_BASE}/api/comments/${id}`, method: 'DELETE' })
				if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); reset(); loadNextPage() }
				else throw new Error(resp.data?.error || '删除失败')
			} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
		}
	})
}
</script>

<style scoped>
.mc-container { padding: 20rpx 30rpx 20rpx; }
</style>
