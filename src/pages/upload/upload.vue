<template>
	<view class="page-container">
		<Header title="上传视频" showBack />

		<view class="upload-container">
			<view class="upload-box">
				<!-- 上传方式切换 -->
			<view class="tab-bar">
				<text :class="['tab-item', uploadMode === 'local' && 'tab-active']" @click="uploadMode = 'local'">
					<uni-icons type="folder" size="16" color="inherit" style="margin-right:8rpx;"/>本地文件
				</text>
				<text :class="['tab-item', uploadMode === 'remote' && 'tab-active']" @click="uploadMode = 'remote'">
					<uni-icons type="link" size="16" color="inherit" style="margin-right:8rpx;"/>远程链接
				</text>
			</view>

			<!-- 本地上传 -->
			<view class="upload-form" v-if="uploadMode === 'local'">
				<view class="form-group">
					<text class="form-label">视频标题</text>
					<input class="dark-input" v-model="title" placeholder="请输入视频标题" maxlength="100" />
				</view>

				<view class="file-picker" @click="chooseVideo">
					<view class="picker-content" v-if="!selectedFile">
						<uni-icons type="cloud-upload" size="48" color="#888" />
						<text class="picker-text">点击选择视频文件</text>
						<text class="picker-hint">支持 mp4、webm 格式，最大 50MB</text>
					</view>
					<view class="picker-content picker-selected" v-else>
						<uni-icons type="videocam" size="48" color="#4caf50" />
						<text class="picker-text">{{ selectedFile.name }}</text>
						<text class="picker-hint">{{ formatSize(selectedFile.size) }}</text>
					</view>
				</view>

				<view class="progress-section" v-if="uploadProgress > 0">
					<view class="upload-progress-bar">
						<view class="upload-progress-fill" :style="{ width: uploadProgress + '%' }"></view>
					</view>
					<text class="progress-text">{{ uploadProgress }}%</text>
				</view>

				<button class="btn-primary submit-btn" @click="uploadLocal" :loading="uploading" :disabled="uploading">
					上传视频
				</button>
			</view>

			<!-- 远程链接 -->
			<view class="upload-form" v-else>
				<view class="form-group">
					<text class="form-label">视频标题</text>
					<input class="dark-input" v-model="title" placeholder="请输入视频标题" maxlength="100" />
				</view>

				<view class="form-group">
					<text class="form-label">视频链接</text>
					<input class="dark-input" v-model="remoteUrl" placeholder="请输入视频 URL（http/https）" />
				</view>

				<button class="btn-primary submit-btn" @click="addRemote" :loading="uploading" :disabled="uploading">
					添加视频
				</button>
			</view>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref, computed } from 'vue'
import { onShow } from '@dcloudio/uni-app'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'
import { request } from '../../composables/useRequest'

const authStore = useAuthStore()

const uploadMode = ref('local')
const title = ref('')
const remoteUrl = ref('')
const selectedFile = ref(null)
const uploading = ref(false)
const uploadProgress = ref(0)

onShow(() => {
	if (!authStore.isLoggedIn) {
		uni.showToast({ title: '请先登录', icon: 'none' })
		setTimeout(() => {
			uni.navigateTo({ url: '/pages/login/login' })
		}, 500)
		return
	}
})

function chooseVideo() {
	// #ifdef H5
	const input = document.createElement('input')
	input.type = 'file'
	input.accept = 'video/mp4,video/webm'
	input.onchange = (e) => {
		const file = e.target.files[0]
		if (file) {
			const video = document.createElement('video')
			video.preload = 'metadata'
			video.onloadedmetadata = () => {
				window.URL.revokeObjectURL(video.src)
				selectedFile.value = {
					file: file,
					name: file.name,
					size: file.size,
					duration: Math.floor(video.duration || 0)
				}
			}
			video.src = URL.createObjectURL(file)
		}
	}
	input.click()
	// #endif

	// #ifndef H5
	uni.chooseVideo({
		sourceType: ['album'],
		success: (res) => {
			selectedFile.value = {
				path: res.tempFilePath,
				name: res.tempFilePath.split('/').pop(),
				size: res.size || 0,
				duration: Math.floor(res.duration || 0)
			}
		}
	})
	// #endif
}

