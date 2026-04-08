<template>
	<DataState
		:initLoading="loading && dashStats.videos === 0"
		:isEmpty="false"
		skeletonType="dashboard"
	>
		<view class="dashboard-section">
			<!-- 统计卡片 -->
			<view class="stats-row">
				<view class="stat-card stat-purple" @click="$emit('switch-tab', 'videos')">
					<view class="stat-icon-wrap">
						<uni-icons type="videocam" size="22" color="#fff" />
					</view>
					<view class="stat-body">
						<text class="stat-number">{{ dashStats.videos }}</text>
						<text class="stat-label">视频总数</text>
					</view>
				</view>
				<view class="stat-card stat-blue" @click="$emit('switch-tab', 'comments')">
					<view class="stat-icon-wrap">
						<uni-icons type="chat" size="22" color="#fff" />
					</view>
					<view class="stat-body">
						<text class="stat-number">{{ dashStats.comments }}</text>
						<text class="stat-label">评论总数</text>
					</view>
				</view>
				<view class="stat-card stat-green" @click="$emit('switch-tab', 'users')">
					<view class="stat-icon-wrap">
						<uni-icons type="person" size="22" color="#fff" />
					</view>
					<view class="stat-body">
						<text class="stat-number">{{ dashStats.users }}</text>
						<text class="stat-label">用户总数</text>
					</view>
				</view>
			</view>

			<!-- 最近视频 -->
			<view class="recent-section">
				<view class="section-header">
					<text class="section-title">最近视频</text>
					<text class="section-more" @click="$emit('switch-tab', 'videos')">查看全部 →</text>
				</view>
				<view v-if="recentVideos.length === 0" class="empty-hint">
					<text>暂无视频</text>
				</view>
				<VideoCard
					v-else
					v-for="v in recentVideos"
					:key="v.id"
					:video="v"
					@click="$emit('go-review', v.id)"
				/>
			</view>

			<!-- 最近评论 -->
			<view class="recent-section">
				<view class="section-header">
					<text class="section-title">最近评论</text>
					<text class="section-more" @click="$emit('switch-tab', 'comments')">查看全部 →</text>
				</view>
				<view v-if="recentComments.length === 0" class="empty-hint">
					<text>暂无评论</text>
				</view>
				<CommentCard
					v-else
					v-for="c in recentComments"
					:key="c.id"
					:comment="c"
					:clickable="true"
					@click="$emit('go-review', c.video_id)"
				/>
			</view>
		</view>
	</DataState>
</template>

<script setup>
import { ref } from 'vue'
import VideoCard from '@/components/VideoCard.vue'
import CommentCard from '@/components/CommentCard.vue'
import DataState from '@/components/DataState.vue'
import { useAuthStore } from '@/stores/authStore'
import { request } from '@/composables/useRequest'

defineEmits(['switch-tab', 'go-review'])

const authStore = useAuthStore()

const dashStats = ref({ videos: 0, comments: 0, users: 0 })
const recentVideos = ref([])
const recentComments = ref([])
const loading = ref(true)

async function fetchDashboard(showLoader = true) {
	if (showLoader && dashStats.value.videos === 0) {
		loading.value = true
	}
	try {
		const [videoRes, commentRes, userRes] = await Promise.allSettled([
			request({
				url: `${authStore.API_BASE}/api/videos`,
				method: 'GET',
				data: { limit: 5 }
			}),
			request({
				url: `${authStore.API_BASE}/api/admin/comments`,
				method: 'GET',
				data: { limit: 5 }
			}),
			request({
				url: `${authStore.API_BASE}/api/admin/users`,
				method: 'GET'
			})
		])

		if (videoRes.status === 'fulfilled' && videoRes.value.statusCode === 200) {
			dashStats.value.videos = videoRes.value.data.total || 0
			recentVideos.value = videoRes.value.data.videos || []
		}
		if (commentRes.status === 'fulfilled' && commentRes.value.statusCode === 200) {
			dashStats.value.comments = commentRes.value.data.total || 0
			recentComments.value = commentRes.value.data.comments || []
		}
		if (userRes.status === 'fulfilled' && userRes.value.statusCode === 200) {
			const users = userRes.value.data.users || []
			dashStats.value.users = users.length
		}
	} catch (e) {
		console.error('Dashboard fetch failed:', e)
	} finally {
		loading.value = false
	}
}

function refresh(showLoader = false) {
	fetchDashboard(showLoader)
}

defineExpose({ refresh })
</script>

<style scoped>
.dashboard-section {
	animation: fadeIn 0.3s ease;
	padding-top: 24rpx;
}

.stats-row {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16rpx;
	margin-bottom: 36rpx;
}

.stat-card {
	border-radius: 20rpx;
	padding: 24rpx 20rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
	gap: 12rpx;
	position: relative;
	overflow: hidden;
}

.stat-card::before {
	content: '';
	position: absolute;
	inset: 0;
	border-radius: 20rpx;
	border: 1px solid rgba(255, 255, 255, 0.08);
	pointer-events: none;
}

.stat-purple {
	background: linear-gradient(135deg, rgba(108, 92, 231, 0.2) 0%, rgba(108, 92, 231, 0.05) 100%);
}

.stat-blue {
	background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(59, 130, 246, 0.05) 100%);
}

.stat-green {
	background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.05) 100%);
}

.stat-icon-wrap {
	width: 52rpx;
	height: 52rpx;
	border-radius: 14rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.stat-purple .stat-icon-wrap {
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
}

.stat-blue .stat-icon-wrap {
	background: linear-gradient(135deg, #3b82f6, #60a5fa);
}

.stat-green .stat-icon-wrap {
	background: linear-gradient(135deg, #10b981, #34d399);
}

.stat-body {
	display: flex;
	flex-direction: column;
	align-items: center;
}

.stat-number {
	font-size: 40rpx;
	font-weight: 700;
	color: #fff;
	line-height: 1.2;
}

.stat-card .stat-label {
	font-size: 20rpx;
	color: #999;
	margin-top: 2rpx;
}

/* 最近活动 */
.recent-section {
	margin-bottom: 30rpx;
}

.section-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
}

.section-title {
	font-size: 28rpx;
	color: #e0e0e0;
	font-weight: 600;
	display: block;
}

.section-more {
	font-size: 22rpx;
	color: #6c5ce7;
}

.empty-hint {
	text-align: center;
	padding: 40rpx 0;
}

.empty-hint text {
	color: #444;
	font-size: 24rpx;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateY(-10rpx); }
	to { opacity: 1; transform: translateY(0); }
}
</style>
