import { computed, watch } from 'vue';
import type { ComputedRef } from 'vue';
import { getChatTypes } from '@/modules/Messages/Chat/init';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';

/**
 * Опции видимости для хоста чата: участники чата, без провайдера.
 * `allowVisibility` — флаг типа (`supportsVisibility`). Host `private`/`group` не отдают
 * роли в меню (`roleOptions: []`); Game по-прежнему инъектирует `IChatType.roles`.
 */
export function useChatVisibilityOptions(chat: ComputedRef<Pick<Chat, 'type' | 'members'> | null>) {
  const chatUsers = useChatUsers();

  const chatType = computed(() =>
    chat.value ? getChatTypes().find((candidate) => candidate.type === chat.value?.type) : undefined,
  );

  const allowVisibility = computed(() => chatType.value?.supportsVisibility ?? false);

  const roleOptions = computed(() => {
    const chatTypeName = chat.value?.type;
    if (chatTypeName === 'private' || chatTypeName === 'group') {
      return [];
    }

    return (chatType.value?.roles ?? []).map((role) => ({ code: role.code, label: role.label }));
  });

  watch(
    () => chat.value?.members.map((member) => member.userId).join(',') ?? '',
    () => {
      const members = chat.value?.members ?? [];
      if (members.length) void chatUsers.ensureUsers(members.map((member) => member.userId));
    },
    { immediate: true },
  );

  const userOptions = computed(() => {
    const members = chat.value?.members ?? [];

    return members.map((member) => ({
      userId: member.userId,
      name: chatUsers.displayName(chatUsers.getUser(member.userId)) || `#${member.userId}`,
    }));
  });

  return { allowVisibility, roleOptions, userOptions };
}
