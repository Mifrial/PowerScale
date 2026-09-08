<script setup lang="ts">
import { computed, ref } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';
import SectionTreeRow from '@/modules/Roleplay/RuleSpace/Component/Section/SectionTreeRow.vue';

const props = defineProps<{
  section: AbilitySection;
  sections: AbilitySection[];
  depth: number;
  isLast: boolean;
  expandedCodes: string[];
  readonly: boolean;
  dropCode: string | null;
  dropPlacement: SectionDropPlacement | null;
}>();

const emit = defineEmits<{
  toggle: [code: string];
  edit: [code: string];
  addChild: [code: string];
  dragstart: [code: string];
  dragover: [code: string, placement: SectionDropPlacement];
  drop: [targetCode: string];
}>();

const didDrag = ref(false);

const children = computed(() =>
  props.sections
    .filter((section) => section.parentCode === props.section.code)
    .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code)),
);

const isExpanded = computed(() => props.expandedCodes.includes(props.section.code));
const hasChildren = computed(() => children.value.length > 0);
const isBranchOpen = computed(() => hasChildren.value && isExpanded.value);

function onDragStart(): void {
  didDrag.value = true;
  emit('dragstart', props.section.code);
}

function onDragEnd(): void {
  window.setTimeout(() => {
    didDrag.value = false;
  }, 0);
}

function onRowClick(): void {
  if (didDrag.value) {
    didDrag.value = false;

    return;
  }
  if (hasChildren.value) emit('toggle', props.section.code);
}

function onDragOver(event: DragEvent): void {
  if (props.readonly) return;
  event.preventDefault();
  const row = event.currentTarget as HTMLElement;
  const rect = row.getBoundingClientRect();
  const ratio = (event.clientY - rect.top) / rect.height;
  const placement: SectionDropPlacement = ratio < 0.25 ? 'before' : ratio > 0.75 ? 'after' : 'child';
  emit('dragover', props.section.code, placement);
}

function onDrop(event: DragEvent): void {
  if (props.readonly) return;
  event.preventDefault();
  emit('drop', props.section.code);
}
</script>

<template>
  <div
    class="section-node"
    :class="{
      'section-node--root': depth === 0,
      'section-node--last': isLast,
    }"
  >
    <div
      class="section-row"
      :class="{
        'section-row--before': dropCode === section.code && dropPlacement === 'before',
        'section-row--after': dropCode === section.code && dropPlacement === 'after',
        'section-row--child': dropCode === section.code && dropPlacement === 'child',
        'section-row--parent': hasChildren,
      }"
      :draggable="!readonly"
      @click="onRowClick"
      @dragstart="onDragStart"
      @dragend="onDragEnd"
      @dragover="onDragOver"
      @drop="onDrop"
    >
      <v-btn
        v-if="!readonly"
        icon
        variant="text"
        size="x-small"
        class="section-row__icon"
        aria-label="Редактировать секцию"
        @click.stop="emit('edit', section.code)"
      >
        <v-icon size="16">mdi-pencil-outline</v-icon>
      </v-btn>
      <v-btn
        v-if="!readonly"
        icon
        variant="text"
        size="x-small"
        class="section-row__icon"
        aria-label="Добавить дочернюю секцию"
        @click.stop="emit('addChild', section.code)"
      >
        <v-icon size="16">mdi-plus</v-icon>
      </v-btn>
      <span class="section-row__name">{{ section.name }}</span>
      <v-btn
        v-if="hasChildren"
        icon
        variant="text"
        size="x-small"
        class="section-row__icon"
        :aria-label="isExpanded ? 'Свернуть' : 'Развернуть'"
        @click.stop="emit('toggle', section.code)"
      >
        <v-icon size="16">{{ isExpanded ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
      </v-btn>
    </div>
    <div v-if="isBranchOpen" class="section-node__stem" aria-hidden="true" />
    <div v-if="isBranchOpen" class="section-node__kids">
      <SectionTreeRow
        v-for="(child, index) in children"
        :key="child.code"
        :section="child"
        :sections="sections"
        :depth="depth + 1"
        :is-last="index === children.length - 1"
        :expanded-codes="expandedCodes"
        :readonly="readonly"
        :drop-code="dropCode"
        :drop-placement="dropPlacement"
        @toggle="emit('toggle', $event)"
        @edit="emit('edit', $event)"
        @add-child="emit('addChild', $event)"
        @dragstart="emit('dragstart', $event)"
        @dragover="(code, placement) => emit('dragover', code, placement)"
        @drop="emit('drop', $event)"
      />
    </div>
  </div>
</template>

<style scoped>
.section-node {
  --section-line: rgba(var(--v-theme-on-surface), 0.28);
  --section-row: 26px;
  --section-mid: 13px;
  --section-radius: 8px;
  --section-arm: 8px;
  --section-gutter: 16px;
  position: relative;
}
.section-node:not(.section-node--root)::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: calc(var(--section-mid) - var(--section-radius));
  border-left: 1px solid var(--section-line);
  pointer-events: none;
}
.section-node:not(.section-node--root):not(.section-node--last)::before {
  height: 100%;
}
.section-node:not(.section-node--root)::after {
  content: '';
  position: absolute;
  left: 0;
  top: calc(var(--section-mid) - var(--section-radius));
  width: var(--section-arm);
  height: var(--section-radius);
  box-sizing: border-box;
  border-left: 1px solid var(--section-line);
  border-bottom: 1px solid var(--section-line);
  border-bottom-left-radius: var(--section-radius);
  pointer-events: none;
}
.section-node__stem {
  position: absolute;
  z-index: 1;
  left: calc(var(--section-gutter) - var(--section-radius));
  top: var(--section-mid);
  width: var(--section-radius);
  height: calc(var(--section-row) - var(--section-radius));
  box-sizing: border-box;
  border-top: 1px solid var(--section-line);
  border-right: 1px solid var(--section-line);
  border-top-right-radius: var(--section-radius);
  pointer-events: none;
}
.section-node--root > .section-node__stem {
  left: var(--section-gutter);
  width: 0;
  height: calc(var(--section-row) - var(--section-radius));
  border-top: none;
  border-right: none;
  border-top-right-radius: 0;
  border-left: 1px solid var(--section-line);
}
.section-node__kids {
  position: relative;
  margin-left: var(--section-gutter);
  padding: 0 4px 4px 0;
  overflow: visible;
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 6px;
}
.section-row {
  display: flex;
  align-items: center;
  min-height: var(--section-row);
  gap: 0;
  padding: 0 4px 0 var(--section-gutter);
  border-radius: 4px;
  cursor: default;
}
.section-row--parent {
  cursor: pointer;
}
.section-row:hover {
  background: rgba(var(--v-theme-on-surface), 0.08);
}
.section-row--before {
  box-shadow: inset 0 2px 0 rgb(var(--v-theme-primary));
}
.section-row--after {
  box-shadow: inset 0 -2px 0 rgb(var(--v-theme-primary));
}
.section-row--child {
  outline: 1px dashed rgb(var(--v-theme-primary));
}
.section-row__icon {
  width: 22px;
  height: 22px;
  min-width: 22px;
}
.section-row__name {
  min-width: 0;
  padding: 0 2px 0 4px;
  font-size: 0.875rem;
  line-height: 1.25;
}
</style>
