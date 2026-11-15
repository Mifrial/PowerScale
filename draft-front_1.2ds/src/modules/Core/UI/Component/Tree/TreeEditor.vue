<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { TreeNodeSource } from '@/modules/Core/UI/Dto/Tree/TreeNodeSource';
import type { TreeDropPlacement } from '@/modules/Core/UI/Enum/TreeDropPlacement';
import TreeView from '@/modules/Core/UI/Component/Tree/TreeView.vue';
import { treeNodeService } from '@/modules/Core/UI/Service/Instance/treeNodeService';

const props = withDefaults(
  defineProps<{
    nodes: TreeNodeSource[];
    readonly?: boolean;
    emptyText?: string;
    selectedId?: string | null;
    editLabel?: string;
    addChildLabel?: string;
  }>(),
  {
    readonly: false,
    emptyText: 'Элементов пока нет',
    selectedId: null,
    editLabel: 'Редактировать',
    addChildLabel: 'Добавить дочерний',
  },
);

const emit = defineEmits<{
  edit: [id: string];
  addChild: [id: string];
  activate: [id: string];
  drop: [sourceId: string, targetId: string, placement: TreeDropPlacement];
}>();

const expandedIds = ref<string[]>([]);
const dragId = ref<string | null>(null);
const dropId = ref<string | null>(null);
const dropPlacement = ref<TreeDropPlacement | null>(null);

const treeNodes = computed(() => treeNodeService.nest(props.nodes));

watch(
  treeNodes,
  (tree) => {
    const rootIds = tree.map((node) => node.id);
    if (expandedIds.value.length === 0) {
      expandedIds.value = rootIds;

      return;
    }
    const known = new Set(treeNodeService.parentIds(tree).concat(rootIds));
    expandedIds.value = expandedIds.value.filter((id) => known.has(id) || rootIds.includes(id));
  },
  { immediate: true },
);

function onActivate(id: string): void {
  const node = treeNodeService.find(treeNodes.value, id);
  if (node && node.children.length > 0) {
    expandedIds.value = expandedIds.value.includes(id)
      ? expandedIds.value.filter((item) => item !== id)
      : [...expandedIds.value, id];
  }
  emit('activate', id);
}

function onDragStart(id: string): void {
  dragId.value = id;
}

function onDragOver(id: string, placement: TreeDropPlacement): void {
  dropId.value = id;
  dropPlacement.value = placement;
}

function onDrop(targetId: string): void {
  if (!dragId.value || !dropPlacement.value) return;
  emit('drop', dragId.value, targetId, dropPlacement.value);
  dragId.value = null;
  dropId.value = null;
  dropPlacement.value = null;
}
</script>

<template>
  <TreeView
    v-model:expanded-ids="expandedIds"
    :nodes="treeNodes"
    :selected-id="props.selectedId"
    :empty-text="props.emptyText"
    :draggable="!props.readonly"
    :drop-id="dropId"
    :drop-placement="dropPlacement"
    @activate="onActivate"
    @dragstart="onDragStart"
    @dragover="onDragOver"
    @drop="onDrop"
    @dragend="dragId = null"
  >
    <template v-if="!props.readonly" #prepend="{ node }">
      <v-btn
        icon
        variant="text"
        size="x-small"
        class="tree-editor__icon"
        :aria-label="props.editLabel"
        @click.stop="emit('edit', node.id)"
      >
        <v-icon size="16">mdi-pencil-outline</v-icon>
      </v-btn>
      <v-btn
        icon
        variant="text"
        size="x-small"
        class="tree-editor__icon"
        :aria-label="props.addChildLabel"
        @click.stop="emit('addChild', node.id)"
      >
        <v-icon size="16">mdi-plus</v-icon>
      </v-btn>
    </template>
  </TreeView>
</template>

<style scoped>
.tree-editor__icon {
  width: 22px;
  height: 22px;
  min-width: 22px;
}
</style>
