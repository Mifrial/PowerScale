<script setup lang="ts">
import { computed } from 'vue';
import { useChatStore } from '@/modules/Messages/Chat/Store/chat';

const props = defineProps<{
  chatId: number | null;
}>();

const store = useChatStore();

const health = computed(() => (props.chatId === null ? undefined : store.readAckHealth[props.chatId]));
const visible = computed(() => health.value?.status === 'retrying');

function retry(): void {
  if (props.chatId !== null) store.retryReadAck(props.chatId);
}
</script>

<template>
  <v-alert v-if="visible" type="warning" variant="tonal" density="compact" class="ma-2">
    <div>{{ health?.lastError }}</div>
    <div class="text-caption mt-1">Не удалось сохранить отметку прочтения. Повтор будет выполнен автоматически.</div>
    <v-btn class="mt-2" size="small" variant="tonal" color="primary" @click="retry">Повторить</v-btn>
  </v-alert>
</template>
