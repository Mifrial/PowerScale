<script setup lang="ts">
import type { TreeNode } from '@/modules/Core/UI/Dto/Tree/TreeNode';
import type { TreeDropPlacement } from '@/modules/Core/UI/Enum/TreeDropPlacement';
import TreeViewNode from '@/modules/Core/UI/Component/Tree/TreeViewNode.vue';

const props = defineProps<{
  nodes: TreeNode[];
  expandedIds: string[];
  selectedId?: string | null;
  emptyText?: string;
  draggable?: boolean;
  dropId?: string | null;
  dropPlacement?: TreeDropPlacement | null;
}>();

const emit = defineEmits<{
  'update:expandedIds': [value: string[]];
  activate: [id: string];
  dragstart: [id: string];
  dragover: [id: string, placement: TreeDropPlacement];
  drop: [id: string];
  dragend: [];
}>();

function toggle(id: string): void {
  emit(
    'update:expandedIds',
    props.expandedIds.includes(id) ? props.expandedIds.filter((item) => item !== id) : [...props.expandedIds, id],
  );
}
</script>

<template>
  <div>
    <div v-if="nodes.length === 0" class="text-body-2 text-medium-emphasis pa-4">
      {{ emptyText || 'Нет элементов' }}
    </div>
    <TreeViewNode
      v-for="(node, index) in nodes"
      :key="node.id"
      :node="node"
      :depth="0"
      :is-last="index === nodes.length - 1"
      :expanded-ids="expandedIds"
      :selected-id="selectedId"
      :draggable="draggable"
      :drop-id="dropId"
      :drop-placement="dropPlacement"
      @toggle="toggle"
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
</template>
