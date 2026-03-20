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

	return {
		dataList,
		loading,
		page,
		hasMore,
		total,
		loadNextPage,
		reset
	}
}
