<template>
	<view>
		<!-- 用户列表 -->
		<view class="user-list-header">
			<text class="section-title">用户列表 ({{ userList.length }})</text>
			<view class="create-user-inline-btn" @click="showCreateUser = true">
				<uni-icons type="plusempty" size="14" color="#fff" style="margin-right:4rpx;" />新建
			</view>
		</view>
		<DataState
			:initLoading="loadingUsers && userList.length === 0"
			:isEmpty="userList.length === 0"
			emptyText="暂无相关用户"
			emptyIcon="person"
			skeletonType="user"
		>
			<view class="admin-item-card" v-for="u in userList" :key="u.id">
				<view class="admin-item-header">
					<view class="user-avatar-small" :style="{ background: getUserColor(u.username) }">
						<text class="avatar-letter-s">{{ u.username ? u.username.charAt(0).toUpperCase() : '?' }}</text>
					</view>
					<view class="admin-item-info">
						<text class="admin-item-title">{{ u.username }}</text>
						<view class="admin-item-meta">
							<view :class="['role-badge', u.role === 'admin' ? 'role-admin' : 'role-user']">
								<text>{{ u.role === 'admin' ? '管理员' : '审片员' }}</text>
							</view>
							<view class="meta-tag">
								<text>{{ formatDateSimple(u.created_at) }}</text>
							</view>
							<view class="meta-tag" style="margin-left: 8rpx;">
								<uni-icons type="videocam" size="12" color="#a0a0b8" />
								<text style="margin-left:4rpx">{{ u.video_count || 0 }}</text>
							</view>
							<view class="meta-tag" style="margin-left: 8rpx;">
								<uni-icons type="chat" size="12" color="#a0a0b8" />
								<text style="margin-left:4rpx">{{ u.comment_count || 0 }}</text>
							</view>
						</view>
					</view>
					<view class="user-actions-row" v-if="u.id != authStore.user?.id">
						<text class="btn-link neutral small-text" @click="openResetPassword(u.id, u.username)">
							<uni-icons type="locked" size="14" color="#a0a0b8" style="margin-right:6rpx;" />重置密码
						</text>
						<text class="btn-link danger small-text" @click="deleteUser(u.id, u.username)">
							<uni-icons type="trash" size="14" color="#e74c3c" style="margin-right:6rpx;" />删除
						</text>
					</view>
				</view>
			</view>
		</DataState>

		<!-- 创建用户弹层 -->
		<view class="modal-mask" v-if="showCreateUser" @click="showCreateUser = false">
			<view class="modal-content" @click.stop>
				<view class="modal-header">
					<text class="modal-title">创建新用户</text>
					<view class="modal-close" @click="showCreateUser = false">
						<uni-icons type="closeempty" size="18" color="#999" />
					</view>
				</view>
				<view class="form-group">
					<text class="form-label">用户名</text>
					<input class="dark-input" v-model="newUsername" placeholder="2-20个字符" maxlength="20" />
				</view>
				<view class="form-group">
					<text class="form-label">密码</text>
					<input class="dark-input" v-model="newPassword" placeholder="至少6个字符" maxlength="32" />
				</view>
				<view class="form-group">
					<text class="form-label">角色</text>
					<view class="role-selector">
						<view :class="['role-option', newRole === 'user' && 'role-active']" @click="newRole = 'user'">审片员</view>
						<view :class="['role-option', newRole === 'admin' && 'role-active']" @click="newRole = 'admin'">管理员</view>
					</view>
				</view>
				<button class="btn-primary create-user-btn" :loading="creatingUser" @click="createUser">创建用户</button>
			</view>
		</view>

		<!-- 重置密码弹层 -->
		<view class="modal-mask" v-if="showResetPassword" @click="showResetPassword = false">
			<view class="modal-content" @click.stop>
				<view class="modal-header">
					<text class="modal-title">重置密码</text>
					<view class="modal-close" @click="showResetPassword = false">
						<uni-icons type="closeempty" size="18" color="#999" />
					</view>
				</view>
				<view class="reset-user-hint">
					<text>为用户「{{ resetTargetName }}」设置新密码</text>
				</view>
				<view class="form-group">
					<text class="form-label">新密码</text>
					<input class="dark-input" v-model="resetNewPassword" placeholder="至少6个字符" maxlength="32" />
				</view>
				<button class="btn-primary create-user-btn" :loading="resettingPassword" @click="resetPassword">确认重置</button>
			</view>
		</view>
	</view>
