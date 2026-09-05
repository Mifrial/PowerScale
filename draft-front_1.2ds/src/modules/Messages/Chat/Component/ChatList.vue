<script setup lang="ts">
import { computed } from 'vue';
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import ChatListItem from '@/modules/Messages/Chat/Component/ChatListItem.vue';
import { useCurrentUser } from '@/modules/Core/User/init';
import { useChatUsers } from '@/modules/Messages/Chat/Composables/useChatUsers';

const props = defineProps<{
  chats: Chat[];
  query: string;
  activeChatId: number | null;
}>();

const emit = defineEmits<{
  'select-chat': [id: number];
  'open-profile': [userId: number];
}>();

const { userId } = useCurrentUser();
const chatUsers = useChatUsers();

function titleOf(chat: Chat): string {
  if (chat.type !== 'private') return chat.name;
  const other = chat.members?.find((member) => member.userId !== userId.value);
  if (!other) return chat.name;

  return chatUsers.displayName(chatUsers.getUser(other.userId)) || chat.name;
}

const visibleChats = computed(() => {
  const needle = props.query.trim().toLowerCase();
  if (!needle) return props.chats;

  return props.chats.filter((chat) => titleOf(chat).toLowerCase().includes(needle));
});
</script>

<template>
  <div class="chat-list">
    <ChatListItem
      v-for="c in visibleChats"
      :key="c.id"
      :chat="c"
      :active-chat-id="activeChatId"
      @select-chat="emit('select-chat', $event)"
      @open-profile="emit('open-profile', $event)"
    />
    <div v-if="!visibleChats.length" class="pa-3 text-center text-medium-emphasis text-caption">Нет чатов</div>
  </div>
</template>

<style scoped>
.chat-list {
  width: 100%;
  flex: 1;
  overflow-y: auto;
  overscroll-behavior: contain;
}
</style>
