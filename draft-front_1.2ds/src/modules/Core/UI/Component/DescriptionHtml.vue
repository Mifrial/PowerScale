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

.description-html :deep(.description-hint) {
  color: rgba(var(--v-theme-primary), 0.9);
}

.description-html :deep(.description-expanded-block) {
  margin: 8px 0;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 4px;
}

.description-html :deep(.description-expanded-block__header) {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 12px;
  cursor: pointer;
}

.description-html :deep(.description-expanded-block__title) {
  text-align: left;
}

.description-html :deep(.description-expanded-block__difficulty) {
  text-align: right;
  color: rgba(var(--v-theme-on-surface), 0.7);
}

.description-html :deep(.description-expanded-block__body) {
  padding: 0 12px 8px;
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
