<template>
	<view class="comment-input-section" v-if="isLoggedIn">
		<!-- 回复提示 -->
		<view class="reply-hint" v-if="replyTo">
			<text class="reply-hint-text">回复 @{{ replyTo.username }}</text>
			<uni-icons type="closeempty" size="16" color="#888" @click="emit('cancelReply')" />
		</view>
		<view class="input-row">
			<view class="img-pick-btn" @click="chooseImage">
				<uni-icons type="camera" size="24" color="#b8b8b8" />
			</view>
			<textarea
				class="dark-input comment-text-textarea"
				v-model="commentText"
				:placeholder="replyTo ? '回复 ' + replyTo.username + '...' : '输入审核意见...'"
				@focus="onFocus"
				maxlength="500"
				auto-height
				:fixed="true"
			/>
			<button class="btn-primary send-btn" @click="submitComment" :loading="submitting">发送</button>
		</view>
		<!-- 图片预览 -->
		<view class="img-preview-row" v-if="selectedImages.length > 0">
			<view class="img-preview-item" v-for="(img, idx) in selectedImages" :key="idx">
				<image :src="img.path" class="img-thumb" mode="aspectFill" @click="previewSelectedImage(idx)" />
				<view class="img-upload-progress" v-if="img.uploading">
					<text class="progress-text">{{ img.progress }}%</text>
				</view>
				<view class="img-remove" @click="removeImage(idx)">
					<uni-icons type="closeempty" size="12" color="#fff" />
				</view>
			</view>
		</view>
		<text class="input-hint" v-if="commentTimestamp >= 0">
			📍 将标记在 {{ formatTime(commentTimestamp) }}
		</text>
	</view>
	<view class="login-hint" v-else>
		<text @click="goLogin">请先登录后发表评论 →</text>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../../stores/authStore'
import { formatTime } from '../../../composables/useUtils'
import { request } from '../../../composables/useRequest'

const authStore = useAuthStore()

const props = defineProps({
	isLoggedIn: Boolean,
	replyTo: Object,
	commentTimestamp: Number,
	currentTime: Number,
	videoId: Number
})

const emit = defineEmits(['cancelReply', 'pauseForComment', 'submitted'])

const commentText = ref('')
const selectedImages = ref([])
const submitting = ref(false)

function onFocus() {
	emit('pauseForComment')
}

function goLogin() {
	uni.navigateTo({ url: '/pages/login/login' })
}

function chooseImage() {
	if (selectedImages.value.length >= 3) {
		return uni.showToast({ title: '最多上传 3 张图片', icon: 'none' })
	}
	uni.chooseImage({
		count: 3 - selectedImages.value.length,
		sizeType: ['compressed'],
		success: (res) => {
			const newImages = res.tempFilePaths.map(p => ({
				path: p,
				progress: 0,
				uploading: false,
				url: ''
			}))
			selectedImages.value.push(...newImages)
		}
	})
}

function removeImage(idx) {
	selectedImages.value.splice(idx, 1)
}

function previewSelectedImage(idx) {
	const urls = selectedImages.value.map(img => img.path)
	uni.previewImage({ urls, current: urls[idx] })
}

async function uploadImage(imgObj) {
	return new Promise((resolve, reject) => {
		imgObj.uploading = true
		const uploadTask = uni.uploadFile({
			url: `${authStore.API_BASE}/api/comments/upload-image`,
			filePath: imgObj.path,
			name: 'image',
			header: authStore.getAuthHeader(),
			success: (res) => {
				const data = typeof res.data === 'string' ? JSON.parse(res.data) : res.data
				if (res.statusCode === 201 && data.url) {
					imgObj.url = data.url
					imgObj.uploading = false
					resolve(data.url)
				} else {
					imgObj.uploading = false
					reject(new Error(data.error || '图片上传失败'))
				}
			},
			fail: (err) => {
				imgObj.uploading = false
				reject(new Error('图片上传失败'))
			}
		})

		uploadTask.onProgressUpdate((res) => {
			imgObj.progress = res.progress
		})
	})
}

