<script setup lang="ts">
import { computed } from 'vue';
import { descriptionHtmlSanitizerService } from '@/modules/Core/UI/Service/Instance/descriptionHtmlSanitizerService';

const props = defineProps<{
  html: string;
}>();

const emit = defineEmits<{
  'open-rule': [code: string];
}>();

const sanitizedHtml = computed(() => descriptionHtmlSanitizerService.sanitize(props.html));

function openRule(event: MouseEvent): void {
  const target = event.target;
  if (!(target instanceof Element)) return;
  const link = target.closest<HTMLElement>('[data-rule-code]');
  const code = link?.dataset.ruleCode;
  if (!code) return;
  event.preventDefault();
  emit('open-rule', code);
}
</script>

<template>
  <div class="description-html" @click="openRule" v-html="sanitizedHtml" />
</template>

<style scoped>
.description-html :deep(.description-example) {
  color: rgba(var(--v-theme-on-surface), 0.58);
}

.description-html :deep(.description-flavor) {
  color: rgba(var(--v-theme-on-surface), 0.62);
  font-style: italic;
}

.description-html :deep([data-rule-code]) {
  color: rgba(var(--v-theme-on-surface), 0.72);
  cursor: pointer;
  text-decoration: underline dotted;
  text-underline-offset: 3px;
}

.description-html :deep(.description-table-wrapper) {
  max-width: 100%;
  overflow-x: auto;
}

.description-html :deep(table) {
  border-collapse: collapse;
  width: 100%;
}

.description-html :deep(th),
.description-html :deep(td) {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  padding: 4px 8px;
  text-align: left;
}
</style>
