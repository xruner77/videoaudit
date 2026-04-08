<template>
	<view>
		<view class="filter-section">
			<view class="search-box-wrapper">
				<uni-icons type="search" size="18" color="#888" class="search-icon" />
				<input
					class="search-input"
					v-model="commentSearchQuery"
					placeholder="搜索评论内容..."
					confirm-type="search"
					@confirm="onSearchComments"
				/>
				<!-- 清除搜索关键字 -->
				<view class="clear-btn" v-if="commentSearchQuery" @click="handleClearCommentQuery" style="margin-right: 8rpx;">
					<uni-icons type="closeempty" size="14" color="#888" />
				</view>
				<!-- 分隔线 -->
				<view class="filter-divider"></view>
				<!-- 筛选按钮/已选标签 -->
				<view v-if="selectedVideoId" class="inline-video-tag" @click.stop>
					<text class="inline-tag-text">{{ shortVideoName }}</text>
					<view class="inline-tag-close" @click="clearVideoTag">
						<uni-icons type="closeempty" size="10" color="#fff" />
					</view>
				</view>
				<view v-else class="inline-filter-btn" @click.stop="showVideoPicker = !showVideoPicker">
					<uni-icons type="list" size="18" color="#a0a0b8" />
				</view>
			</view>

			<!-- 视频下拉列表（紧贴搜索栏下方） -->
			<view class="video-dropdown" v-if="showVideoPicker">
				<view class="dropdown-search">
					<uni-icons type="search" size="14" color="#666" style="margin-right: 10rpx;" />
					<input class="dropdown-search-input" v-model="videoSearchQuery" placeholder="搜索视频..." />
				</view>
				<scroll-view scroll-y class="dropdown-list">
					<view class="dropdown-item" :class="{ 'dropdown-active': !selectedVideoId }" @click="selectVideoFilter(null)">
						<text>全部视频</text>
					</view>
					<view
						class="dropdown-item"
						v-for="v in filteredVideoOptions"
						:key="v.id"
						:class="{ 'dropdown-active': selectedVideoId === v.id }"
						@click="selectVideoFilter(v)"
					>
						<view class="dropdown-item-info">
							<text class="dropdown-item-title">{{ v.title }}</text>
							<text class="dropdown-item-meta">{{ v.uploader || '系统' }} · {{ v.comment_count || 0 }}条</text>
						</view>
						<uni-icons v-if="selectedVideoId === v.id" type="checkmarkempty" size="14" color="#6c5ce7" />
					</view>
				</scroll-view>
			</view>
			<view class="search-mask" v-if="showVideoPicker" @click="showVideoPicker = false"></view>
		</view>

		<CommentCard
			v-for="c in commentList"
			:key="c.id"
			:comment="c"
			:showDelete="true"
			:clickable="true"
			@click="$emit('go-review', c.video_id)"
			@delete="deleteComment(c.id)"
		/>

		<view class="load-more-status" v-if="commentList.length > 0">
			<text v-if="loadingComments">正在加载...</text>
			<text v-else-if="hasMoreComments" @click="loadNextComments">加载更多评论</text>
			<text v-else>—— 已加载全部 ——</text>
		</view>

		<view class="empty-state" v-if="commentList.length === 0 && loadingComments">
			<text>正在加载...</text>
		</view>
		<view class="empty-state" v-else-if="commentList.length === 0 && !loadingComments">
			<text>暂无相关评论</text>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import CommentCard from '@/components/CommentCard.vue'
import { useAuthStore } from '@/stores/authStore'
import { usePagination } from '@/composables/usePagination'
import { request } from '@/composables/useRequest'

const emit = defineEmits(['go-review', 'data-changed'])

const authStore = useAuthStore()

const videoOptions = ref([])
const selectedVideoId = ref(null)
const videoSearchQuery = ref('')
const commentSearchQuery = ref('')
const showVideoPicker = ref(false)
const initialized = ref(false)

const shortVideoName = computed(() => {
	const name = videoSearchQuery.value || ''
	return name.length > 6 ? name.slice(0, 6) + '…' : name
})

const {
	dataList: commentList,
	loading: loadingComments,
	hasMore: hasMoreComments,
	loadNextPage: loadNextComments,
	reset: resetComments
} = usePagination(async (params) => {
	const res = await request({
		url: `${authStore.API_BASE}/api/admin/comments`,
		method: 'GET',
		data: {
			...params,
			q: commentSearchQuery.value,
			video_id: selectedVideoId.value || undefined
		}
	})
	if (res.statusCode === 200) {
		return { data: res.data.comments, total: res.data.total }
	}
	return { data: [], total: 0 }
}, { limit: 20 })

