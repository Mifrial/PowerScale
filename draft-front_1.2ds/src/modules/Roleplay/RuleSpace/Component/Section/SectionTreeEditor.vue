<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';
import type { TreeDropPlacement } from '@/modules/Core/UI/Enum/TreeDropPlacement';
import TreeView from '@/modules/Core/UI/Component/Tree/TreeView.vue';
import { treeNodeService } from '@/modules/Core/UI/Service/Instance/treeNodeService';

const props = defineProps<{
  sections: AbilitySection[];
  readonly: boolean;
}>();

const emit = defineEmits<{
  edit: [code: string];
  addChild: [code: string];
  drop: [sourceCode: string, targetCode: string, placement: SectionDropPlacement];
}>();

const expandedIds = ref<string[]>([]);
const dragId = ref<string | null>(null);
const dropId = ref<string | null>(null);
const dropPlacement = ref<TreeDropPlacement | null>(null);

const nodes = computed(() =>
  treeNodeService.nest(
    props.sections.map((section) => ({
      id: section.code,
      label: section.name,
      parentId: section.parentCode,
      sortOrder: section.sortOrder,
    })),
  ),
);

watch(
  nodes,
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
  const node = treeNodeService.find(nodes.value, id);
  if (node && node.children.length > 0) {
    expandedIds.value = expandedIds.value.includes(id)
      ? expandedIds.value.filter((item) => item !== id)
      : [...expandedIds.value, id];
  }
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
    :nodes="nodes"
    v-model:expanded-ids="expandedIds"
    empty-text="Секций пока нет"
    :draggable="!readonly"
    :drop-id="dropId"
    :drop-placement="dropPlacement"
    @activate="onActivate"
    @dragstart="onDragStart"
    @dragover="onDragOver"
    @drop="onDrop"
    @dragend="dragId = null"
  >
    <template v-if="!readonly" #prepend="{ node }">
      <v-btn
        icon
        variant="text"
        size="x-small"
        class="section-tree__icon"
        aria-label="Редактировать секцию"
        @click.stop="emit('edit', node.id)"
      >
        <v-icon size="16">mdi-pencil-outline</v-icon>
      </v-btn>
      <v-btn
        icon
        variant="text"
        size="x-small"
        class="section-tree__icon"
        aria-label="Добавить дочернюю секцию"
        @click.stop="emit('addChild', node.id)"
      >
        <v-icon size="16">mdi-plus</v-icon>
      </v-btn>
    </template>
  </TreeView>
</template>

<style scoped>
.section-tree__icon {
  width: 22px;
  height: 22px;
  min-width: 22px;
}
</style>
