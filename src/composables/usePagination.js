import { ref } from 'vue'

/**
 * 通用分页 Composable
 * @param {Function} fetchFn - 必须返回 Promise<{ data: Array, total: Number }>
 * @param {Object} options - { limit: Number }
 */
export function usePagination(fetchFn, options = {}) {
	const dataList = ref([])
	const loading = ref(false)
	const page = ref(1)
	const hasMore = ref(true)
	const total = ref(0)
	const limit = options.limit || 20

	async function loadNextPage(extraParams = {}) {
		if (loading.value || !hasMore.value) return
		
		loading.value = true
		try {
			const res = await fetchFn({
				page: page.value,
				limit,
				...extraParams
			})
			
			if (res && Array.isArray(res.data)) {
				// 只有第一页时覆盖，否则追加
				if (page.value === 1) {
					dataList.value = res.data
				} else {
					dataList.value = [...dataList.value, ...res.data]
				}
				
				total.value = res.total || 0
				page.value++
				hasMore.value = dataList.value.length < total.value
			} else {
				hasMore.value = false
			}
		} catch (e) {
			console.error('Pagination fetch error:', e)
			hasMore.value = false
		} finally {
			loading.value = false
		}
	}

	function reset() {
		dataList.value = []
		page.value = 1
		hasMore.value = true
		total.value = 0
		loading.value = false
	}

	/**
	 * 静默刷新：后台重新拉取第一页数据并合并，不清空列表、不显示 loading
	 */
	async function silentRefresh(extraParams = {}) {
		try {
			const res = await fetchFn({
				page: 1,
				limit,
				...extraParams
			})
			if (res && Array.isArray(res.data)) {
				// 保留已加载分页的数量，只替换第一页的数据
				const oldPageCount = page.value - 1 // 已加载的页数
				if (oldPageCount <= 1) {
					// 只加载了第一页，直接替换
					dataList.value = res.data
				} else {
					// 多页情况：替换前 limit 条，保留后续分页
					dataList.value = [...res.data, ...dataList.value.slice(limit)]
				}
				total.value = res.total || 0
				hasMore.value = dataList.value.length < total.value
			}
		} catch (e) {
			console.error('Silent refresh error:', e)
		}
	}

	return {
		dataList,
		loading,
		page,
		hasMore,
		total,
		loadNextPage,
		reset,
		silentRefresh
	}
}
