<template>
	<view class="data-state-container">
		<!-- 骨架屏占位 -->
		<view class="skeleton-wrapper" v-if="initLoading">
			<!-- Video Grid Skeleton -->
			<view v-if="skeletonType === 'video'" class="skeleton-grid">
				<view class="skeleton-card" v-for="i in 4" :key="i">
					<view class="skeleton-box skeleton-thumb"></view>
					<view class="skeleton-info">
						<view class="skeleton-box skeleton-text skeleton-title"></view>
						<view class="skeleton-box skeleton-text skeleton-meta"></view>
					</view>
				</view>
			</view>

			<!-- Comment Skeleton -->
			<view v-else-if="skeletonType === 'comment'" class="skeleton-list">
				<view class="skeleton-comment-item" v-for="i in 4" :key="i">
					<view class="skeleton-box skeleton-avatar"></view>
					<view class="skeleton-comment-body">
						<view class="skeleton-box skeleton-text skeleton-title w-50"></view>
						<view class="skeleton-box skeleton-text skeleton-meta w-100"></view>
					</view>
				</view>
			</view>

			<!-- User Skeleton -->
			<view v-else-if="skeletonType === 'user'" class="skeleton-list">
				<view class="skeleton-user-item" v-for="i in 5" :key="i">
					<view class="skeleton-box skeleton-avatar-small"></view>
					<view class="skeleton-user-body">
						<view class="skeleton-box skeleton-text skeleton-title w-40"></view>
						<view class="skeleton-box skeleton-text skeleton-meta w-30 mt-10"></view>
					</view>
				</view>
			</view>

			<!-- Dashboard Skeleton -->
			<view v-else-if="skeletonType === 'dashboard'" class="skeleton-dashboard">
				<view class="skeleton-stats-row">
					<view class="skeleton-box skeleton-stat-card" v-for="i in 3" :key="i"></view>
				</view>
				<view class="skeleton-box skeleton-section-title w-30"></view>
				<view class="skeleton-box skeleton-card-wide mb-20" v-for="i in 2" :key="'rd'+i"></view>
			</view>

			<!-- 通用/默认 Skeleton -->
			<view v-else class="skeleton-list">
				<view class="skeleton-box skeleton-card-wide mb-20" v-for="i in 4" :key="i"></view>
			</view>
		</view>

		<!-- 实际内容 -->
		<view class="content-wrapper" v-show="!initLoading && !isEmpty">
			<slot></slot>
		</view>

		<!-- 空状态 -->
		<view class="empty-state" v-if="!initLoading && isEmpty">
			<uni-icons :type="emptyIcon" size="48" color="#555" />
			<text class="empty-text">{{ emptyText }}</text>
		</view>

		<!-- 底部分页态 -->
		<view class="load-more-status" v-if="showPagination && !isEmpty && !initLoading">
			<view class="spinner" v-if="loadingMore"></view>
			<text v-if="loadingMore" class="loading-more-text">加载中...</text>
			<text v-else-if="hasMore" class="has-more-text" @click="$emit('loadMore')">继续滚动加载</text>
			<text v-else class="no-more-text">—— 已加载全部 ——</text>
		</view>
	</view>
</template>

<script setup>
defineProps({
	initLoading: { type: Boolean, default: false },
	isEmpty: { type: Boolean, default: false },
	emptyText: { type: String, default: '暂无数据' },
	emptyIcon: { type: String, default: 'info' },
	showPagination: { type: Boolean, default: false },
	loadingMore: { type: Boolean, default: false },
	hasMore: { type: Boolean, default: false },
	skeletonType: { type: String, default: 'list' }
})
defineEmits(['loadMore'])
</script>

<style scoped>
.data-state-container {
	width: 100%;
}

/* ==================== Shimmer Animation ==================== */
@keyframes shimmer {
	0% { background-position: -200% 0; }
	100% { background-position: 200% 0; }
}

.skeleton-box {
	background: linear-gradient(90deg, rgba(255,255,255,0.02) 25%, rgba(255,255,255,0.06) 50%, rgba(255,255,255,0.02) 75%);
	background-size: 200% 100%;
	animation: shimmer 1.5s infinite;
	border-radius: 8rpx;
}

/* ==================== Skeleton Layouts ==================== */

.skeleton-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 20rpx;
	padding: 10rpx 0;
}

.skeleton-card {
	background: rgba(255,255,255,0.02);
	border-radius: 16rpx;
	overflow: hidden;
}

.skeleton-thumb {
	height: 220rpx;
	border-radius: 0;
}

.skeleton-info {
	padding: 16rpx;
}

.skeleton-text {
	height: 24rpx;
	margin-bottom: 12rpx;
}

.skeleton-title { width: 80%; }
.skeleton-meta { width: 50%; opacity: 0.7; }

/* Comment Style */
.skeleton-list {
	padding: 10rpx 0;
}

.skeleton-comment-item {
	display: flex;
	margin-bottom: 30rpx;
	background: rgba(255,255,255,0.01);
	padding: 20rpx;
	border-radius: 12rpx;
}

.skeleton-avatar {
	width: 80rpx;
	height: 80rpx;
	border-radius: 50%;
	margin-right: 20rpx;
	flex-shrink: 0;
}

.skeleton-comment-body {
	flex: 1;
	padding-top: 10rpx;
}

/* User Style */
.skeleton-user-item {
	display: flex;
	align-items: center;
	padding: 24rpx 20rpx;
	margin-bottom: 16rpx;
	background: rgba(255,255,255,0.01);
	border: 1px solid rgba(255,255,255,0.03);
	border-radius: 16rpx;
}

.skeleton-avatar-small {
	width: 60rpx;
	height: 60rpx;
	border-radius: 50%;
	margin-right: 20rpx;
	flex-shrink: 0;
}

.skeleton-user-body { flex: 1; }

/* Dashboard Style */
.skeleton-dashboard {
	padding: 10rpx 0;
}

.skeleton-stats-row {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 16rpx;
	margin-bottom: 40rpx;
}

.skeleton-stat-card {
	height: 160rpx;
	border-radius: 20rpx;
}

.skeleton-section-title {
	height: 32rpx;
	margin-bottom: 24rpx;
}

/* Generic */
.skeleton-card-wide {
	height: 140rpx;
	background: rgba(255,255,255,0.02);
	border-radius: 16rpx;
}
.w-100 { width: 100%; }
.w-50 { width: 50%; }
.w-40 { width: 40%; }
.w-30 { width: 30%; }
.mt-10 { margin-top: 10rpx; }
.mb-20 { margin-bottom: 20rpx; }


/* ==================== Empty State ==================== */
.empty-state {
	display: flex;
	flex-direction: column;
	align-items: center;
	justify-content: center;
	padding: 120rpx 0;
}

.empty-text {
	font-size: 26rpx;
	color: #555;
	margin-top: 24rpx;
	letter-spacing: 1rpx;
}


/* ==================== Load More ==================== */
.load-more-status {
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 40rpx 0;
}

.spinner {
	width: 28rpx;
	height: 28rpx;
	border: 3rpx solid rgba(108, 92, 231, 0.2);
	border-top-color: #6c5ce7;
	border-radius: 50%;
	animation: spin 0.8s linear infinite;
	margin-right: 12rpx;
}

@keyframes spin {
	to { transform: rotate(360deg); }
}

.loading-more-text { font-size: 24rpx; color: #a0a0b8; }
.has-more-text { font-size: 24rpx; color: #6c5ce7; padding: 10rpx 30rpx; }
.no-more-text { font-size: 24rpx; color: #444; }
</style>
