<script setup lang="ts">
import { computed } from 'vue';
import { useNotificationStore } from '@/modules/Messages/Notifications/Store/notifications';
import AppBreadcrumbs from '@/shell/AppBreadcrumbs.vue';

defineEmits<{ 'toggle-sidebar': []; 'toggle-notifications': [] }>();

const notificationStore = useNotificationStore();

const unreadCount = computed(() => notificationStore.unreadCount);
</script>

<template>
  <v-app-bar flat class="border-b">
    <v-app-bar-nav-icon @click="$emit('toggle-sidebar')" />

    <AppBreadcrumbs />

    <v-spacer />

    <v-btn icon variant="text" aria-label="Уведомления" @click="$emit('toggle-notifications')">
      <v-badge :model-value="unreadCount > 0" :content="unreadCount" color="error" size="small">
        <v-icon>mdi-bell-outline</v-icon>
      </v-badge>
    </v-btn>
  </v-app-bar>
</template>
