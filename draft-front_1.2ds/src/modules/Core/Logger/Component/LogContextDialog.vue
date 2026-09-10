<script setup lang="ts">
import { computed } from 'vue';
import type { LogEntry } from '@/modules/Core/Logger/Dto/LogEntry';

const props = defineProps<{
  modelValue: boolean;
  entry: LogEntry | null;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

const contextJson = computed(() => JSON.stringify(props.entry?.context ?? null, null, 2));

function close(): void {
  emit('update:modelValue', false);
}
</script>

<template>
  <v-dialog :model-value="props.modelValue" max-width="640" @update:model-value="emit('update:modelValue', $event)">
    <v-card v-if="props.entry">
      <v-card-title>Запись {{ props.entry.id }}</v-card-title>
      <v-card-text>
        <div class="text-body-2 mb-2">{{ props.entry.message }}</div>
        <div class="text-caption text-medium-emphasis mb-4">
          {{ props.entry.level }} · {{ props.entry.source ?? '—' }} · {{ props.entry.errorCode ?? '—' }}
        </div>
        <pre class="text-caption">{{ contextJson }}</pre>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="tonal" @click="close">Закрыть</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
