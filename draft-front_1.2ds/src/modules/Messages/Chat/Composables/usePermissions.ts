import { isAdmin, useCurrentUser } from '@/modules/Core/User/init';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import type { ChatPermission } from '@/modules/Messages/Chat/Enum/ChatPermission';

export function usePermissions() {
  const { userId, isGuest, currentUser } = useCurrentUser();

  function canInChat(chat: Pick<Chat, 'members' | 'visibility' | 'type'> | null, permission: ChatPermission): boolean {
    if (!chat || !userId.value) return false;
    if (isAdmin(currentUser.value)) return true;

    const me = chat.members?.find((m) => m.userId === userId.value);

    if (!me) {
      if (chat.visibility === 'public') {
        if (permission === 'chat.read') return true;
        if (permission === 'chat.write') return !isGuest.value;
      }

      return false;
    }

    switch (me.status) {
      case 'creator':
        return true;
      case 'admin':
        if (permission === 'chat.manage') return false;

        return true;
      case 'member':
        return permission === 'chat.read' || permission === 'chat.write';
      default:
        return false;
    }
  }

  return { isGuest, canInChat };
}
