<script setup lang="ts">
import { computed } from 'vue';
import { NodeViewWrapper } from '@tiptap/vue-3';
import type { NodeViewProps } from '@tiptap/vue-3';
import { getInlineRenderer } from '@/modules/Messages/Inline/init';
import type { InlineSegment } from '@/modules/Messages/Inline/Dto/InlineSegment';

const props = defineProps<NodeViewProps>();

type TokenSegment = Extract<InlineSegment, { kind: 'token' }>;

const type = computed(() => String(props.node.attrs.type ?? ''));
const value = computed(() => String(props.node.attrs.value ?? ''));
const label = computed(() => String(props.node.attrs.label ?? '') || value.value);
const renderer = computed(() => getInlineRenderer(type.value));
const segment = computed<TokenSegment>(() => ({
  kind: 'token',
  type: type.value,
  params: label.value === value.value ? [value.value] : [value.value, label.value],
}));
const context = computed<Record<string, unknown>>(() => {
  const storage = props.editor.storage as unknown as {
    inlineEntity?: { context?: Record<string, unknown> };
  };

  return storage.inlineEntity?.context ?? {};
});
</script>

<template>
  <NodeViewWrapper as="span" class="inline-entity" contenteditable="false">
    <component :is="renderer.component" v-if="renderer" :segment="segment" :context="context" />
    <span v-else class="inline-entity-fallback">{{ label }}</span>
  </NodeViewWrapper>
</template>

<style scoped>
.inline-entity-fallback {
  color: rgba(var(--v-theme-on-surface), 0.72);
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}
</style>
