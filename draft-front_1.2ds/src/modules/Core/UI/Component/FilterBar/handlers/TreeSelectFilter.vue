<script setup lang="ts">
import { computed } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterOptionValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterOptionValue';
import type { TreeSelectItem } from '@/modules/Core/UI/Dto/Tree/TreeSelectItem';
import TreeSelect from '@/modules/Core/UI/Component/Input/TreeSelect.vue';

const props = defineProps<{
  field: FilterField;
  modelValue?: FilterOptionValue | null;
  eager?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: FilterOptionValue | null | undefined];
}>();

const items = computed<TreeSelectItem[]>(() =>
  (props.field.treeOptions ?? []).map((option) => ({
    value: option.value as string | number,
    label: option.label,
    parentValue: option.parentValue as string | number | null | undefined,
  })),
);

const value = computed(() =>
  props.modelValue === null || props.modelValue === undefined ? null : (props.modelValue as string | number),
);

function onUpdate(next: string | number | null): void {
  emit('update:modelValue', next);
}
</script>

<template>
  <div class="flex-grow-1" @click.stop>
    <TreeSelect
      :model-value="value"
      :items="items"
      :label="props.field.label"
      placeholder="Выбрать секцию"
      empty-text="Секции не найдены"
      search-label="Поиск по секциям"
      density="compact"
      clearable
      :eager="props.eager"
      @update:model-value="onUpdate"
    />
  </div>
</template>
