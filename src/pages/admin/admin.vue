<template>
	<view class="page-container">
		<Header title="后台管理" showBack />

		<view class="admin-container" v-if="authStore.isAdmin">
			<!-- 管理 Tab -->
			<view class="tab-bar">
				<text :class="['tab-item', tab === 'videos' && 'tab-active']" @click="tab = 'videos'">
					🎬 视频管理
				</text>
				<text :class="['tab-item', tab === 'comments' && 'tab-active']" @click="tab = 'comments'">
					💬 评论管理
				</text>
			</view>

			<!-- 视频管理 -->
			<view v-if="tab === 'videos'">
				<view 
					class="admin-item-card" 
					v-for="v in videos" 
					:key="v.id"
					:class="{ 'item-expanded': expandedId === v.id }"
				>
					<view class="admin-item-header" @click="toggleExpand(v.id)">
						<view class="admin-item-info">
							<text class="admin-item-title">{{ v.title }}</text>
							<view class="admin-item-meta">
								<view class="meta-tag">
									<uni-icons type="person" size="12" color="#666" style="margin-right:4rpx;"/>
									<text>{{ v.uploader || '未知' }}</text>
								</view>
								<view class="meta-tag">
									<uni-icons :type="v.type === 'local' ? 'folder-add' : 'paperplane'" size="12" color="#666" style="margin-right:4rpx;"/>
									<text>{{ v.type === 'local' ? '本地' : '远程' }}</text>
								</view>
							</view>
						</view>
						<view class="admin-item-arrow">
							<uni-icons :type="expandedId === v.id ? 'top' : 'bottom'" size="14" color="#555" />
						</view>
					</view>

					<!-- 展开详情区域 -->
					<view class="admin-item-details" v-if="expandedId === v.id">
						<view class="details-grid">
							<view class="details-stat">
								<text class="stat-value">{{ formatDuration(v.duration) }}</text>
								<text class="stat-label">时长</text>
							</view>
							<view class="details-stat">
								<text class="stat-value">{{ v.views || 0 }}</text>
								<text class="stat-label">播放量</text>
							</view>
							<view class="details-stat">
								<text class="stat-value">{{ v.comment_count || 0 }}</text>
								<text class="stat-label">评论数</text>
							</view>
						</view>
						<view class="admin-item-actions">
							<text class="action-btn" @click.stop="goReview(v.id)">查看视频</text>
							<view style="flex: 1"></view>
							<text class="action-btn action-danger" @click.stop="deleteVideo(v.id)">删除</text>
						</view>
					</view>
				</view>
				<view class="empty-state" v-if="videos.length === 0">
					<text>暂无视频</text>
				</view>
			</view>

			<!-- 评论管理 -->
			<view v-if="tab === 'comments'">
				<view class="filter-section">
					<text class="form-label">选择视频:</text>
					<picker :range="videoNames" @change="onVideoFilterChange">
						<view class="dark-input picker-display">
							{{ selectedVideoName || '全部视频' }}
						</view>
					</picker>
				</view>

				<view class="admin-item" v-for="c in filteredComments" :key="c.id">
					<view class="admin-item-info">
						<text class="admin-item-title">{{ c.content }}</text>
						<text class="admin-item-meta">
							👤 {{ c.username }} · {{ formatTime(c.timestamp) }}
						</text>
					</view>
					<view class="admin-item-actions">
						<text class="action-btn action-danger" @click="deleteComment(c.id)">删除</text>
					</view>
				</view>
				<view class="empty-state" v-if="filteredComments.length === 0">
					<text>暂无评论</text>
				</view>
			</view>
		</view>

		<!-- 无权限 -->
		<view class="no-access" v-else>
			<text class="no-access-icon">🔒</text>
			<text class="no-access-text">需要管理员权限</text>
			<button class="btn-secondary" @click="goBack">返回</button>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'

const authStore = useAuthStore()

const tab = ref('videos')
const videos = ref([])
const allComments = ref([])
const selectedVideoId = ref(null)
const expandedId = ref(null)

const videoNames = computed(() => {
	return ['全部视频', ...videos.value.map(v => v.title)]
})

const selectedVideoName = computed(() => {
	if (!selectedVideoId.value) return '全部视频'
	const video = videos.value.find(v => v.id === selectedVideoId.value)
	return video?.title || '全部视频'
})

const filteredComments = computed(() => {
	if (!selectedVideoId.value) return allComments.value
	return allComments.value.filter(c => c.video_id == selectedVideoId.value)
})

onShow(() => {
	if (!authStore.isAdmin) return
	fetchVideos()
})

async function fetchVideos() {
	try {
		const res = await uni.request({
			url: `${authStore.API_BASE}/api/videos`,
			method: 'GET'
		})
		if (res.statusCode === 200) {
			videos.value = res.data.videos || []
			// 获取所有视频的评论
			fetchAllComments()
		}
	} catch (e) {
		console.error(e)
	}
}

