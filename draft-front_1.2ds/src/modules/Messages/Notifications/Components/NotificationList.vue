<template>
  <div v-if="items.length" class="notification-list">
    <NotificationItem
      v-for="n in items"
      :key="n.id"
      :notification="n"
      :icon-size="iconSize"
      :action-size="actionSize"
      @action="$emit('action', $event)"
    />
  </div>
  <div v-else class="text-caption text-medium-emphasis">
    <slot name="empty" />
  </div>
</template>

<script setup lang="ts">
import type { Notification } from '@/modules/Messages/Notifications/Interface/types'
import NotificationItem from './NotificationItem.vue'

defineProps<{
  items: Notification[]
  iconSize?: number | string
  actionSize?: string
}>()

defineEmits<{
  action: [payload: { id: number; key: string }]
}>()
</script>

<style scoped>
.notification-list {
  display: flex;
  flex-direction: column;
}
</style>
