<script setup lang="ts">
import type { Chat } from '@/modules/Messages/Chat/Dto/Chat';
import ChatListItem from '@/modules/Messages/Chat/Component/ChatListItem.vue';

defineProps<{
  chats: Chat[];
  activeChatId: number | null;
}>();

const emit = defineEmits<{
  'select-chat': [id: number];
  'open-profile': [userId: number];
}>();
</script>

<template>
  <div class="chat-list">
    <ChatListItem
      v-for="c in chats"
      :key="c.id"
      :chat="c"
      :active-chat-id="activeChatId"
      @select-chat="emit('select-chat', $event)"
      @open-profile="emit('open-profile', $event)"
    />
    <div v-if="!chats.length" class="pa-3 text-center text-medium-emphasis text-caption">Нет чатов</div>
  </div>
</template>

<style scoped>
.chat-list {
  width: 280px;
  flex-shrink: 0;
  overflow-y: auto;
  overscroll-behavior: contain;
  border-right: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
