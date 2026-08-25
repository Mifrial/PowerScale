<script setup lang="ts">
import { computed } from 'vue';
import { getInlineRenderer } from '@/modules/Messages/Chat/init';
import { inlineContentService } from '@/modules/Messages/Chat/Service/Instance/inlineContentService';

const props = defineProps<{
  summary: string;
  expanded: boolean;
  rendererContext?: Record<string, unknown>;
}>();

const emit = defineEmits<{
  toggle: [];
}>();

const segments = computed(() => inlineContentService.parse(props.summary));
const inlineRenderers = computed(() =>
  segments.value.map((seg) => (seg.kind === 'text' ? null : getInlineRenderer(seg.type))),
);

function onKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault();
    emit('toggle');
  }
}
</script>

<template>
  <div class="chat-fold-panel" :class="{ 'chat-fold-panel--open': props.expanded }">
    <div v-if="props.expanded" class="chat-fold-panel__body">
      <slot />
    </div>
    <div
      class="chat-fold-panel__header"
      role="button"
      tabindex="0"
      :aria-expanded="props.expanded"
      @click="emit('toggle')"
      @keydown="onKeydown"
    >
      <div class="chat-fold-panel__strip">
        <v-icon :icon="props.expanded ? 'mdi-chevron-down' : 'mdi-chevron-up'" size="20" />
      </div>
      <div class="chat-fold-panel__summary">
        <template v-for="(seg, si) in segments" :key="si">
          <template v-if="seg.kind === 'text'">{{ seg.text }}</template>
          <component
            v-else-if="inlineRenderers[si]"
            :is="inlineRenderers[si]?.component"
            :segment="seg"
            :context="props.rendererContext"
          />
        </template>
      </div>
    </div>
  </div>
</template>

<style scoped>
.chat-fold-panel {
  margin: 0 16px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 10px;
  overflow: hidden;
  background: rgb(var(--v-theme-surface));
}
.chat-fold-panel__body {
  display: flex;
  flex-direction: column;
  gap: 12px;
  padding: 8px 0 12px;
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.chat-fold-panel__header {
  cursor: pointer;
  user-select: none;
  outline: none;
  background: rgba(var(--v-theme-on-surface), 0.04);
}
.chat-fold-panel__header:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}
.chat-fold-panel--open .chat-fold-panel__header {
  background: rgba(var(--v-theme-primary), 0.12);
}
.chat-fold-panel__strip {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 22px;
  color: rgba(var(--v-theme-on-surface), 0.55);
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
.chat-fold-panel--open .chat-fold-panel__strip {
  color: rgb(var(--v-theme-primary));
}
.chat-fold-panel__summary {
  padding: 8px 12px 10px;
  font-size: 13px;
  line-height: 1.4;
  white-space: pre-wrap;
}
</style>
