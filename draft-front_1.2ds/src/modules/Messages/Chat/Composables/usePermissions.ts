import { computed } from 'vue'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useUserStore } from '@/modules/Core/User/Store/users'
import type { Chat } from '@/modules/Messages/Chat/Interface/types'

export type ChatPermission = 'chat.read' | 'chat.write' | 'chat.kick' | 'chat.manage'

export function usePermissions() {
  const auth = useAuthStore()
  const currentUser = useUserStore()

  const groups = computed(() => currentUser.currentUser?.groups ?? [])
  const isGuest = computed(() => auth.isGuest)

  function canInChat(chat: Pick<Chat, 'members' | 'visibility' | 'type'> | null, permission: ChatPermission): boolean {
    if (!chat || !auth.userId) return false
    if (groups.value.includes('Администратор')) return true

    const me = chat.members?.find(m => m.userId === auth.userId)

    if (!me) {
      if (chat.visibility === 'public') {
        if (permission === 'chat.read') return true
        if (permission === 'chat.write') return !auth.isGuest
      }
      return false
    }

    switch (me.status) {
      case 'creator':
        return true
      case 'admin':
        if (permission === 'chat.manage') return false
        return true
      case 'member':
        return permission === 'chat.read' || permission === 'chat.write'
      default:
        return false
    }
  }

  function canKick(chat: Pick<Chat, 'members' | 'visibility' | 'type'> | null, targetUserId: number): boolean {
    if (!chat || !auth.userId) return false
    if (!canInChat(chat, 'chat.kick')) return false
    const target = chat.members?.find(m => m.userId === targetUserId)
    if (!target) return false
    if (target.status === 'creator') return false
    if (meStatus(chat) === 'admin' && target.status === 'admin') return false
    return true
  }

  function meStatus(chat: Pick<Chat, 'members'> | null): string | null {
    if (!chat || !auth.userId) return null
    return chat.members?.find(m => m.userId === auth.userId)?.status ?? null
  }

  return { groups, isGuest, canInChat, canKick, meStatus }
}
