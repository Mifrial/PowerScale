import { defineStore } from 'pinia'
import { ref, computed, watch } from 'vue'
import type { Notification } from '@/modules/Messages/Notifications/Dto/Notification'
import type { NotifFilter } from '@/modules/Messages/Notifications/Enum/NotifFilter'
import { getNotificationApi } from '@/modules/Messages/Notifications/init'

export const useNotificationStore = defineStore('notifications', () => {
  const items = ref<Notification[]>([])
  const total = ref(0)
  const unreadCount = ref(0)
  const loading = ref(false)
  const filter = ref<NotifFilter>('all')
  const searchQuery = ref('')
  const page = ref(1)
  const pageSize = ref(6)

  // Серверная загрузка данных
  async function fetchData() {
    loading.value = true
    try {
      const offset = (page.value - 1) * pageSize.value
      const result = await getNotificationApi().fetchPage({
        filter: filter.value,
        search: searchQuery.value || undefined,
        offset,
        limit: pageSize.value,
      })
      items.value = result.items
      total.value = result.total
      unreadCount.value = result.unreadCount
    } finally {
      loading.value = false
    }
  }

  // При смене фильтра или поиска — сбрасываем страницу и перезапрашиваем
  watch([filter, searchQuery], () => {
    page.value = 1
    fetchData()
  })

  // При смене страницы — перезапрашиваем
  watch(page, () => {
    fetchData()
  })

  const totalPages = computed(() =>
    Math.max(1, Math.ceil(total.value / pageSize.value))
  )

  async function markAsRead(payload: { id: number; key: string }) {
    await getNotificationApi().markAsRead(payload.id)
    const n = items.value.find(x => x.id === payload.id)
    if (n) {
      n.read = true
      unreadCount.value = Math.max(0, unreadCount.value - 1)
    }
  }

  async function markAllAsRead() {
    await getNotificationApi().markAllAsRead()
    items.value.forEach(n => { n.read = true })
    unreadCount.value = 0
  }

  function setPage(p: number) {
    page.value = p
  }

  function setFilter(f: NotifFilter) {
    filter.value = f
  }

  return {
    items, total, unreadCount, loading, filter, searchQuery,
    totalPages, page, pageSize,
    fetchData, markAsRead, markAllAsRead, setPage, setFilter,
  }
})
