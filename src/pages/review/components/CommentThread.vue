<template>
	<view class="comment-list">
		<view class="comment-list-header">
			<text class="section-title">审核意见 ({{ comments.length }})</text>
			<view class="sort-btn" @click="showSortPopup = true">
				<text class="sort-text">{{ hasSorted ? sortLabel : '切换排序' }}</text>
				<!-- 自定义上下箭头 SVG -->
				<view class="sort-icon-wrapper">
					<svg viewBox="0 0 24 24" width="12" height="12" stroke="#a0a0b8" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round">
						<polyline points="7 15 12 20 17 15"></polyline>
						<polyline points="7 9 12 4 17 9"></polyline>
					</svg>
				</view>
			</view>
		</view>

		<!-- 父评论 -->
		<template v-for="c in sortedComments" :key="c.id">
			<view class="comment-item" :class="{ 'comment-item-active': activeCommentId === c.id }">
				<view class="comment-main" @click="handleSeekTo(c.id, c.timestamp)">
					<view class="comment-avatar" :style="{ background: getAvatarColor(c.username) }">
						<text class="avatar-letter">{{ getAvatarLetter(c.username) }}</text>
					</view>
					<view class="comment-body">
						<view class="comment-header">
							<text class="comment-username">{{ c.username }}</text>
							<text class="comment-time" :class="{ 'time-active': activeCommentId === c.id }">
								{{ formatTime(c.timestamp) }}
							</text>
						</view>
						<view class="comment-content-box" v-if="c.content || c.image_url">
							<text class="comment-content" v-if="c.content">{{ c.content }}</text>
							<view class="comment-image-list" v-if="getImages(c.image_url).length > 0">
								<view class="image-wrapper" v-for="(img, idx) in getImages(c.image_url)" :key="idx">
									<view class="image-placeholder" v-if="getImageStatus(img + idx) !== 'loaded' && getImageStatus(img + idx) !== 'error'"></view>
									<view class="image-error" v-if="getImageStatus(img + idx) === 'error'">加载失败</view>
									<image 
										:src="getFullUrl(img)" 
										class="comment-image" 
										:class="{ 'hide-img': getImageStatus(img + idx) !== 'loaded' }"
										mode="widthFix" 
										@load="onImageLoadManual(img + idx)"
										@error="onImageErrorManual(img + idx)"
										@click.stop="previewCommentImage(c.image_url, idx)" 
									/>
								</view>
							</view>
						</view>
						<view class="comment-actions">
							<text class="comment-date">{{ formatRelativeTime(c.created_at) }}</text>
							<text class="reply-btn" @click.stop="handleReply(c)">回复</text>
							<text class="reply-btn delete-btn" v-if="isAdmin || c.user_id == currentUserId" @click.stop="handleDelete(c.id)">删除</text>
						</view>
					</view>
				</view>

				<!-- 子评论（全展平，顺序往下排列） -->
				<view class="replies-container" v-if="c.replies && c.replies.length > 0">
					<view class="reply-item" v-for="r in (isExpanded(c.id) ? c.replies : c.replies.slice(0, 3))" :key="r.id" @click.stop="handleSeekTo(c.id, c.timestamp)">
						<view class="comment-avatar unified-avatar" :style="{ background: getAvatarColor(r.username) }">
							<text class="avatar-letter-small">{{ getAvatarLetter(r.username) }}</text>
						</view>
						<view class="reply-body">
							<view class="reply-header">
								<text class="reply-username">{{ r.username }}</text>
							</view>
							<view class="reply-content-box">
								<text class="reply-content">
									<text class="reply-to-at">回复 @{{ getReplyToUsername(r.parent_id) }}：</text>
									<text v-if="r.content">{{ r.content }}</text>
								</text>
								<view class="comment-image-list mt-8" v-if="getImages(r.image_url).length > 0">
									<view class="image-wrapper" v-for="(img, idx) in getImages(r.image_url)" :key="idx">
										<view class="image-placeholder small-placeholder" v-if="getImageStatus(img + idx) !== 'loaded' && getImageStatus(img + idx) !== 'error'"></view>
										<view class="image-error small-error" v-if="getImageStatus(img + idx) === 'error'">失败</view>
										<image 
											:src="getFullUrl(img)" 
											class="reply-image" 
											:class="{ 'hide-img': getImageStatus(img + idx) !== 'loaded' }"
											mode="widthFix" 
											@load="onImageLoadManual(img + idx)"
											@error="onImageErrorManual(img + idx)"
											@click.stop="previewCommentImage(r.image_url, idx)" 
										/>
									</view>
								</view>
							</view>
							<view class="reply-actions">
								<text class="reply-date">{{ formatRelativeTime(r.created_at) }}</text>
								<text class="reply-btn" @click.stop="handleReply(r)">回复</text>
								<text class="reply-btn delete-btn" v-if="isAdmin || r.user_id == currentUserId" @click.stop="handleDelete(r.id)">删除</text>
							</view>
						</view>
					</view>
					<!-- 展开/收回按钮 -->
					<view class="replies-fold-ctrl" v-if="c.replies.length > 3" @click.stop="toggleExpand(c.id)">
						<text class="fold-text">{{ isExpanded(c.id) ? '收起回复' : '展开更多回复 (共 ' + c.replies.length + ' 条)' }}</text>
						<uni-icons :type="isExpanded(c.id) ? 'arrowup' : 'arrowdown'" size="12" color="#6c5ce7" />
					</view>
				</view>
			</view>
		</template>

		<view class="load-more-status" v-if="comments.length > 0">
			<text v-if="loadingComments">正在加载...</text>
			<text v-else-if="hasMoreComments" @click="emit('loadMore')">加载更多评论</text>
			<text v-else>—— 已加载全部评论 ——</text>
		</view>
		<view class="empty-comments" v-if="comments.length === 0 && !loadingComments">
			<text>暂无审核意见</text>
		</view>
	</view>

	<!-- 排序弹窗 -->
	<view class="popup-mask" v-if="showSortPopup" @click="showSortPopup = false">
		<view class="popup-content" @click.stop>
			<view class="popup-header">
				<text class="popup-title">选择排序方式</text>
				<uni-icons type="closeempty" size="20" color="#888" @click="showSortPopup = false" />
			</view>
			<view class="sort-options">
				<view class="sort-option" :class="{ active: sortType === 'newest' }" @click="selectSortType('newest')">最新发布</view>
				<view class="sort-option" :class="{ active: sortType === 'oldest' }" @click="selectSortType('oldest')">最早发布</view>
				<view class="sort-option" :class="{ active: sortType === 'timestamp' }" @click="selectSortType('timestamp')">时间戳</view>
				<view class="sort-option" :class="{ active: sortType === 'reviewer' }" @click="selectSortType('reviewer')">审评者</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useAuthStore } from '../../../stores/authStore'