async function uploadLocal() {
	if (!title.value.trim()) {
		return uni.showToast({ title: '请输入标题', icon: 'none' })
	}
	if (!selectedFile.value) {
		return uni.showToast({ title: '请选择视频文件', icon: 'none' })
	}
	if (selectedFile.value.size > 50 * 1024 * 1024) {
		return uni.showToast({ title: '文件大小不能超过 50MB', icon: 'none' })
	}

	uploading.value = true
	uploadProgress.value = 0

	try {
		// #ifdef H5
		const formData = new FormData()
		formData.append('title', title.value.trim())
		formData.append('video', selectedFile.value.file)
		formData.append('duration', selectedFile.value.duration || 0)

		const xhr = new XMLHttpRequest()
		xhr.open('POST', `${authStore.API_BASE}/api/videos/upload`)
		xhr.setRequestHeader('Authorization', `Bearer ${authStore.token}`)

		xhr.upload.onprogress = (e) => {
			if (e.lengthComputable) {
				uploadProgress.value = Math.round((e.loaded / e.total) * 100)
			}
		}

		await new Promise((resolve, reject) => {
			xhr.onload = () => {
				if (xhr.status === 201) {
					try {
						resolve(JSON.parse(xhr.responseText))
					} catch (e) {
						resolve({})
					}
				} else {
					try {
						const data = JSON.parse(xhr.responseText)
						reject(new Error(data.error || '上传失败'))
					} catch (e) {
						if (xhr.status === 413) {
							reject(new Error('文件过大，超出服务器限制 (413)'))
						} else {
							reject(new Error(`上传失败 (HTTP ${xhr.status})`))
						}
					}
				}
			}
			xhr.onerror = () => reject(new Error('网络错误'))
			xhr.send(formData)
		})
		// #endif

		// #ifndef H5
		await new Promise((resolve, reject) => {
			uni.uploadFile({
				url: `${authStore.API_BASE}/api/videos/upload`,
				filePath: selectedFile.value.path,
				name: 'video',
				formData: { 
					title: title.value.trim(),
					duration: selectedFile.value.duration || 0
				},
				header: authStore.getAuthHeader(),
				success: (res) => {
					if (res.statusCode === 201) {
						try {
							resolve(JSON.parse(res.data))
						} catch(e) {
							resolve({})
						}
					} else {
						try {
							const data = JSON.parse(res.data)
							reject(new Error(data.error || '上传失败'))
						} catch (e) {
							if (res.statusCode === 413) {
								reject(new Error('文件过大，超出服务器限制 (413)'))
							} else {
								reject(new Error(`上传失败 (HTTP ${res.statusCode})`))
							}
						}
					}
				},
				fail: (err) => reject(new Error(err.errMsg || '上传失败'))
			})
		})
		// #endif

		uni.showToast({ title: '上传成功', icon: 'success' })
		title.value = ''
		selectedFile.value = null
		uploadProgress.value = 0
		setTimeout(() => {
			uni.switchTab({ url: '/pages/profile/profile' })
		}, 1500)
	} catch (e) {
		uni.showToast({ title: e.message || '上传失败', icon: 'none' })
	} finally {
		uploading.value = false
	}
}

function getRemoteDuration(url) {
	return new Promise((resolve) => {
		// #ifdef H5
		const timer = setTimeout(() => {
			resolve(0)
		}, 5000) // 5s 延时，防止卡死
		
		const video = document.createElement('video')
		video.preload = 'metadata'
		video.onloadedmetadata = () => {
			clearTimeout(timer)
			resolve(Math.floor(video.duration || 0))
		}
		video.onerror = () => {
			clearTimeout(timer)
			resolve(0)
		}
		video.src = url
		// #endif

		// #ifndef H5
		resolve(0)
		// #endif
	})
}

async function addRemote() {
	if (!title.value.trim()) {
		return uni.showToast({ title: '请输入标题', icon: 'none' })
	}
	if (!remoteUrl.value.trim()) {
		return uni.showToast({ title: '请输入视频链接', icon: 'none' })
	}

	uploading.value = true
	try {
		const duration = await getRemoteDuration(remoteUrl.value.trim())
		const res = await request({
			url: `${authStore.API_BASE}/api/videos/remote`,
			method: 'POST',
			header: {
				'Content-Type': 'application/json'
			},
			data: {
				title: title.value.trim(),
				url: remoteUrl.value.trim(),
				duration: duration
			}
		})

		if (res.statusCode === 201) {
			uni.showToast({ title: '添加成功', icon: 'success' })
			title.value = ''
			remoteUrl.value = ''
			setTimeout(() => {
				uni.switchTab({ url: '/pages/profile/profile' })
			}, 1500)
		} else {
			throw new Error(res.data?.error || '添加失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message || '添加失败', icon: 'none' })
	} finally {
		uploading.value = false
	}
}

function goReview(id) {
	uni.navigateTo({ url: `/pages/review/review?id=${id}` })
}

function formatSize(bytes) {
	if (!bytes) return '未知大小'
	if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
	return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<style scoped>

.upload-container {
	padding: 24rpx;
}

.upload-box {
	background: rgba(255, 255, 255, 0.06);
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 24rpx;
	padding: 30rpx;
	margin-bottom: 40rpx;
	box-shadow: 0 8rpx 32rpx rgba(0, 0, 0, 0.25);
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

.upload-form {
	margin-bottom: 10rpx;
}

.form-group {
	margin-bottom: 24rpx;
}

.form-label {
	font-size: 24rpx;
	color: #888;
	margin-bottom: 12rpx;
	display: block;
}

.file-picker {
	border: 2rpx dashed rgba(108, 92, 231, 0.4);
	border-radius: 16rpx;
	padding: 40rpx;
	text-align: center;
	margin-bottom: 24rpx;
}

.file-picker:active {
	background: rgba(108, 92, 231, 0.05);
}

.picker-icon {
	font-size: 60rpx;
	display: block;
	margin-bottom: 16rpx;
}

.picker-text {
	font-size: 28rpx;
	color: #a0a0b8;
	display: block;
}

.picker-hint {
	font-size: 22rpx;
	color: #555;
	display: block;
	margin-top: 8rpx;
}

.picker-selected {
	border-color: #6c5ce7;
	background: rgba(108, 92, 231, 0.05);
}

.progress-section {
	display: flex;
	align-items: center;
	gap: 16rpx;
	margin-bottom: 24rpx;
}

.upload-progress-bar {
	flex: 1;
	height: 8rpx;
	background: rgba(255, 255, 255, 0.1);
	border-radius: 4rpx;
	overflow: hidden;
}

.upload-progress-fill {
	height: 100%;
	background: linear-gradient(90deg, #6c5ce7, #a855f7);
	border-radius: 4rpx;
	transition: width 0.2s;
}

.progress-text {
	font-size: 24rpx;
	color: #6c5ce7;
	min-width: 80rpx;
	text-align: right;
}

.submit-btn {
	width: 100%;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0;
}
</style>
