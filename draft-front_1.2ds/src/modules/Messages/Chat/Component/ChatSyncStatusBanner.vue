<script setup lang="ts">
import { computed } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';

const store = useChatStore();

const visible = computed(() => store.syncHealth.status === 'retrying');
</script>

<template>
  <v-alert v-if="visible" type="warning" variant="tonal" density="compact" class="ma-2">
    <div>{{ store.syncHealth.lastError }}</div>
    <div class="text-caption mt-1">Повтор будет выполнен автоматически.</div>
    <v-btn class="mt-2" size="small" variant="tonal" color="primary" @click="store.retrySync()">Повторить</v-btn>
  </v-alert>
</template>