import { formatTime, getUserColor as getAvatarColor } from '../../../composables/useUtils'
import { request } from '../../../composables/useRequest'

const authStore = useAuthStore()

const props = defineProps({
	comments: Array,
	activeCommentId: [Number, String],
	isAdmin: Boolean,
	currentUserId: [Number, String],
	loadingComments: Boolean,
	hasMoreComments: Boolean
})

const emit = defineEmits(['seekTo', 'reply', 'deleted', 'loadMore'])

const showSortPopup = ref(false)
const sortType = ref('timestamp')
const hasSorted = ref(false)
const expandedCommentIds = ref([])
const imageLoadingStatus = ref({})

const sortLabel = computed(() => {
	switch(sortType.value) {
		case 'newest': return '最新发布';
		case 'oldest': return '最早发布';
		case 'timestamp': return '时间戳';
		case 'reviewer': return '审评者';
		default: return '时间戳';
	}
})

const sortedComments = computed(() => {
	// 1. 创建所有评论的映射，并初始化回复数组
	const commentMap = new Map();
	props.comments.forEach(c => {
		commentMap.set(c.id, { ...c, replies: [] });
	});

	const rootComments = [];
	
	// 2. 将所有回复分配到最顶层的"根评论"下
	commentMap.forEach(comment => {
		if (comment.parent_id) {
			// 递归向上查找根评论
			let current = comment;
			let root = null;
			let visited = new Set();
			
			while (current.parent_id && commentMap.has(current.parent_id) && !visited.has(current.id)) {
				visited.add(current.id);
				current = commentMap.get(current.parent_id);
			}
			
			root = current;
			
			if (root.id !== comment.id) {
				root.replies.push(comment);
			} else {
				rootComments.push(comment);
			}
		} else {
			rootComments.push(comment);
		}
	});

	// 3. 对每个根评论下的展平回复按创建时间排序
	rootComments.forEach(root => {
		root.replies.sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime());
	});

	// 4. 根据当前选择的 sortType 对根评论排序
	const list = [...rootComments];
	switch(sortType.value) {
		case 'timestamp':
			return list.sort((a, b) => a.timestamp - b.timestamp);
		case 'newest':
			return list.sort((a, b) => b.id - a.id);
		case 'oldest':
			return list.sort((a, b) => a.id - b.id);
		case 'reviewer':
			return list.sort((a, b) => a.username.localeCompare(b.username));
		default:
			return list;
	}
})

