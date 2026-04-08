/**
 * 通用工具函数
 * 从多个页面提取的公共逻辑，消除重复代码
 */
import { useAuthStore } from '../stores/authStore'

/**
 * 格式化时长为 M:SS（如 1:05）
 * @param {number} seconds - 秒数
 */
export function formatDuration(seconds) {
	if (!seconds || seconds < 0) return '0:00'
	const m = Math.floor(seconds / 60)
	const s = Math.floor(seconds % 60)
	return `${m}:${s.toString().padStart(2, '0')}`
}

export const formatTime = formatDuration

/**
 * 格式化日期为 "X月X日"
 * @param {string} dateStr - ISO 日期字符串
 */
export function formatDateSimple(dateStr) {
	if (!dateStr) return ''
	const d = new Date(dateStr)
	return `${d.getMonth() + 1}月${d.getDate()}日`
}

/**
 * 根据用户名生成头像背景色
 * @param {string} username - 用户名
 */
const avatarColors = ['#5b52f6', '#a855f7', '#ec4899', '#f43f5e', '#ef4444', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#6366f1']
export function getUserColor(username) {
	if (!username) return avatarColors[0]
	let hash = 0
	for (let i = 0; i < username.length; i++) hash = username.charCodeAt(i) + ((hash << 5) - hash)
	return avatarColors[Math.abs(hash) % avatarColors.length]
}

/**
 * 生成视频缩略图 URL（本地视频拼接后端地址，添加 #t=0.5 用于首帧）
 * @param {Object} video - 视频对象 { url, type }
 */
export function getVideoThumbUrl(video) {
	if (!video || !video.url) return ''
	const authStore = useAuthStore()
	let url = video.type === 'local' ? `${authStore.API_BASE}${video.url}` : video.url
	if (!url.includes('#t=')) url += '#t=0.5'
	return url
}

/**
 * 根据用户角色隐藏/显示 tabBar 项（H5 DOM 操作）
 * tabBar 顺序: 0=首页, 1=管理后台, 2=我的
 * @param {boolean} isAdmin - 是否管理员
 */
export function updateTabBarForRole(isAdmin) {
	// #ifdef H5
	const apply = () => {
		const items = document.querySelectorAll('.uni-tabbar__item')
		if (!items || items.length < 3) return false
		if (isAdmin) {
			items[0].style.display = 'none'
			items[1].style.display = ''
			items[2].style.display = ''
		} else {
			items[0].style.display = ''
			items[1].style.display = 'none'
			items[2].style.display = ''
		}
		return true
	}
	// 尝试立即执行，如果 DOM 未就绪则延迟重试
	if (!apply()) {
		setTimeout(apply, 200)
	}
	// #endif
}
