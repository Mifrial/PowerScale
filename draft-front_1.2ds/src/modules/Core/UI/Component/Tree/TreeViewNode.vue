<script setup lang="ts">
import { computed, ref } from 'vue';
import type { TreeNode } from '@/modules/Core/UI/Dto/Tree/TreeNode';
import type { TreeDropPlacement } from '@/modules/Core/UI/Enum/TreeDropPlacement';
import TreeViewNode from '@/modules/Core/UI/Component/Tree/TreeViewNode.vue';

const props = defineProps<{
  node: TreeNode;
  depth: number;
  isLast: boolean;
  expandedIds: string[];
  selectedId?: string | null;
  draggable?: boolean;
  dropId?: string | null;
  dropPlacement?: TreeDropPlacement | null;
}>();

const emit = defineEmits<{
  toggle: [id: string];
  activate: [id: string];
  dragstart: [id: string];
  dragover: [id: string, placement: TreeDropPlacement];
  drop: [id: string];
  dragend: [];
}>();

const didDrag = ref(false);
const hasChildren = computed(() => props.node.children.length > 0);
const isExpanded = computed(() => props.expandedIds.includes(props.node.id));
const isBranchOpen = computed(() => hasChildren.value && isExpanded.value);
const isSelected = computed(() => props.selectedId === props.node.id);

function onDragStart(): void {
  didDrag.value = true;
  emit('dragstart', props.node.id);
}

function onDragEnd(): void {
  emit('dragend');
  window.setTimeout(() => {
    didDrag.value = false;
  }, 0);
}

function onRowClick(): void {
  if (didDrag.value) {
    didDrag.value = false;

    return;
  }
  emit('activate', props.node.id);
}

function onDragOver(event: DragEvent): void {
  if (!props.draggable) return;
  event.preventDefault();
  const row = event.currentTarget as HTMLElement;
  const rect = row.getBoundingClientRect();
  const ratio = (event.clientY - rect.top) / rect.height;
  const placement: TreeDropPlacement = ratio < 0.25 ? 'before' : ratio > 0.75 ? 'after' : 'child';
  emit('dragover', props.node.id, placement);
}

function onDrop(event: DragEvent): void {
  if (!props.draggable) return;
  event.preventDefault();
  emit('drop', props.node.id);
}
</script>

<template>
  <div
    class="tree-node"
    :class="{
      'tree-node--root': depth === 0,
      'tree-node--last': isLast,
    }"
  >
    <div
      class="tree-row"
      :class="{
        'tree-row--before': dropId === node.id && dropPlacement === 'before',
        'tree-row--after': dropId === node.id && dropPlacement === 'after',
        'tree-row--child': dropId === node.id && dropPlacement === 'child',
        'tree-row--parent': hasChildren,
        'tree-row--selected': isSelected,
      }"
      :draggable="draggable"
      @click="onRowClick"
      @dragstart="onDragStart"
      @dragend="onDragEnd"
      @dragover="onDragOver"
      @drop="onDrop"
    >
      <slot name="prepend" :node="node" :has-children="hasChildren" />
      <span class="tree-row__name">{{ node.label }}</span>
      <v-btn
        v-if="hasChildren"
        icon
        variant="text"
        size="x-small"
        class="tree-row__icon"
        :aria-label="isExpanded ? 'Свернуть' : 'Развернуть'"
        @click.stop="emit('toggle', node.id)"
      >
        <v-icon size="16">{{ isExpanded ? 'mdi-chevron-down' : 'mdi-chevron-right' }}</v-icon>
      </v-btn>
    </div>
    <div v-if="isBranchOpen" class="tree-node__stem" aria-hidden="true" />
    <div v-if="isBranchOpen" class="tree-node__kids">
      <TreeViewNode
        v-for="(child, index) in node.children"
        :key="child.id"
        :node="child"
        :depth="depth + 1"
        :is-last="index === node.children.length - 1"
        :expanded-ids="expandedIds"
        :selected-id="selectedId"
        :draggable="draggable"
        :drop-id="dropId"
        :drop-placement="dropPlacement"
        @toggle="emit('toggle', $event)"
        @activate="emit('activate', $event)"
        @dragstart="emit('dragstart', $event)"
        @dragover="(id, placement) => emit('dragover', id, placement)"
        @drop="emit('drop', $event)"
        @dragend="emit('dragend')"
      >
        <template #prepend="slotProps">
          <slot name="prepend" v-bind="slotProps" />
        </template>
      </TreeViewNode>
    </div>
  </div>
