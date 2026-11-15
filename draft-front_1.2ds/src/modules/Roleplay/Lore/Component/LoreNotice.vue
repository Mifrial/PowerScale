<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    modelValue: boolean;
    text: string;
    color?: 'success' | 'error' | 'info' | 'warning';
    timeout?: number;
  }>(),
  {
    color: 'success',
    timeout: 4000,
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: boolean];
}>();

const icon = computed(() => {
  const icons = {
    success: 'mdi-check-circle-outline',
    error: 'mdi-alert-circle-outline',
    info: 'mdi-information-outline',
    warning: 'mdi-alert-outline',
  } as const;

  return icons[props.color];
});
</script>

<template>
  <v-snackbar
    :model-value="props.modelValue"
    :color="props.color"
    :timeout="props.timeout"
    location="top"
    @update:model-value="emit('update:modelValue', $event)"
  >
    <div class="d-flex align-center ga-2">
      <v-icon :icon="icon" size="20" />
      <span class="text-body-2">{{ props.text }}</span>
    </div>
    <template #actions>
      <v-btn
        icon="mdi-close"
        variant="text"
        size="small"
        aria-label="Закрыть"
        @click="emit('update:modelValue', false)"
      />
    </template>
  </v-snackbar>
</template>