function selectSortType(type) {
	sortType.value = type
	showSortPopup.value = false
	hasSorted.value = true
}

function toggleExpand(commentId) {
	const index = expandedCommentIds.value.indexOf(commentId)
	if (index > -1) {
		expandedCommentIds.value.splice(index, 1)
	} else {
		expandedCommentIds.value.push(commentId)
	}
}

function isExpanded(commentId) {
	return expandedCommentIds.value.includes(commentId)
}

function onImageLoadManual(key) {
	imageLoadingStatus.value[key] = 'loaded'
}

function onImageErrorManual(key) {
	imageLoadingStatus.value[key] = 'error'
}

function getImageStatus(key) {
	return imageLoadingStatus.value[key] || 'loading'
}

function getFullUrl(path) {
	if (!path) return ''
	if (path.startsWith('http')) return path
	return authStore.API_BASE + path
}

function getImages(imageUrl) {
	if (!imageUrl) return []
	try {
		const parsed = JSON.parse(imageUrl)
		return Array.isArray(parsed) ? parsed : [imageUrl]
	} catch (e) {
		return [imageUrl]
	}
}

function getReplyToUsername(parentId) {
	if (!parentId) return ''
	const p = props.comments.find(c => c.id === parentId)
	return p ? p.username : '未知'
}

function getAvatarLetter(username) {
	return username ? username.charAt(0).toUpperCase() : '?'
}

function previewCommentImage(imageUrl, idx) {
	const imgs = getImages(imageUrl)
	const urls = imgs.map(img => getFullUrl(img))
	uni.previewImage({ urls, current: urls[idx || 0] })
}

function formatRelativeTime(dateStr) {
	if (!dateStr) return '';
	const now = new Date();
	const past = new Date(dateStr.replace(/-/g, '/'));
	const diff = (now - past) / 1000;
	if (diff < 60) return '刚刚';
	if (diff < 3600) return Math.floor(diff / 60) + '分钟前';
	if (diff < 86400) return Math.floor(diff / 3600) + '小时前';
	if (diff < 2592000) return Math.floor(diff / 86400) + '天前';
	return dateStr.split(' ')[0];
}

function handleSeekTo(id, time) {
	emit('seekTo', { id, time })
}

function handleReply(comment) {
	emit('reply', { id: comment.id, username: comment.username, timestamp: comment.timestamp })
}

