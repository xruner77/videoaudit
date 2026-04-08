<template>
	<view>
		<view class="filter-section">
			<view class="search-box-wrapper">
				<uni-icons type="search" size="18" color="#888" class="search-icon" />
				<input
					class="search-input"
					v-model="videoQuery"
					placeholder="搜索视频标题..."
					confirm-type="search"
					@confirm="onSearchVideos"
				/>
				<view class="clear-btn" v-if="videoQuery" @click="handleClearVideoQuery">
					<uni-icons type="closeempty" size="14" color="#888" />
				</view>
			</view>
		</view>

		<VideoCard
			v-for="v in videoList"
			:key="v.id"
			:video="v"
			:showDelete="true"
			@click="$emit('go-review', v.id)"
			@delete="deleteVideo(v.id)"
		/>
		<view class="load-more-status" v-if="videoList.length > 0">
			<text v-if="loadingVideos">正在加载...</text>
			<text v-else-if="hasMoreVideos" @click="loadNextVideos">加载更多视频</text>
			<text v-else>—— 已加载全部 ——</text>
		</view>
		<view class="empty-state" v-if="videoList.length === 0 && loadingVideos">
			<text>正在加载...</text>
		</view>
		<view class="empty-state" v-else-if="videoList.length === 0 && !loadingVideos">
			<text>暂无相关视频</text>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import VideoCard from '@/components/VideoCard.vue'
import { useAuthStore } from '@/stores/authStore'
import { usePagination } from '@/composables/usePagination'
import { request } from '@/composables/useRequest'

const emit = defineEmits(['go-review', 'data-changed'])

const authStore = useAuthStore()

const videoQuery = ref('')
const initialized = ref(false)

const {
	dataList: videoList,
	loading: loadingVideos,
	hasMore: hasMoreVideos,
	loadNextPage: loadNextVideos,
	reset: resetVideos
} = usePagination(async (params) => {
	const res = await request({
		url: `${authStore.API_BASE}/api/videos`,
		method: 'GET',
		data: {
			...params,
			q: videoQuery.value
		}
	})
	if (res.statusCode === 200) {
		return { data: res.data.videos, total: res.data.total }
	}
	return { data: [], total: 0 }
}, { limit: 20 })

function refreshVideos() {
	resetVideos()
	loadNextVideos()
}

function onSearchVideos() { refreshVideos() }
function handleClearVideoQuery() { videoQuery.value = ''; refreshVideos() }

async function deleteVideo(id) {
	uni.showModal({
		title: '管理员操作',
		content: '确定要删除此视频及关联的所有评论？',
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await request({
					url: `${authStore.API_BASE}/api/videos/${id}`,
					method: 'DELETE'
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					refreshVideos()
					emit('data-changed', 'videos')
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
		refreshVideos()
	}
}

function init() {
	if (!initialized.value) {
		initialized.value = true
		refreshVideos()
	}
}

function loadNext() {
	loadNextVideos()
}

defineExpose({ refresh, init, loadNext })
</script>

<style scoped>
@import './shared.css';
</style>