async function submitComment() {
	const text = commentText.value.trim()
	if (!text && selectedImages.value.length === 0) {
		return uni.showToast({ title: '请输入评论内容或选择图片', icon: 'none' })
	}

	submitting.value = true
	try {
		// Upload all selected images
		const uploadPromises = selectedImages.value.map(img => {
			if (img.url) return Promise.resolve(img.url)
			return uploadImage(img)
		})
		
		const imageUrls = await Promise.all(uploadPromises)
		const imageUrlData = imageUrls.length > 0 ? JSON.stringify(imageUrls) : ''

		const res = await request({
			url: `${authStore.API_BASE}/api/comments`,
			method: 'POST',
			header: {
				'Content-Type': 'application/json'
			},
			data: {
				video_id: props.videoId,
				content: text,
				timestamp: props.commentTimestamp >= 0 ? props.commentTimestamp : props.currentTime,
				image_url: imageUrlData || undefined,
				parent_id: props.replyTo ? props.replyTo.id : undefined
			}
		})

		if (res.statusCode === 201) {
			uni.showToast({ title: '评论成功', icon: 'success' })
			commentText.value = ''
			selectedImages.value = []
			emit('submitted')
		} else {
			throw new Error(res.data?.error || '评论失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message || '评论失败', icon: 'none' })
	} finally {
		submitting.value = false
	}
}
</script>

<style scoped>
/* 评论输入 */
.comment-input-section {
	position: fixed;
	bottom: 0;
	left: 0;
	width: 100%;
	box-sizing: border-box;
	background: #1a1a2e;
	padding: 24rpx 24rpx calc(24rpx + env(safe-area-inset-bottom));
	border-top: 1px solid rgba(255, 255, 255, 0.08);
	z-index: 100;
}

.input-row {
	display: flex;
	gap: 16rpx;
	align-items: center;
}

.comment-text-textarea {
	flex: 1;
	line-height: 1.6;
	min-height: 1.6em;
	padding: 10rpx 20rpx;
	box-sizing: border-box;
	background: rgba(0, 0, 0, 0.2);
	border-radius: 12rpx;
}

.send-btn {
	width: 140rpx;
	height: 64rpx;
	padding: 0;
	font-size: 26rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	flex-shrink: 0;
}

.img-pick-btn {
	display: flex;
	align-items: center;
	justify-content: center;
	width: 44px;
	height: 44px;
	flex-shrink: 0;
	cursor: pointer;
}

.img-pick-btn:active {
	opacity: 0.7;
}

.input-hint {
	font-size: 22rpx;
	color: #f39c12;
	margin-top: 10rpx;
	display: block;
}

.login-hint {
	position: fixed;
	bottom: 0;
	left: 0;
	width: 100%;
	box-sizing: border-box;
	background: #1a1a2e;
	padding: 30rpx 24rpx calc(30rpx + env(safe-area-inset-bottom));
	border-top: 1px solid rgba(255, 255, 255, 0.08);
	z-index: 100;
	text-align: center;
}

.login-hint text {
	color: #6c5ce7;
	font-size: 26rpx;
}

.reply-hint {
	display: flex;
	justify-content: space-between;
	align-items: center;
	padding: 12rpx 16rpx;
	margin-bottom: 12rpx;
	background: rgba(255, 255, 255, 0.05);
	border-radius: 8rpx;
}

.reply-hint-text {
	font-size: 24rpx;
	color: #999;
}

.img-preview-row {
	display: flex;
	flex-wrap: wrap;
	gap: 16rpx;
	margin-top: 16rpx;
}

.img-preview-item {
	position: relative;
	width: 100rpx;
	height: 100rpx;
}

.img-thumb {
	width: 100%;
	height: 100%;
	border-radius: 8rpx;
	object-fit: cover;
	background: #252538;
}

.img-upload-progress {
	position: absolute;
	top: 0;
	left: 0;
	width: 100%;
	height: 100%;
	background: rgba(0, 0, 0, 0.6);
	display: flex;
	align-items: center;
	justify-content: center;
	border-radius: 8rpx;
}

.progress-text {
	font-size: 20rpx;
	color: #fff;
}

.img-remove {
	position: absolute;
	top: -12rpx;
	right: -12rpx;
	width: 34rpx;
	height: 34rpx;
	border-radius: 50%;
	background: #000;
	opacity: 0.9;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.img-remove:active {
	background: #e74c3c;
}
</style>