async function handleDelete(commentId) {
	uni.showModal({
		title: '确认删除',
		content: '确定要删除这条评论吗？',
		success: async (res) => {
			if (res.confirm) {
				try {
					const response = await request({
						url: `${authStore.API_BASE}/api/comments/${commentId}`,
						method: 'DELETE'
					})
					if (response.statusCode === 200) {
						uni.showToast({ title: '删除成功', icon: 'success' })
						emit('deleted')
					} else {
						throw new Error(response.data?.error || '删除失败')
					}
				} catch (e) {
					uni.showToast({ title: e.message || '删除失败', icon: 'none' })
				}
			}
		}
	})
}
</script>

<style scoped>
/* 评论列表 */
.comment-list {
	padding: 24rpx;
}

.comment-list-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 24rpx;
}

.section-title {
	font-size: 30rpx;
	font-weight: 700;
	color: #c0c0d0;
}

.sort-btn {
	display: flex;
	align-items: center;
	padding: 8rpx 0;
	cursor: pointer;
}

.sort-btn:active {
	opacity: 0.7;
}

.sort-icon-wrapper {
	margin-left: 6rpx;
	display: flex;
	align-items: center;
	justify-content: center;
}

.sort-text {
	font-size: 24rpx;
	color: #e0e0e0;
}

.comment-item {
	padding: 24rpx 12rpx;
	border-top: 1px solid rgba(255, 255, 255, 0.04);
	margin: 0 -12rpx;
	transition: background 0.2s ease;
}

.comment-item:first-child {
	border-top: none;
}

.comment-item-active {
	background: rgba(255, 255, 255, 0.05);
	border-radius: 12rpx;
}

.comment-main {
	display: flex;
	gap: 20rpx;
}

.comment-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8rpx;
}

.comment-body {
	flex: 1;
	min-width: 0;
}

.comment-avatar {
	width: 60rpx;
	height: 60rpx;
	border-radius: 50%;
	background: #3d3d52;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.avatar-letter {
	font-size: 26rpx;
	color: #fff;
	font-weight: 600;
}

.avatar-letter-small {
	font-size: 20rpx;
	color: #fff;
	font-weight: 600;
}

.comment-username {
	font-size: 28rpx;
	color: #c0c0d0;
	font-weight: 500;
	line-height: 1.1;
}

.comment-date {
	font-size: 22rpx;
	color: #777788;
}

.comment-time {
	font-size: 26rpx;
	color: #f39c12;
	background: rgba(243, 156, 18, 0.1);
	padding: 4rpx 14rpx;
	border-radius: 6rpx;
	font-variant-numeric: tabular-nums;
	transition: all 0.2s ease;
	height: fit-content;
}

.time-active {
	background: #f39c12 !important;
	color: #fff !important;
}

.comment-content {
	font-size: 30rpx;
	color: #eeeeee;
	line-height: 1.6;
	word-break: break-all;
	display: block;
}

.comment-content-box {
	display: flex;
	flex-direction: column;
}

.comment-image-wrapper {
	margin-top: 12rpx;
	display: block;
}

.comment-actions {
	margin-top: 8rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.reply-btn {
	font-size: 24rpx;
	color: #888899;
	cursor: pointer;
}

.reply-btn:active {
	opacity: 0.7;
}

.comment-image-list {
	display: flex;
	flex-wrap: wrap;
	gap: 12rpx;
	margin-top: 16rpx;
}

.comment-image {
	width: 180rpx;
	height: 180rpx;
	object-fit: cover;
	border-radius: 8rpx;
}

.reply-image {
	width: 140rpx;
	height: 140rpx;
	object-fit: cover;
	border-radius: 8rpx;
	max-width: 200rpx;
}

.image-wrapper {
	position: relative;
	overflow: hidden;
	border-radius: 8rpx;
}

.image-placeholder {
	width: 180rpx;
	height: 180rpx;
	background: rgba(255, 255, 255, 0.05);
	position: relative;
	overflow: hidden;
}

.image-placeholder::after {
	content: "";
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.1), transparent);
	animation: skeleton-loading 1.5s infinite;
}

.small-placeholder {
	width: 140rpx;
	height: 140rpx;
}

