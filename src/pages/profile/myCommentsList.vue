<template>
	<view class="page-container">
		<Header title="我的评论" showBack />

		<view class="comment-list">
			<view class="comment-group-card" v-for="group in groupedComments" :key="group.video_id" @click="goToMyComments(group.video_id)">
				<view class="group-info">
					<text class="group-video-title">{{ group.video_title || '未知视频' }}</text>
					<view class="group-stats">
						<text class="group-count"><uni-icons type="chat" size="14" color="#a855f7" style="margin-right:6rpx;"/>{{ group.comments.length }} 条评论</text>
						<uni-icons type="right" size="16" color="#555" />
					</view>
				</view>
			</view>

			<view class="empty-state" v-if="!loading && groupedComments.length === 0">
				<uni-icons type="chat" size="40" color="#444" />
				<text class="empty-text">暂无评论</text>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'

const authStore = useAuthStore()
const myComments = ref([])
const loading = ref(false)

const groupedComments = computed(() => {
	if (!myComments.value || myComments.value.length === 0) return []
	
	const groupMap = new Map()
	myComments.value.forEach(comment => {
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
		fetchMyComments()
	}
})

async function fetchMyComments() {
	loading.value = true
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/comments/user/${authStore.user.id}`,
			method: 'GET',
			header: authStore.getAuthHeader()
		})
		if (res.statusCode === 200) {
			myComments.value = res.data.comments || []
		}
	} catch (e) {
		console.error('Failed to fetch my comments:', e)
	} finally {
		loading.value = false
	}
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
</style>
