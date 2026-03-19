<template>
	<view class="login-page">
		<view class="login-bg"></view>
		<view class="login-container">
			<view class="login-logo">
				<text class="logo-icon">🎬</text>
				<text class="logo-title">视频审片系统</text>
				<text class="logo-subtitle">Video Audit System</text>
			</view>

			<view class="login-card">
				<view class="tab-bar">
					<text :class="['tab-item', !isRegister && 'tab-active']" @click="isRegister = false">登录</text>
					<text :class="['tab-item', isRegister && 'tab-active']" @click="isRegister = true">注册</text>
				</view>

				<view class="form-group">
					<text class="form-label">用户名</text>
					<input class="dark-input" v-model="username" placeholder="请输入用户名" maxlength="20" />
				</view>

				<view class="form-group">
					<text class="form-label">密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="password" placeholder="请输入密码" :password="!showPassword" maxlength="32" />
						<view class="eye-icon" @click="showPassword = !showPassword">
							<uni-icons :type="showPassword ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<view class="form-group" v-if="isRegister">
					<text class="form-label">确认密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="confirmPassword" placeholder="请再次输入密码" :password="!showConfirmPassword" maxlength="32" />
						<view class="eye-icon" @click="showConfirmPassword = !showConfirmPassword">
							<uni-icons :type="showConfirmPassword ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<button class="btn-primary login-btn" :loading="loading" @click="handleSubmit">
					{{ isRegister ? '注 册' : '登 录' }}
				</button>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import { useAuthStore } from '../../stores/authStore'

const authStore = useAuthStore()
const isRegister = ref(false)
const username = ref('')
const password = ref('')
const confirmPassword = ref('')
const showPassword = ref(false)
const showConfirmPassword = ref(false)
const loading = ref(false)

async function handleSubmit() {
	if (!username.value.trim() || !password.value) {
		return uni.showToast({ title: '请填写完整信息', icon: 'none' })
	}

	if (isRegister.value) {
		if (password.value !== confirmPassword.value) {
			return uni.showToast({ title: '两次密码不一致', icon: 'none' })
		}
		if (password.value.length < 6) {
			return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
		}
	}

	loading.value = true
	try {
		if (isRegister.value) {
			await authStore.register(username.value.trim(), password.value)
			uni.showToast({ title: '注册成功，请登录', icon: 'success' })
			isRegister.value = false
			password.value = ''
			confirmPassword.value = ''
			showPassword.value = false
			showConfirmPassword.value = false
		} else {
			await authStore.login(username.value.trim(), password.value)
			uni.showToast({ title: '登录成功', icon: 'success' })
			setTimeout(() => {
				uni.reLaunch({ url: '/pages/videoList/videoList' })
			}, 500)
		}
	} catch (e) {
		uni.showToast({ title: e.message || '操作失败', icon: 'none' })
	} finally {
		loading.value = false
	}
}
</script>

<style scoped>
.login-page {
	min-height: 100vh;
	display: flex;
	align-items: center;
	justify-content: center;
	background: #0f0f1a;
	position: relative;
	overflow: hidden;
}

.login-bg {
	position: absolute;
	top: -30%;
	left: -20%;
	width: 600rpx;
	height: 600rpx;
	background: radial-gradient(circle, rgba(108, 92, 231, 0.15) 0%, transparent 70%);
	border-radius: 50%;
}

.login-container {
	width: 85%;
	max-width: 680rpx;
	z-index: 1;
}

.login-logo {
	text-align: center;
	margin-bottom: 60rpx;
}

.logo-icon {
	font-size: 80rpx;
	display: block;
	margin-bottom: 16rpx;
}

.logo-title {
	font-size: 44rpx;
	color: #fff;
	display: block;
}

.logo-subtitle {
	font-size: 24rpx;
	color: #555;
	margin-top: 8rpx;
	letter-spacing: 4rpx;
	display: block;
}

.login-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 24rpx;
	padding: 40rpx;
	backdrop-filter: blur(20px);
}

.tab-bar {
	display: flex;
	margin-bottom: 40rpx;
	background: rgba(255, 255, 255, 0.04);
	border-radius: 12rpx;
	padding: 6rpx;
}

.tab-item {
	flex: 1;
	text-align: center;
	padding: 16rpx 0;
	font-size: 28rpx;
	color: #666;
	border-radius: 10rpx;
	transition: all 0.3s;
}

.tab-active {
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	color: #fff;
	font-weight: 600;
}

.form-group {
	margin-bottom: 28rpx;
}

.form-label {
	font-size: 24rpx;
	color: #888;
	margin-bottom: 12rpx;
	display: block;
}

.password-input-wrapper {
	position: relative;
	width: 100%;
}

.pr-80 {
	padding-right: 80rpx !important;
}

.eye-icon {
	position: absolute;
	right: 0;
	top: 0;
	width: 80rpx;
	height: 100%;
	display: flex;
	align-items: center;
	justify-content: center;
	z-index: 2;
}

.login-btn {
	margin-top: 20rpx;
	width: 100%;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0;
	font-size: 32rpx;
	letter-spacing: 8rpx;
}
</style>
