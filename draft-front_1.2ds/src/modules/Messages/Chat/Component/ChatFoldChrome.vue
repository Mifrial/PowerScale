<script setup lang="ts">
import { computed } from 'vue';
import type { ChatFoldTone } from '@/modules/Messages/Chat/Enum/ChatFoldTone';
import type { ChatFoldVariant } from '@/modules/Messages/Chat/Enum/ChatFoldVariant';
import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
import { getInlineRenderer } from '@/modules/Messages/Chat/init';
import { inlineContentService } from '@/modules/Messages/Chat/Service/Instance/inlineContentService';
import { hostInlineRendererContext } from '@/modules/Messages/Chat/Utils/hostInlineRendererContext';

const props = defineProps<{
  summary: string;
  expanded: boolean;
  tone?: ChatFoldTone;
  variant?: ChatFoldVariant;
  rendererContext?: ChatInlineRendererContext | null;
  openEntity?: (ref: string) => void;
}>();

const emit = defineEmits<{
  toggle: [];
}>();

const pluginContext = computed(() => hostInlineRendererContext(props.rendererContext, props.openEntity));

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
  <div
    class="chat-fold-chrome"
    :class="[
      `chat-fold-chrome--${props.variant ?? 'divider'}`,
      { 'chat-fold-chrome--open': props.expanded, 'chat-fold-chrome--highlighted': props.tone === 'highlighted' },
    ]"
    role="button"
    tabindex="0"
    :aria-expanded="props.expanded"
    @click="emit('toggle')"
    @keydown="onKeydown"
  >
    <v-icon
      :icon="props.expanded ? 'mdi-chevron-down' : 'mdi-chevron-right'"
      size="18"
      class="chat-fold-chrome__chevron"
    />
    <v-divider class="chat-fold-chrome__line" />
    <span class="chat-fold-chrome__text">
      <template v-for="(seg, si) in segments" :key="si">
        <template v-if="seg.kind === 'text'">{{ seg.text }}</template>
        <component
          v-else-if="inlineRenderers[si]"
          :is="inlineRenderers[si]?.component"
          :segment="seg"
          :context="pluginContext"
        />
      </template>
    </span>
    <v-divider class="chat-fold-chrome__line" />
  </div>
</template>

<style scoped>
.chat-fold-chrome {
  display: flex;
  align-items: center;
  gap: 8px;
  padding-left: 16px;
  padding-right: 16px;
  cursor: pointer;
  user-select: none;
  outline: none;
}
.chat-fold-chrome:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}
.chat-fold-chrome--divider {
  padding-top: 10px;
  padding-bottom: 4px;
}
.chat-fold-chrome__chevron {
  flex-shrink: 0;
  opacity: 0.7;
}
.chat-fold-chrome__line {
  flex: 1;
}
.chat-fold-chrome__text {
  flex-shrink: 1;
  font-size: 12px;
  font-weight: 500;
  color: rgba(var(--v-theme-on-surface), 0.55);
  text-align: center;
  max-width: 70%;
}
.chat-fold-chrome--highlighted .chat-fold-chrome__text {
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
}
</style>
