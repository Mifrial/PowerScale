import { computed } from 'vue'
import { useAuthStore } from '@/modules/Core/Auth/Store/auth'
import { useUserStore } from '@/modules/Core/User/Store/users'
import { isAdmin } from '@/modules/Core/User/init'
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat'

export type ChatPermission = 'chat.read' | 'chat.write' | 'chat.kick' | 'chat.manage'

export function usePermissions() {
  const auth = useAuthStore()
  const currentUser = useUserStore()

  const isGuest = computed(() => auth.isGuest)

  function canInChat(chat: Pick<Chat, 'members' | 'visibility' | 'type'> | null, permission: ChatPermission): boolean {
    if (!chat || !auth.userId) return false
    if (isAdmin(currentUser.currentUser)) return true

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

  return { isGuest, canInChat }
}
