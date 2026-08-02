<template>
  <div class="messenger-page">
    <Messenger>
      <template #tabs>
        <div class="messenger-header">
          <MessengerTabs />
        </div>
      </template>
    </Messenger>
  </div>
</template>

<script setup lang="ts">
import Messenger from '@/modules/Messages/Chat/Component/Messenger.vue'
import MessengerTabs from '@/modules/Messages/Chat/Component/MessengerTabs.vue'
</script>

<style scoped>
.messenger-page {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
  max-height: calc(100vh - var(--v-layout-top) - var(--v-layout-bottom, 0px));
  background: rgb(var(--v-theme-surface));
}

.messenger-header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}
</style>
