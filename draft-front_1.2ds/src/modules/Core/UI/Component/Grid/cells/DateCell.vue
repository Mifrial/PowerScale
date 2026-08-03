<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ value: unknown }>();

const formatted = computed(() => {
  if (!props.value) return '—';
  const d = new Date(props.value as string | number);

  return isNaN(d.getTime())
    ? String(props.value)
    : d.toLocaleDateString('ru-RU', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
      });
});
</script>

<template>
  <span class="text-caption text-medium-emphasis">{{ formatted }}</span>
</template>
