<template>
  <div class="chat-tabs-scroll">
    <button
      v-for="tab in store.tabs"
      :key="tab.key"
      class="chat-type-tab"
      :class="{ active: store.selectedTab === tab.key }"
      @click="store.selectedTab = tab.key"
    >
      <v-icon :icon="tab.icon" size="16" class="mr-1" />
      {{ tab.label }}
      <v-badge v-if="store.tabUnread(tab.key)" inline :content="store.tabUnread(tab.key)" color="error" size="x-small" class="ml-1" />
    </button>
  </div>
</template>

<script setup lang="ts">
import { useChatStore } from '@/modules/Messages/Chat/Store/chat'

const store = useChatStore()
</script>

<style scoped>
.chat-tabs-scroll {
  display: flex;
  align-items: center;
  gap: 4px;
  overflow-x: auto;
  white-space: nowrap;
  scrollbar-width: thin;
}

.chat-type-tab {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 6px 12px;
  border-radius: 6px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), 0.7);
  transition: background 0.12s ease;
  white-space: nowrap;
}
.chat-type-tab:hover {
  background: rgb(var(--v-theme-primaryLight));
}
.chat-type-tab.active {
  background: rgb(var(--v-theme-primaryLight));
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
}
</style>
