<template>
  <v-navigation-drawer location="right" permanent width="56" class="chat-bar">
    <div class="d-flex flex-column align-center py-3 ga-3">
      <template v-for="ch in sortedChats" :key="ch.id">
        <v-btn
          variant="text"
          size="small"
          icon
          class="flex-shrink-0"
          :title="ch.name"
          :aria-label="ch.name"
          :color="ch.id === store.activeChatId ? chatColor(ch.type) : 'default'"
          @click="handleChatClick(ch.id)"
        >
          <v-badge
            v-if="ch.unreadCount > 0"
            :content="ch.unreadCount"
            color="error"
            size="x-small"
            dot
          >
            <v-icon size="small">{{ chatIcon(ch.type) }}</v-icon>
          </v-badge>
          <v-icon v-else size="small">{{ chatIcon(ch.type) }}</v-icon>
        </v-btn>
        <v-divider v-if="ch.type === 'game' && hasGameDiscussionAfter(ch)" vertical class="my-1" />
      </template>
    </div>
  </v-navigation-drawer>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useChatStore } from '@/modules/Messages/Chat/Store/chat'
import type { ChatType } from '@/modules/Messages/Chat/Interface/types'
import { chatIcon, chatColor } from '@/modules/Messages/Chat/Config/chatType'

const emit = defineEmits<{
  (e: 'open-chat', chatId: number): void
}>()

const store = useChatStore()

const sortedChats = computed(() =>
  [...store.chats]
    .sort((a, b) => b.lastMessageAt.localeCompare(a.lastMessageAt))
    .slice(0, 10),
)

function handleChatClick(id: number) {
  store.openChat(id)
  emit('open-chat', id)
}

function hasGameDiscussionAfter(chat: { id: number; type: ChatType }): boolean {
  const idx = sortedChats.value.findIndex(c => c.id === chat.id)
  const next = sortedChats.value[idx + 1]
  return next?.type === 'game_discussion'
}
</script>

<style scoped>
.chat-bar {
  border-left: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}
</style>
