<template>
	<view class="page-container">
		<Header title="修改密码" showBack />
		
		<view class="container">
			<view class="card">
				<view class="form-group">
					<text class="form-label">当前密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="oldPassword" placeholder="请输入当前密码" :password="!showOldPwd" />
						<view class="eye-icon" @click="showOldPwd = !showOldPwd">
							<uni-icons :type="showOldPwd ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<view class="form-group">
					<text class="form-label">新密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="newPassword" placeholder="请输入新密码（至少6位）" :password="!showNewPwd" />
						<view class="eye-icon" @click="showNewPwd = !showNewPwd">
							<uni-icons :type="showNewPwd ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<view class="form-group">
					<text class="form-label">确认新密码</text>
					<view class="password-input-wrapper">
						<input class="dark-input pr-80" v-model="confirmPassword" placeholder="请再次输入新密码" :password="!showConfirmPwd" />
						<view class="eye-icon" @click="showConfirmPwd = !showConfirmPwd">
							<uni-icons :type="showConfirmPwd ? 'eye-filled' : 'eye-slash'" size="20" color="#888" />
						</view>
					</view>
				</view>

				<button class="btn-primary submit-btn" :loading="loading" @click="handleSubmit">
					确认修改
				</button>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import Header from '../../components/Header.vue'
import { useAuthStore } from '../../stores/authStore'
import { request } from '../../composables/useRequest'

const authStore = useAuthStore()
const oldPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const showOldPwd = ref(false)
const showNewPwd = ref(false)
const showConfirmPwd = ref(false)
const loading = ref(false)

async function handleSubmit() {
	if (!oldPassword.value) {
		return uni.showToast({ title: '请输入当前密码', icon: 'none' })
	}
	if (!newPassword.value || newPassword.value.length < 6) {
		return uni.showToast({ title: '新密码至少6个字符', icon: 'none' })
	}
	if (newPassword.value !== confirmPassword.value) {
		return uni.showToast({ title: '两次密码不一致', icon: 'none' })
	}

	loading.value = true
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/auth/password`,
			method: 'PUT',
			header: {
				'Content-Type': 'application/json'
			},
			data: {
				old_password: oldPassword.value,
				new_password: newPassword.value
			}
		})
		if (res.statusCode === 200) {
			uni.showToast({ title: '密码修改成功', icon: 'success' })
			setTimeout(() => {
				uni.navigateBack()
			}, 1500)
		} else {
			throw new Error(res.data?.error || '修改失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message || '修改失败', icon: 'none' })
	} finally {
		loading.value = false
	}
}
</script>

<style scoped>
.container {
	padding: 40rpx;
	display: flex;
	flex-direction: column;
	align-items: center;
}

.card {
	width: 100%;
	max-width: 680rpx;
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.06);
	border-radius: 24rpx;
	padding: 40rpx;
	backdrop-filter: blur(20px);
	margin-top: 40rpx;
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

.submit-btn {
	margin-top: 40rpx;
	width: 100%;
	height: 88rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	padding: 0;
	font-size: 32rpx;
}
</style>
