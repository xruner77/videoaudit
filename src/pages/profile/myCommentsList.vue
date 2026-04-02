<template>
	<view class="page-container">
		<Header title="我的评论" showBack />

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
import CommentCard from '../../components/CommentCard.vue'
import { useAuthStore } from '../../stores/authStore'
import { usePagination } from '../../composables/usePagination'

const authStore = useAuthStore()

const { dataList, loading, hasMore, loadNextPage, reset } = usePagination(async (params) => {
	const res = await uni.request({
		url: `${authStore.API_BASE}/api/comments/user/${authStore.user?.id}`,
		method: 'GET',
		header: authStore.getAuthHeader(),
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
				const resp = await uni.request({ url: `${authStore.API_BASE}/api/comments/${id}`, method: 'DELETE', header: authStore.getAuthHeader() })
				if (resp.statusCode === 200) { uni.showToast({ title: '已删除', icon: 'success' }); reset(); loadNextPage() }
				else throw new Error(resp.data?.error || '删除失败')
			} catch (e) { uni.showToast({ title: e.message, icon: 'none' }) }
		}
	})
}
</script>

<style scoped>
.mc-container { padding: 20rpx 30rpx 60rpx; }
.load-more-status { width: 100%; text-align: center; padding: 40rpx 0; font-size: 24rpx; color: #444; }
.empty-state { text-align: center; padding: 100rpx 0; }
.empty-text { font-size: 28rpx; color: #555; display: block; margin-top: 16rpx; }
</style>
