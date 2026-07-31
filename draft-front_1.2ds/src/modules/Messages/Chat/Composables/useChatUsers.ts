import { computed } from 'vue'
import { useUserStore } from '@/modules/Core/User/Store/users'
import type { User } from '@/modules/Core/User/Interface/types'

export function useChatUsers() {
  const userStore = useUserStore()

  const userMap = computed<Map<number, User>>(() => {
    const map = new Map<number, User>()
    for (const u of userStore.users) {
      map.set(u.id, u)
    }
    return map
  })

  function getUser(id: number): User | undefined {
    return userMap.value.get(id)
  }

  async function ensureUsers(ids: number[]): Promise<void> {
    const missing = ids.filter(id => !userMap.value.has(id))
    if (!missing.length) return
    if (!userStore.users.length) {
      await userStore.fetchUsers()
      return
    }
    const fetched = await userStore.getUsersByIds(missing)
    for (const u of fetched) {
      userStore.users.push(u)
    }
  }

  function initials(u: User | undefined): string {
    if (!u) return '?'
    const first = u.name?.[0] || ''
    const second = u.surname?.[0] || ''
    return (first + second).toUpperCase() || '?'
  }

  function displayName(u: User | undefined): string {
    if (!u) return ''
    const parts = [u.name, u.surname].filter(Boolean)
    return parts.join(' ') || u.login
  }

  return { userMap, getUser, ensureUsers, initials, displayName }
}
