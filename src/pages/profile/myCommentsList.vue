<template>
	<view class="page-container">
		<Header title="我的评论" showBack />

		<view class="comment-list">
			<view class="comment-group-card" v-for="group in groupedComments" :key="group.video_id" @click="goToMyComments(group.video_id)">
				<view class="group-info">
					<text class="group-video-title">{{ group.video_title || '未知视频' }}</text>
					<view class="group-stats">
						<text class="group-count">
							<uni-icons type="chat" size="14" color="#a855f7" style="margin-right:6rpx;"/>
							最近评论于 {{ group.comments[0] ? formatDateSimple(group.comments[0].created_at) : '' }}
						</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
				</view>
			</view>

			<view class="load-more-status" v-if="dataList.length > 0">
				<text v-if="loading">正在加载...</text>
				<text v-else-if="hasMore">加载更多评论记录...</text>
				<text v-else>—— 已加载全部评论 ——</text>
			</view>

			<view class="empty-state" v-if="!loading && groupedComments.length === 0">
				<uni-icons type="chat" size="40" color="#444" />
				<text class="empty-text">暂无评论记录</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { computed } from 'vue'
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
		url: `${authStore.API_BASE}/api/comments/user/${authStore.user.id}`,
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

const groupedComments = computed(() => {
	if (!dataList.value || dataList.value.length === 0) return []
	
	const groupMap = new Map()
	dataList.value.forEach(comment => {
		if (!groupMap.has(comment.video_id)) {
			groupMap.set(comment.video_id, {
				video_id: comment.video_id,
				video_title: comment.video_title,
				comments: []
			})
		}
		groupMap.get(comment.video_id).comments.push(comment)
	})
	
	return Array.from(groupMap.values())
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

function formatDateSimple(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getMonth() + 1}/${d.getDate()} ${d.getHours()}:${d.getMinutes().toString().padStart(2, '0')}`
}

function goToMyComments(videoId) {
	uni.navigateTo({ url: `/pages/myComments/myComments?videoId=${videoId}` })
}
</script>

<style scoped>

.comment-list {
	padding: 20rpx;
}

.comment-group-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 16rpx;
	padding: 24rpx;
	margin-bottom: 16rpx;
	transition: all 0.2s ease;
}

.comment-group-card:active {
	background: rgba(255, 255, 255, 0.06);
}

.group-info {
	display: flex;
	flex-direction: column;
}

.group-video-title {
	font-size: 28rpx;
	font-weight: 600;
	color: #fff;
	margin-bottom: 12rpx;
	display: block;
	white-space: nowrap;
	overflow: hidden;
	text-overflow: ellipsis;
}

.group-stats {
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.group-count {
	font-size: 24rpx;
	color: #a0a0b8;
	display: flex;
	align-items: center;
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

.load-more-status {
	width: 100%;
	text-align: center;
	padding: 60rpx 0;
	font-size: 24rpx;
	color: #444;
}
</style>
