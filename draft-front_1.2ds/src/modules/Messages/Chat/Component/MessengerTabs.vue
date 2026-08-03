<script setup lang="ts">
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';

const store = useChatStore();
</script>

<template>
  <div class="chat-tabs-scroll">
    <v-tabs v-model="store.selectedTab" density="compact" color="primary" hide-slider>
      <v-tab v-for="tab in store.tabs" :key="tab.key" :value="tab.key">
        <v-icon :icon="tab.icon" size="16" class="mr-1" />
        {{ tab.label }}
        <v-badge
          v-if="store.tabUnread(tab.key)"
          inline
          :content="store.tabUnread(tab.key)"
          color="error"
          size="x-small"
          class="ml-1"
        />
      </v-tab>
    </v-tabs>
  </div>
</template>

<style scoped>
.chat-tabs-scroll {
  width: 100%;
  overflow: hidden;
  white-space: nowrap;
}
.chat-tabs-scroll :deep(.v-tab) {
  min-width: auto;
  font-size: 13px;
}
</style>
