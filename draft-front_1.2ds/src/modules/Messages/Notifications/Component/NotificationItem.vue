<script setup lang="ts">
import type { Notification } from '@/modules/Messages/Notifications/Dto/Notification'
import { DateTime } from '@/modules/Core/Engine/Value/DateTime'

defineProps<{
  notification: Notification
  iconSize?: number | string
  actionSize?: string
}>()

defineEmits<{
  action: [payload: { id: number; key: string }]
}>()
</script>

<template>
  <div class="notification-item" :class="{ unread: !notification.read }">
    <v-icon :icon="notification.icon" :size="iconSize" class="notification-icon" :color="notification.read ? 'grey' : 'primary'" />
    <div class="notification-body">
      <div class="d-flex align-center justify-space-between">
        <span class="notification-title">{{ notification.title }}</span>
        <span class="notification-time">{{ DateTime.formatTime(notification.createdAt) }}</span>
      </div>
      <div class="notification-preview">{{ notification.preview }}</div>
      <div v-if="notification.actions.length" class="notification-actions">
        <v-btn
          v-for="act in notification.actions"
          :key="act.key"
          variant="text"
          :color="act.color === 'error' ? 'error' : 'primary'"
          :size="actionSize"
          class="action-btn"
          @click="$emit('action', { id: notification.id, key: act.key })"
        >
          {{ act.label }}
        </v-btn>
      </div>
    </div>
  </div>
</template>

<style scoped>
.notification-item {
  display: flex;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 8px;
  border: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
  margin-bottom: 6px;
  background: rgb(var(--v-theme-surface));
}
.notification-item.unread {
  background: rgb(var(--v-theme-primaryLight));
  border-color: rgb(var(--v-theme-primary));
}

.notification-icon {
  flex-shrink: 0;
  margin-top: 2px;
}

.notification-body {
  flex: 1;
  min-width: 0;
}

.notification-title {
  font-size: 14px;
  font-weight: 500;
}

.notification-time {
  font-size: 12px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  white-space: nowrap;
  flex-shrink: 0;
  margin-left: 8px;
}

.notification-preview {
  font-size: 13px;
  color: rgba(var(--v-theme-on-surface), var(--v-text-medium-opacity));
  margin-top: 2px;
}

.notification-actions {
  display: flex;
  gap: 6px;
  margin-top: 8px;
}

.action-btn.v-btn--variant-text {
  border: thin solid currentColor;
  transition: background 0.15s ease, border-color 0.15s ease;
}
.action-btn.v-btn--variant-text:hover {
  background: rgb(var(--v-theme-primaryLight));
}
</style>
