<template>
	<view class="page-container">
		<Header title="管理后台" />

		<view class="admin-container" v-if="authStore.isAdmin">
			<!-- 管理 Tab -->
			<view class="admin-tabs">
				<view class="tab-header">
					<view
						:class="['tab-item', tab === 'dashboard' && 'tab-active']"
						@click="switchTab('dashboard')"
					>
						概览
					</view>
					<view
						:class="['tab-item', tab === 'videos' && 'tab-active']"
						@click="switchTab('videos')"
					>
						视频
					</view>
					<view
						:class="['tab-item', tab === 'comments' && 'tab-active']"
						@click="switchTab('comments')"
					>
						评论
					</view>
					<view
						:class="['tab-item', tab === 'users' && 'tab-active']"
						@click="switchTab('users')"
					>
						用户
					</view>
				</view>
				<view class="tab-indicator-container">
					<view :class="['tab-indicator', 'tab-pos-' + tab]"></view>
				</view>
			</view>

			<!-- 子组件：v-show 保留状态 -->
			<AdminDashboard
				v-show="tab === 'dashboard'"
				ref="dashboardRef"
				@switch-tab="switchTab"
				@go-review="goReview"
			/>
			<AdminVideos
				v-show="tab === 'videos'"
				ref="videosRef"
				@go-review="goReview"
				@data-changed="onDataChanged"
			/>
			<AdminComments
				v-show="tab === 'comments'"
				ref="commentsRef"
				@go-review="goReview"
				@data-changed="onDataChanged"
			/>
			<AdminUsers
				v-show="tab === 'users'"
				ref="usersRef"
				@data-changed="onDataChanged"
			/>
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
import { ref } from 'vue'
import { onShow, onReachBottom } from '@dcloudio/uni-app'
import Header from '@/components/Header.vue'
import { useAuthStore } from '@/stores/authStore'
import { updateTabBarForRole } from '@/composables/useUtils'
import AdminDashboard from './components/AdminDashboard.vue'
import AdminVideos from './components/AdminVideos.vue'
import AdminComments from './components/AdminComments.vue'
import AdminUsers from './components/AdminUsers.vue'

const authStore = useAuthStore()

const tab = ref('dashboard')
const dashboardRef = ref(null)
const videosRef = ref(null)
const commentsRef = ref(null)
const usersRef = ref(null)

onShow(() => {
	if (!authStore.isAdmin) return
	updateTabBarForRole(true)
	// Dashboard 始终刷新
	dashboardRef.value?.refresh()
	// 评论 Tab 的视频筛选下拉也需要刷新
	commentsRef.value?.fetchVideoOptions()
})

onReachBottom(() => {
	if (tab.value === 'videos') videosRef.value?.loadNext()
	else if (tab.value === 'comments') commentsRef.value?.loadNext()
})

function switchTab(newTab) {
	// 切换前关闭评论 Tab 的弹层
	if (tab.value === 'comments' && newTab !== 'comments') {
		commentsRef.value?.closePopups()
	}

	tab.value = newTab

	if (newTab === 'dashboard') {
		dashboardRef.value?.refresh()
	} else if (newTab === 'videos') {
		videosRef.value?.init()
	} else if (newTab === 'comments') {
		commentsRef.value?.init()
	} else if (newTab === 'users') {
		usersRef.value?.init()
	}
}

function onDataChanged(source) {
	// 跳过触发者自身（它已经在内部刷新过了），刷新其他 Tab
	if (source !== 'dashboard') dashboardRef.value?.refresh()
	if (source !== 'videos') videosRef.value?.refresh()
	if (source !== 'comments') commentsRef.value?.refresh()
	if (source !== 'users') usersRef.value?.refresh()
}

function goReview(id) {
	uni.navigateTo({ url: `/pages/review/review?id=${id}` })
}

function goBack() {
	uni.navigateBack()
}
</script>

<style>
/* 共享样式：不使用 scoped，用 .admin-container 前缀限定作用域 */

.admin-container {
	padding: 0 30rpx 140rpx;
}

/* ==================== Tabs ==================== */
.admin-container .admin-tabs {
	position: sticky;
	top: 88rpx;
	z-index: 110;
	padding-top: 10rpx;
	padding-bottom: 10rpx;
	margin-bottom: 0;
	background: #1a1a2e !important;
	margin-left: -30rpx;
	margin-right: -30rpx;
	padding-left: 30rpx;
	padding-right: 30rpx;
}

.admin-container .tab-header {
	display: flex;
	justify-content: space-around;
	padding: 10rpx 0;
}

.admin-container .tab-item {
	font-size: 26rpx;
	color: #777;
	padding: 20rpx 0;
	transition: all 0.3s ease;
	font-weight: 500;
}

.admin-container .tab-active {
	color: #fff;
	font-weight: 600;
}

.admin-container .tab-indicator-container {
	height: 4rpx;
	background: rgba(255, 255, 255, 0.05);
	border-radius: 2rpx;
	position: relative;
}

.admin-container .tab-indicator {
	position: absolute;
	top: 0;
	left: 0;
	width: 25%;
	height: 100%;
	background: linear-gradient(90deg, #6c5ce7, #a855f7);
	border-radius: 2rpx;
	transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
	transform: translateX(0);
}

.admin-container .tab-pos-dashboard {
	transform: translateX(0);
}

.admin-container .tab-pos-videos {
	transform: translateX(100%);
}

.admin-container .tab-pos-comments {
	transform: translateX(200%);
}

.admin-container .tab-pos-users {
	transform: translateX(300%);
}

/* === 移除已迁移的子组件 CSS === */
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
