/**
 * 封装 uni.request，自动处理 401 token 过期跳转登录
 * 用法与 uni.request 完全一致，返回 Promise<response>
 */
import { useAuthStore } from '../stores/authStore'

export function request(options) {
	const authStore = useAuthStore()
	// 自动注入 Authorization header
	const header = {
		...authStore.getAuthHeader(),
		...options.header
	}

	return uni.request({
		...options,
		header
	}).then(res => {
		// 检测 401 未授权（排除登录接口自身）
		if (res.statusCode === 401 && !options.url?.includes('/api/auth/login')) {
			authStore.handleUnauthorized()
			// 抛出错误阻止后续业务逻辑处理空数据
			throw new Error('登录已过期')
		}
		return res
	})
}
