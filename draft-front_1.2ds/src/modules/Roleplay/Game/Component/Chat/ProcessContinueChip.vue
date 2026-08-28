<script setup lang="ts">
import { computed } from 'vue';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
  context?: { openEntity?: (key: string) => void };
}>();

const entityKey = computed(() => {
  if (props.segment.params[0] !== 'continue') return null;
  const entityType = props.segment.params[1];
  const entityId = props.segment.params[2];
  if (!entityType || !entityId) return null;

  return `${entityType}:${entityId}`;
});

function open(): void {
  if (entityKey.value) props.context?.openEntity?.(`process:continue:${entityKey.value}`);
}
</script>

<template>
  <span
    class="process-continue-chip"
    role="button"
    tabindex="0"
    @click="open"
    @keydown.enter.prevent="open"
    @keydown.space.prevent="open"
  >
    продолжить
  </span>
</template>

<style scoped>
.process-continue-chip {
  color: rgb(var(--v-theme-primary));
  cursor: pointer;
  font-weight: 500;
  text-decoration: underline;
}
</style>
