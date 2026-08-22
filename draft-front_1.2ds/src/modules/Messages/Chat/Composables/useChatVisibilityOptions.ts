import { computed } from 'vue';
import type { ComputedRef } from 'vue';
import { getChatTypes } from '@/modules/Messages/Chat/init';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';

/**
 * Опции видимости сообщений для хоста чата: выводятся из данных самого чата (роли типа +
 * участники), без провайдера. `allowVisibility` — по флагу типа (`supportsVisibility`),
 * роли/права инъектирует домен через `IChatType.roles`.
 */
export function useChatVisibilityOptions(chat: ComputedRef<Pick<Chat, 'type' | 'members'> | null>) {
  const chatUsers = useChatUsers();

  const chatType = computed(() =>
    chat.value ? getChatTypes().find((candidate) => candidate.type === chat.value?.type) : undefined,
  );

  const allowVisibility = computed(() => chatType.value?.supportsVisibility ?? false);

  const roleOptions = computed(() =>
    (chatType.value?.roles ?? []).map((role) => ({ code: role.code, label: role.label })),
  );

  const userOptions = computed(() => {
    const members = chat.value?.members ?? [];
    if (members.length) void chatUsers.ensureUsers(members.map((member) => member.userId));

    return members.map((member) => ({
      userId: member.userId,
      name: chatUsers.displayName(chatUsers.getUser(member.userId)) || `#${member.userId}`,
    }));
  });

  return { allowVisibility, roleOptions, userOptions };
}
