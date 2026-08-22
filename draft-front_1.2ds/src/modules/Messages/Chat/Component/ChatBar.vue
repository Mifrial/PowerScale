<script setup lang="ts">
import { computed } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';
import { getChatIcon, getChatColor } from '@/modules/Messages/Chat/init';

const emit = defineEmits<{
  (e: 'open-chat', chatId: number): void;
}>();

const store = useChatStore();

const recentChats = computed(() => store.sortedChats.slice(0, 10));

function handleChatClick(id: number) {
  store.openChat(id);
  emit('open-chat', id);
}
</script>

<template>
  <v-navigation-drawer location="right" permanent width="56" class="chat-bar">
    <div class="d-flex flex-column align-center py-3 ga-3">
      <template v-for="ch in recentChats" :key="ch.id">
        <v-btn
          variant="text"
          size="small"
          icon
          class="flex-shrink-0"
          :title="ch.name"
          :aria-label="ch.name"
          :color="ch.id === store.activeChatId ? getChatColor(ch.type) : 'default'"
          @click="handleChatClick(ch.id)"
        >
          <v-badge v-if="ch.unreadCount > 0" :content="ch.unreadCount" color="error" size="x-small" dot>
            <v-icon size="small">{{ getChatIcon(ch.type) }}</v-icon>
          </v-badge>
          <v-icon v-else size="small">{{ getChatIcon(ch.type) }}</v-icon>
        </v-btn>
      </template>
    </div>
  </v-navigation-drawer>
</template>

<style scoped>
.chat-bar {
  border-left: 1px solid rgb(var(--v-theme-divider));
}
</style>