const filteredVideoOptions = computed(() => {
	if (!videoSearchQuery.value) return videoOptions.value
	const q = videoSearchQuery.value.toLowerCase()
	return videoOptions.value.filter(v =>
		v.title.toLowerCase().includes(q) ||
		(v.uploader && v.uploader.toLowerCase().includes(q))
	)
})

async function fetchVideoOptions() {
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/videos/all-names`
		})
		if (res.data && res.data.videos) {
			videoOptions.value = res.data.videos
		}
	} catch (e) {
		console.error('Fetch video options failed:', e)
	}
}

function refreshComments() { resetComments(); loadNextComments() }

function onSearchComments() { refreshComments() }
function handleClearCommentQuery() { commentSearchQuery.value = ''; refreshComments() }

function selectVideoFilter(video) {
	if (!video) { selectedVideoId.value = null; videoSearchQuery.value = '' }
	else { selectedVideoId.value = video.id; videoSearchQuery.value = video.title }
	showVideoPicker.value = false; refreshComments()
}

function clearVideoTag() {
	selectedVideoId.value = null
	videoSearchQuery.value = ''
	refreshComments()
}

async function deleteComment(id) {
	uni.showModal({
		title: '管理员操作',
		content: '确定要删除此评论？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await request({
					url: `${authStore.API_BASE}/api/comments/${id}`,
					method: 'DELETE'
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					refreshComments()
					emit('data-changed', 'comments')
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

function refresh() {
	if (initialized.value) {
		refreshComments()
		fetchVideoOptions()
	}
}

function init() {
	if (!initialized.value) {
		initialized.value = true
		refreshComments()
		fetchVideoOptions()
	}
}

function loadNext() {
	loadNextComments()
}

function closePopups() {
	showVideoPicker.value = false
}

defineExpose({ refresh, init, loadNext, closePopups, fetchVideoOptions })
</script>

<style scoped>
@import './shared.css';

/* 搜索栏内嵌筛选 */
.filter-divider {
	width: 1px;
	height: 32rpx;
	background: rgba(255, 255, 255, 0.12);
	margin: 0 12rpx;
	flex-shrink: 0;
}

.inline-filter-btn {
	flex-shrink: 0;
	padding: 8rpx;
	cursor: pointer;
	display: flex;
	align-items: center;
	border-radius: 8rpx;
	transition: background 0.15s;
}

.inline-filter-btn:active {
	background: rgba(255, 255, 255, 0.08);
}

.inline-video-tag {
	display: flex;
	align-items: center;
	background: rgba(108, 92, 231, 0.2);
	border-radius: 8rpx;
	padding: 6rpx 10rpx 6rpx 16rpx;
	flex-shrink: 0;
	max-width: 200rpx;
	gap: 6rpx;
}

.inline-tag-text {
	font-size: 22rpx;
	color: #c0b8ff;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.inline-tag-close {
	width: 26rpx;
	height: 26rpx;
	border-radius: 50%;
	background: rgba(255, 255, 255, 0.15);
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
	cursor: pointer;
}

.inline-tag-close:active {
	background: rgba(255, 255, 255, 0.3);
}

/* 视频下拉列表 */
.video-dropdown {
	position: absolute;
	top: 86rpx;
	left: 0;
	right: 0;
	background: #1e1e34;
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 0 0 16rpx 16rpx;
	z-index: 200;
	box-shadow: 0 10rpx 30rpx rgba(0, 0, 0, 0.4);
	overflow: hidden;
}

.dropdown-search {
	display: flex;
	align-items: center;
	padding: 16rpx 20rpx;
	border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.dropdown-search-input {
	flex: 1;
	font-size: 24rpx;
	color: #ddd;
}

.dropdown-list {
	max-height: 480rpx;
}

.dropdown-item {
	display: flex;
	align-items: center;
	justify-content: space-between;
	padding: 22rpx 20rpx;
	border-bottom: 1px solid rgba(255, 255, 255, 0.03);
	color: #ccc;
	font-size: 26rpx;
}

.dropdown-item:active {
	background: rgba(255, 255, 255, 0.04);
}

.dropdown-active {
	color: #6c5ce7;
	background: rgba(108, 92, 231, 0.06);
}

.dropdown-item-info {
	display: flex;
	flex-direction: column;
	min-width: 0;
	flex: 1;
}

.dropdown-item-title {
	font-size: 26rpx;
	color: #e0e0e0;
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.dropdown-active .dropdown-item-title {
	color: #6c5ce7;
}

.dropdown-item-meta {
	font-size: 22rpx;
	color: #666;
	margin-top: 4rpx;
}

.search-mask {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	z-index: 100;
}
</style>
