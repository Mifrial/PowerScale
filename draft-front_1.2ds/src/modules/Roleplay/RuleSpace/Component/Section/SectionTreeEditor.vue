<script setup lang="ts">
import { computed } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';
import TreeEditor from '@/modules/Core/UI/Component/Tree/TreeEditor.vue';

const props = defineProps<{
  sections: AbilitySection[];
  readonly: boolean;
}>();

const emit = defineEmits<{
  edit: [code: string];
  addChild: [code: string];
  drop: [sourceCode: string, targetCode: string, placement: SectionDropPlacement];
}>();

const nodes = computed(() =>
  props.sections.map((section) => ({
    id: section.code,
    label: section.name,
    parentId: section.parentCode,
    sortOrder: section.sortOrder,
  })),
);
</script>

<template>
  <TreeEditor
    :nodes="nodes"
    :readonly="props.readonly"
    empty-text="Секций пока нет"
    edit-label="Редактировать секцию"
    add-child-label="Добавить дочернюю секцию"
    @edit="emit('edit', $event)"
    @add-child="emit('addChild', $event)"
    @drop="(sourceCode, targetCode, placement) => emit('drop', sourceCode, targetCode, placement)"
  />
</template>
