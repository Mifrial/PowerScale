<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
  context?: { openEntity?: (key: string) => void };
}>();

const router = useRouter();

const entityId = computed(() => Number(props.segment.params[0]));
const label = computed(() => {
  const rest = props.segment.params.slice(1).join(',').trim();

  return rest || props.segment.params[0] || '';
});
const entityKey = computed(() => {
  if (!Number.isInteger(entityId.value) || entityId.value <= 0) return null;
  if (props.segment.type === 'npc') return `npc:${entityId.value}`;

  return `character:${entityId.value}`;
});

function open(): void {
  if (!entityKey.value) return;
  if (props.context?.openEntity) {
    props.context.openEntity(entityKey.value);

    return;
  }
  if (props.segment.type === 'character') {
    void router.push({ name: 'CharacterDetail', params: { id: String(entityId.value) } });
  }
}
</script>

<template>
  <span
    class="game-entity-chip"
    role="button"
    tabindex="0"
    @click="open"
    @keydown.enter.prevent="open"
    @keydown.space.prevent="open"
    >{{ label }}</span
  >
</template>

<style scoped>
.game-entity-chip {
  color: rgb(var(--v-theme-primary));
  font-weight: 500;
  cursor: pointer;
  text-decoration: underline;
  text-underline-offset: 2px;
}
</style>
