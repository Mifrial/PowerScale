<script setup lang="ts">
import { computed, ref } from 'vue';
import type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
import type { InlineSegment } from '@/modules/Messages/Chat/Dto/InlineSegment';
import RuleSlider from '@/modules/Roleplay/Rule/Component/RuleSlider.vue';
import { ruleChipFromContext } from '@/modules/Roleplay/Rule/Utils/ruleChipFromContext';

const props = defineProps<{
  segment: Extract<InlineSegment, { kind: 'token' }>;
  /** Data-срез хоста: без него чип скрыт; имена только из tokenLabels. */
  context?: ChatInlineRendererContext;
}>();

const sliderOpen = ref(false);

const code = computed(() => props.segment.params[0] ?? '');
const rule = computed(() => ruleChipFromContext(code.value, props.context));
const displayName = computed(() => props.segment.params[1]?.trim() || rule.value?.name || null);
</script>

<template>
  <span v-if="rule" class="chat-rule-chip" @click="sliderOpen = true">
    <v-icon size="x-small" class="mr-1">mdi-book-open-variant</v-icon>
    <span class="chat-rule-chip__name">{{ displayName }}</span>
  </span>
  <span v-else class="chat-rule-chip chat-rule-chip--hidden">
    <v-icon size="x-small" class="mr-1">mdi-book-lock</v-icon>
    <span>Объект скрыт</span>
  </span>

  <RuleSlider
    v-model:open="sliderOpen"
    :rule-code="code || null"
    :space-id="props.context?.spaceId ?? null"
    :rules-revision="props.context?.rulesRevision ?? null"
  />
</template>

<style scoped>
.chat-rule-chip {
  display: inline-flex;
  align-items: center;
  color: rgb(var(--v-theme-primary));
  font-weight: 600;
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}

.chat-rule-chip__name {
  font-weight: 600;
}

.chat-rule-chip--hidden {
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  cursor: default;
  font-style: italic;
  text-decoration: none;
}
</style>