</template>

<script setup>
import { ref } from 'vue'
import DataState from '@/components/DataState.vue'
import { useAuthStore } from '@/stores/authStore'
import { formatDateSimple, getUserColor } from '@/composables/useUtils'
import { request } from '@/composables/useRequest'

const emit = defineEmits(['data-changed'])

const authStore = useAuthStore()

const userList = ref([])
const loadingUsers = ref(false)
const newUsername = ref('')
const newPassword = ref('')
const newRole = ref('user')
const creatingUser = ref(false)
const showCreateUser = ref(false)
const showResetPassword = ref(false)
const resetTargetId = ref(null)
const resetTargetName = ref('')
const resetNewPassword = ref('')
const resettingPassword = ref(false)
const initialized = ref(false)

async function fetchUsers(showLoader = true) {
	if (showLoader && userList.value.length === 0) {
		loadingUsers.value = true
	}
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/admin/users`,
			method: 'GET'
		})
		if (res.statusCode === 200) {
			userList.value = res.data.users || []
		}
	} catch (e) {
		console.error('Fetch users failed:', e)
	} finally {
		loadingUsers.value = false
	}
}

async function createUser() {
	if (!newUsername.value.trim()) {
		return uni.showToast({ title: '请输入用户名', icon: 'none' })
	}
	if (!newPassword.value || newPassword.value.length < 6) {
		return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
	}

	creatingUser.value = true
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/admin/users`,
			method: 'POST',
			header: {
				'Content-Type': 'application/json'
			},
			data: {
				username: newUsername.value.trim(),
				password: newPassword.value,
				role: newRole.value
			}
		})
		if (res.statusCode === 201) {
			uni.showToast({ title: '用户创建成功', icon: 'success' })
			newUsername.value = ''
			newPassword.value = ''
			newRole.value = 'user'
			showCreateUser.value = false
			fetchUsers(false)
			emit('data-changed', 'users')
		} else {
			throw new Error(res.data?.error || '创建失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message, icon: 'none' })
	} finally {
		creatingUser.value = false
	}
}

async function deleteUser(id, username) {
	uni.showModal({
		title: '管理员操作',
		content: `确定要删除用户「${username}」及其所有数据？`,
		success: async (res) => {
			if (!res.confirm) return
			try {
				const resp = await request({
					url: `${authStore.API_BASE}/api/admin/users/${id}`,
					method: 'DELETE'
				})
				if (resp.statusCode === 200) {
					uni.showToast({ title: '已删除', icon: 'success' })
					fetchUsers(false)
					emit('data-changed', 'users')
				} else {
					throw new Error(resp.data?.error || '删除失败')
				}
			} catch (e) {
				uni.showToast({ title: e.message, icon: 'none' })
			}
		}
	})
}

function openResetPassword(id, username) {
	resetTargetId.value = id
	resetTargetName.value = username
	resetNewPassword.value = ''
	showResetPassword.value = true
}

async function resetPasswordAction() {
	if (!resetNewPassword.value || resetNewPassword.value.length < 6) {
		return uni.showToast({ title: '密码至少6个字符', icon: 'none' })
	}
	resettingPassword.value = true
	try {
		const res = await request({
			url: `${authStore.API_BASE}/api/admin/users/${resetTargetId.value}/reset-password`,
			method: 'PUT',
			header: { 'Content-Type': 'application/json' },
			data: { password: resetNewPassword.value }
		})
		if (res.statusCode === 200) {
			uni.showToast({ title: '密码已重置', icon: 'success' })
			showResetPassword.value = false
		} else {
			throw new Error(res.data?.error || '重置失败')
		}
	} catch (e) {
		uni.showToast({ title: e.message, icon: 'none' })
	} finally {
		resettingPassword.value = false
	}
}

// 模板中绑定的名称
const resetPassword = resetPasswordAction

function refresh(showLoader = false) {
	if (initialized.value) {
		fetchUsers(showLoader)
	}
}

function init() {
	if (!initialized.value) {
		initialized.value = true
		fetchUsers(true)
	}
}

defineExpose({ refresh, init })
</script>

<style scoped>
@import './shared.css';

.section-title {
	font-size: 28rpx;
	color: #e0e0e0;
	font-weight: 600;
	display: block;
}