</template>

<style scoped>
.tree-node {
  --tree-line: rgba(var(--v-theme-on-surface), 0.28);
  --tree-row: 26px;
  --tree-mid: 13px;
  --tree-radius: 8px;
  --tree-arm: 8px;
  --tree-gutter: 16px;
  position: relative;
}
.tree-node:not(.tree-node--root)::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: calc(var(--tree-mid) - var(--tree-radius));
  border-left: 1px solid var(--tree-line);
  pointer-events: none;
}
.tree-node:not(.tree-node--root):not(.tree-node--last)::before {
  height: 100%;
}
.tree-node:not(.tree-node--root)::after {
  content: '';
  position: absolute;
  left: 0;
  top: calc(var(--tree-mid) - var(--tree-radius));
  width: var(--tree-arm);
  height: var(--tree-radius);
  box-sizing: border-box;
  border-left: 1px solid var(--tree-line);
  border-bottom: 1px solid var(--tree-line);
  border-bottom-left-radius: var(--tree-radius);
  pointer-events: none;
}
.tree-node__stem {
  position: absolute;
  z-index: 1;
  left: calc(var(--tree-gutter) - var(--tree-radius));
  top: var(--tree-mid);
  width: var(--tree-radius);
  height: calc(var(--tree-row) - var(--tree-radius));
  box-sizing: border-box;
  border-top: 1px solid var(--tree-line);
  border-right: 1px solid var(--tree-line);
  border-top-right-radius: var(--tree-radius);
  pointer-events: none;
}
.tree-node--root > .tree-node__stem {
  left: var(--tree-gutter);
  width: 0;
  height: calc(var(--tree-row) - var(--tree-radius));
  border-top: none;
  border-right: none;
  border-top-right-radius: 0;
  border-left: 1px solid var(--tree-line);
}
.tree-node__kids {
  position: relative;
  margin-left: var(--tree-gutter);
  padding: 0 4px 4px 0;
  overflow: visible;
  background: rgba(var(--v-theme-on-surface), 0.04);
  border-radius: 6px;
}
.tree-row {
  display: flex;
  align-items: center;
  min-height: var(--tree-row);
  gap: 0;
  padding: 0 4px 0 var(--tree-gutter);
  border-radius: 4px;
  cursor: pointer;
}
.tree-row:hover {
  background: rgba(var(--v-theme-on-surface), 0.08);
}
.tree-row--selected {
  background: rgba(var(--v-theme-primary), 0.16);
}
.tree-row--before {
  box-shadow: inset 0 2px 0 rgb(var(--v-theme-primary));
}
.tree-row--after {
  box-shadow: inset 0 -2px 0 rgb(var(--v-theme-primary));
}
.tree-row--child {
  outline: 1px dashed rgb(var(--v-theme-primary));
}
.tree-row__icon {
  width: 22px;
  height: 22px;
  min-width: 22px;
}

.tree-row__icon:hover {
  background: rgb(var(--v-theme-primary));
}

.tree-row__icon:hover :deep(.v-btn__overlay) {
  opacity: 0;
}

.tree-row__icon:hover :deep(.v-icon) {
  color: rgb(var(--v-theme-on-primary));
}
.tree-row__name {
  min-width: 0;
  flex: 1 1 auto;
  padding: 0 2px 0 4px;
  font-size: 0.875rem;
  line-height: 1.25;
}
</style>