@keyframes skeleton-loading {
	0% { transform: translateX(-100%); }
	100% { transform: translateX(100%); }
}

.image-error {
	width: 180rpx;
	height: 180rpx;
	background: rgba(255, 255, 255, 0.03);
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 20rpx;
	color: #666;
	border: 1px dashed rgba(255, 255, 255, 0.1);
	border-radius: 8rpx;
}

.small-error {
	width: 140rpx;
	height: 140rpx;
	font-size: 18rpx;
}

.hide-img {
	position: absolute !important;
	top: 0;
	left: 0;
	opacity: 0;
	width: 0 !important;
	height: 0 !important;
}

.mt-8 {
	margin-top: 8rpx !important;
}

.reply-item {
	display: flex;
	gap: 16rpx;
	padding: 16rpx 0;
	cursor: pointer;
	box-sizing: border-box;
}

.reply-body {
	flex: 1;
	min-width: 0;
}

.replies-container {
	margin-top: 12rpx;
	padding-left: 80rpx;
	display: flex;
	flex-direction: column;
	gap: 4rpx;
}

.reply-header {
	display: flex;
	align-items: center;
	justify-content: space-between;
	margin-bottom: 4rpx;
}

.unified-avatar {
	width: 40rpx !important;
	height: 40rpx !important;
}

.reply-username {
	font-size: 24rpx;
	color: #c0c0d0;
	font-weight: 500;
	line-height: 1;
	margin: 0;
}

.reply-date {
	font-size: 20rpx;
	color: #666;
}

.reply-content-box {
	display: flex;
	flex-direction: column;
}

.reply-content {
	font-size: 27rpx;
	color: #eeeeee;
	line-height: 1.6;
	word-break: break-all;
	display: block;
	margin: 0;
}

.reply-to-at {
	color: #9999aa;
	font-weight: 600;
	margin-right: 8rpx;
}

.reply-actions {
	margin-top: 8rpx;
	display: flex;
	justify-content: space-between;
	align-items: center;
}

.replies-fold-ctrl {
	padding: 20rpx 0 10rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 8rpx;
	cursor: pointer;
}

.replies-fold-ctrl:active {
	opacity: 0.7;
}

.fold-text {
	font-size: 26rpx;
	color: #999;
	font-weight: 500;
}

.delete-btn {
	font-size: 22rpx;
	color: #e74c3c;
	margin-left: 20rpx;
}

.empty-comments {
	text-align: center;
	padding: 40rpx;
}

.empty-comments text {
	color: #555;
	font-size: 26rpx;
}

.load-more-status {
	text-align: center;
	padding: 60rpx 0;
	font-size: 24rpx;
	color: #444;
}

/* --------------- 排序弹窗 --------------- */
.popup-mask {
	position: fixed;
	top: 0; left: 0; right: 0; bottom: 0;
	background: rgba(0, 0, 0, 0.6);
	z-index: 1000;
	display: flex;
	flex-direction: column;
	justify-content: flex-end;
}

.popup-content {
	background: #1f1f2e;
	border-top-left-radius: 20rpx;
	border-top-right-radius: 20rpx;
	padding: 30rpx 40rpx calc(40rpx + env(safe-area-inset-bottom));
	animation: slideUp 0.25s cubic-bezier(0.2, 0.8, 0.2, 1);
}

@keyframes slideUp {
	from { transform: translateY(100%); }
	to { transform: translateY(0); }
}

.popup-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;
}

.popup-title {
	font-size: 32rpx;
	font-weight: bold;
	color: #fff;
}

.sort-options {
	display: grid;
	grid-template-columns: 1fr 1fr;
	gap: 20rpx;
}

.sort-option {
	background: rgba(255, 255, 255, 0.06);
	color: #c0c0d0;
	padding: 26rpx 0;
	text-align: center;
	border-radius: 12rpx;
	font-size: 28rpx;
	transition: all 0.2s;
}

.sort-option.active {
	background: #5b52f6;
	color: #fff;
	font-weight: 600;
}
</style>
