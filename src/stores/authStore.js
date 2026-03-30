import { defineStore } from 'pinia'
import { ref, computed } from 'vue'

// 使用相对路径，自适应当前域名的 http/https 和端口。Nginx 反向代理会处理 /api 请求
const API_BASE = ''

export const useAuthStore = defineStore('auth', () => {
	const token = ref('')
	const user = ref(null)

	const isLoggedIn = computed(() => !!token.value)
	const isAdmin = computed(() => user.value?.role === 'admin')
	const username = computed(() => user.value?.username || '')

	function init() {
		const savedToken = uni.getStorageSync('token')
		const savedUser = uni.getStorageSync('user')
		if (savedToken) {
			token.value = savedToken
		}
		if (savedUser) {
			try {
				user.value = typeof savedUser === 'string' ? JSON.parse(savedUser) : savedUser
			} catch (e) {
				user.value = null
			}
		}
	}

	async function login(username, password) {
		const res = await uni.request({
			url: `${API_BASE}/api/auth/login`,
			method: 'POST',
			header: { 'Content-Type': 'application/json' },
			data: { username, password }
		})
		if (res.statusCode !== 200) {
			throw new Error(res.data?.error || '登录失败')
		}
		token.value = res.data.token
		user.value = res.data.user
		uni.setStorageSync('token', res.data.token)
		uni.setStorageSync('user', JSON.stringify(res.data.user))
		return res.data
	}

	function logout() {
		token.value = ''
		user.value = null
		uni.removeStorageSync('token')
		uni.removeStorageSync('user')
		uni.reLaunch({ url: '/pages/login/login' })
	}

	function getAuthHeader() {
		return token.value ? { Authorization: `Bearer ${token.value}` } : {}
	}

	return {
		token, user, isLoggedIn, isAdmin, username,
		init, login, logout, getAuthHeader,
		API_BASE
	}
})