async function fetchAllComments() {
	const all = []
	for (const v of videos.value) {
		try {
			const res = await uni.request({
				url: `${authStore.API_BASE}/api/comments/${v.id}`,
				method: 'GET'
			})
			if (res.statusCode === 200) {
				all.push(...(res.data.comments || []))
			}
		} catch (e) {
			console.error(e)
		}
	}
	allComments.value = all
}

function onVideoFilterChange(e) {
	const index = e.detail.value
	if (index === 0) {
		selectedVideoId.value = null
	} else {
		selectedVideoId.value = videos.value[index - 1]?.id || null
	}
}

function toggleExpand(id) {
	expandedId.value = expandedId.value === id ? null : id
}

function formatDuration(seconds) {
	if (!seconds) return '00:00'
	const m = Math.floor(seconds / 60)
	const s = seconds % 60
	return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`
}

async function deleteVideo(id) {
	uni.showModal({
		title: '管理员操作',
		content: '确定要删除此视频及关联的所有评论？',
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
					fetchVideos()
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

async function deleteComment(id) {
	uni.showModal({
		title: '管理员操作',
		content: '确定要删除此评论？',
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
					fetchAllComments()
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

function goBack() {
	uni.navigateBack()
}

function formatTime(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}
</script>

<style scoped>

.admin-container {
	padding: 24rpx;
}

.tab-bar {
	display: flex;
	background: rgba(255, 255, 255, 0.04);
	border-radius: 12rpx;
	padding: 6rpx;
	margin-bottom: 30rpx;
}

.tab-item {
	flex: 1;
	text-align: center;
	padding: 18rpx 0;
	font-size: 26rpx;
	color: #666;
	border-radius: 10rpx;
}

.tab-active {
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	color: #fff;
	font-weight: 600;
}

.filter-section {
	margin-bottom: 24rpx;
}

.form-label {
	font-size: 24rpx;
	color: #888;
	margin-bottom: 12rpx;
	display: block;
}

.picker-display {
	font-size: 28rpx;
}

.admin-item-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.05);
	border-radius: 16rpx;
	margin-bottom: 20rpx;
	overflow: hidden;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.item-expanded {
	background: rgba(255, 255, 255, 0.05);
	border-color: rgba(255, 255, 255, 0.1);
	box-shadow: 0 8rpx 24rpx rgba(0, 0, 0, 0.2);
}

.admin-item-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 24rpx 30rpx;
}

.admin-item-info {
	flex: 1;
	overflow: hidden;
}

.admin-item-title {
	font-size: 28rpx;
	color: #e0e0e0;
	font-weight: 600;
	display: block;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
	margin-bottom: 8rpx;
}

.admin-item-meta {
	display: flex;
	gap: 20rpx;
}

.meta-tag {
	display: flex;
	align-items: center;
	font-size: 22rpx;
	color: #777;
}

.admin-item-arrow {
	margin-left: 20rpx;
	opacity: 0.6;
}

.admin-item-details {
	padding: 0 30rpx 30rpx;
	border-top: 1px solid rgba(255, 255, 255, 0.03);
	animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
	from { opacity: 0; transform: translateY(-10rpx); }
	to { opacity: 1; transform: translateY(0); }
}

.details-grid {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 20rpx;
	background: rgba(255, 255, 255, 0.02);
	padding: 24rpx 20rpx;
	border-radius: 12rpx;
	margin: 24rpx 0;
}

.details-stat {
	text-align: center;
}

.stat-value {
	font-size: 30rpx;
	color: #fff;
	display: block;
	font-weight: 500;
}

.stat-label {
	font-size: 20rpx;
	color: #555;
	text-transform: uppercase;
	margin-top: 4rpx;
}

.admin-item-actions {
	display: flex;
	align-items: center;
	gap: 24rpx;
	padding-top: 10rpx;
}

.action-btn {
	font-size: 26rpx;
	color: #6c5ce7;
	padding: 12rpx 24rpx;
	background: rgba(108, 92, 231, 0.1);
	border-radius: 8rpx;
	font-weight: 500;
}

.action-btn:active {
	opacity: 0.7;
}

.action-danger {
	color: #ff5e5e;
	background: rgba(255, 94, 94, 0.1);
}

/* 兼容现有样式的评论管理项 */
.admin-item {
	display: flex;
	justify-content: space-between;
	align-items: center;
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.05);
	border-radius: 12rpx;
	padding: 24rpx 30rpx;
	margin-bottom: 12rpx;
}

.admin-item-meta-old {
	font-size: 22rpx;
	color: #666;
	margin-top: 6rpx;
	display: block;
}

.empty-state {
	text-align: center;
	padding: 100rpx 60rpx;
}

.empty-state text {
	color: #444;
	font-size: 26rpx;
}

/* 无权限 */
.no-access {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	min-height: 60vh;
}

.no-access-icon {
	font-size: 80rpx;
}

.no-access-text {
	font-size: 32rpx;
	color: #555;
	margin: 20rpx 0 40rpx;
}
</style>