.user-list-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 20rpx;
	padding-top: 24rpx;
}

.create-user-inline-btn {
	display: flex;
	align-items: center;
	padding: 8rpx 24rpx;
	font-size: 24rpx;
	background: linear-gradient(135deg, #6c5ce7, #a855f7);
	color: #fff;
	border-radius: 30rpx;
	margin: 0;
}
.create-user-inline-btn:active {
	opacity: 0.8;
}

.admin-item-card {
	background: rgba(255, 255, 255, 0.03);
	border: 1px solid rgba(255, 255, 255, 0.05);
	border-radius: 16rpx;
	margin-bottom: 20rpx;
	overflow: hidden;
	transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.admin-item-header {
	display: flex;
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

.user-avatar-small {
	width: 60rpx;
	height: 60rpx;
	border-radius: 50%;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-right: 20rpx;
}

.avatar-letter-s {
	font-size: 26rpx;
	color: #fff;
	font-weight: 700;
}

.role-badge {
	font-size: 20rpx;
	padding: 2rpx 12rpx;
	border-radius: 6rpx;
	margin-right: 12rpx;
}

.role-admin {
	background: rgba(108, 92, 231, 0.2);
	color: #a78bfa;
}

.role-user {
	background: rgba(255, 255, 255, 0.08);
	color: #b0b0c8;
}

.user-actions-row {
	display: flex;
	flex-direction: column;
	align-items: flex-end;
	gap: 12rpx;
	flex-shrink: 0;
}

.btn-link {
	font-size: 24rpx;
	color: #6c5ce7;
	display: flex;
	align-items: center;
	padding: 10rpx 0;
	transition: opacity 0.2s;
}

.btn-link:active {
	opacity: 0.6;
}

.btn-link.neutral {
	color: #a0a0b8;
}

.btn-link.danger {
	color: #e74c3c;
}

.btn-link.small-text {
	font-size: 22rpx;
}

/* 表单和弹层 */
.form-group {
	margin-bottom: 24rpx;
}

.form-label {
	font-size: 24rpx;
	color: #888;
	margin-bottom: 12rpx;
	display: block;
}

.role-selector {
	display: flex;
	gap: 16rpx;
}

.role-option {
	flex: 1;
	text-align: center;
	padding: 16rpx 0;
	font-size: 26rpx;
	color: #666;
	background: rgba(255, 255, 255, 0.04);
	border: 1px solid rgba(255, 255, 255, 0.08);
	border-radius: 12rpx;
	transition: all 0.3s;
}

.role-active {
	background: linear-gradient(135deg, rgba(108, 92, 231, 0.2), rgba(168, 85, 247, 0.2));
	border-color: rgba(108, 92, 231, 0.5);
	color: #fff;
	font-weight: 600;
}

.create-user-btn {
	width: 100%;
	height: 80rpx;
	display: flex;
	align-items: center;
	justify-content: center;
	margin-top: 8rpx;
}

/* 重置密码弹层 */
.modal-mask {
	position: fixed;
	top: 0;
	left: 0;
	right: 0;
	bottom: 0;
	background: rgba(0, 0, 0, 0.6);
	z-index: 1000;
	display: flex;
	align-items: center;
	justify-content: center;
	animation: fadeIn 0.2s ease;
}

.modal-content {
	width: 620rpx;
	background: #1a1a2e;
	border: 1px solid rgba(255, 255, 255, 0.1);
	border-radius: 20rpx;
	padding: 40rpx 30rpx;
	box-shadow: 0 20rpx 40rpx rgba(0, 0, 0, 0.5);
	animation: scaleIn 0.2s cubic-bezier(0.18, 0.89, 0.32, 1.28);
}

@keyframes scaleIn {
	from { opacity: 0; transform: scale(0.9); }
	to { opacity: 1; transform: scale(1); }
}

@keyframes fadeIn {
	from { opacity: 0; }
	to { opacity: 1; }
}

.modal-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 30rpx;
}

.modal-title {
	font-size: 32rpx;
	color: #fff;
	font-weight: 600;
}

.modal-close {
	padding: 10rpx;
	margin: -10rpx;
}

.reset-user-hint {
	margin-bottom: 20rpx;
}

.reset-user-hint text {
	font-size: 26rpx;
	color: #b0b0c8;
}
</style>
