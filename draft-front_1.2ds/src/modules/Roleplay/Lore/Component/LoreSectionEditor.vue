<script setup lang="ts">
import { computed, ref } from 'vue';
import type { LoreSectionInput } from '@/modules/Roleplay/Lore/Dto/LoreSectionInput';
import type { LoreSectionDropPlacement } from '@/modules/Roleplay/Lore/Enum/LoreSectionDropPlacement';
import TreeEditor from '@/modules/Core/UI/Component/Tree/TreeEditor.vue';
import LoreSectionDialog from '@/modules/Roleplay/Lore/Component/LoreSectionDialog.vue';
import { loreSectionTreeService } from '@/modules/Roleplay/Lore/Service/Instance/loreSectionTreeService';

const props = withDefaults(
  defineProps<{
    modelValue: LoreSectionInput[];
    articleCounts?: Record<string, number>;
  }>(),
  {
    articleCounts: () => ({}),
  },
);

const emit = defineEmits<{
  'update:modelValue': [value: LoreSectionInput[]];
}>();

const dialogOpen = ref(false);
const dialogMode = ref<'create' | 'edit'>('create');
const editedCode = ref<string | null>(null);
const dialogParentCode = ref<string | null>(null);

const sections = computed(() => loreSectionTreeService.normalize(props.modelValue));
const nodes = computed(() =>
  sections.value.map((section) => ({
    id: section.code,
    label: section.name,
    parentId: section.parentCode,
    sortOrder: section.sortOrder,
  })),
);
const existingCodes = computed(() => sections.value.map((section) => section.code));
const editedSection = computed(() =>
  editedCode.value === null ? null : (sections.value.find((section) => section.code === editedCode.value) ?? null),
);
const parentOptions = computed(() => {
  const excluded = editedCode.value ? loreSectionTreeService.descendantCodes(sections.value, editedCode.value) : [];
  const items: { value: string | null; title: string }[] = [{ value: null, title: '— Корень —' }];
  for (const section of sections.value) {
    if (excluded.includes(section.code)) continue;
    items.push({ value: section.code, title: sectionPath(section.code) });
  }

  return items;
});
const removeBlocked = computed(() => {
  const section = editedSection.value;
  if (!section) return null;
  if (loreSectionTreeService.hasChildren(sections.value, section.code)) {
    return 'Сначала перенесите дочерние разделы.';
  }
  const articleCount = props.articleCounts[section.code] ?? 0;
  if (articleCount > 0) {
    return `В разделе ${articleCount} статей: сначала перенесите их в другой раздел.`;
  }

  return null;
});
const dialogParentPath = computed(() => {
  if (editedSection.value) return sectionPath(editedSection.value.code);

  return dialogParentCode.value === null ? '' : sectionPath(dialogParentCode.value);
});

function sectionPath(code: string): string {
  const byCode = new Map(sections.value.map((section) => [section.code, section]));
  const parts: string[] = [];
  const visited = new Set<string>();
  let current = byCode.get(code) ?? null;
  while (current && !visited.has(current.code)) {
    visited.add(current.code);
    parts.unshift(current.name);
    current = current.parentCode === null ? null : (byCode.get(current.parentCode) ?? null);
  }

  return parts.join(' → ');
}

function emitValue(value: LoreSectionInput[]): void {
  emit('update:modelValue', value);
}

function openCreate(parentCode: string | null): void {
  dialogMode.value = 'create';
  editedCode.value = null;
  dialogParentCode.value = parentCode;
  dialogOpen.value = true;
}

function openEdit(code: string): void {
  dialogMode.value = 'edit';
  editedCode.value = code;
  dialogParentCode.value = null;
  dialogOpen.value = true;
}

function onSave(payload: {
  code: string;
  name: string;
  description: string;
  icon: string;
  parentCode: string | null;
}): void {
  if (dialogMode.value === 'create') {
    emitValue(
      loreSectionTreeService.add(sections.value, {
        code: payload.code,
        name: payload.name,
        description: payload.description,
        icon: payload.icon,
        parentCode: payload.parentCode,
        sortOrder: 0,
      }),
    );
  } else if (editedCode.value !== null) {
    emitValue(
      loreSectionTreeService.update(sections.value, editedCode.value, {
        name: payload.name,
        description: payload.description,
        icon: payload.icon,
        parentCode: payload.parentCode,
      }),
    );
  }
  dialogOpen.value = false;
}

function onRemove(): void {
  if (editedCode.value === null || removeBlocked.value) return;
  emitValue(loreSectionTreeService.remove(sections.value, editedCode.value));
  dialogOpen.value = false;
}

function onDrop(sourceCode: string, targetCode: string, placement: LoreSectionDropPlacement): void {
  emitValue(loreSectionTreeService.drop(sections.value, sourceCode, targetCode, placement));
}

function openRootCreate(): void {
  openCreate(null);
}

defineExpose({ openRootCreate });
</script>

<template>
  <div class="lore-sections">
    <div class="text-caption text-medium-emphasis mb-2">
      Разделы вкладываются друг в друга и перетаскиваются мышью. Статьи назначаются разделу в редакторе статьи.
    </div>

    <div class="lore-sections__tree">
      <TreeEditor
        :nodes="nodes"
        empty-text="Разделов пока нет"
        edit-label="Редактировать раздел"
        add-child-label="Добавить подраздел"
        @edit="openEdit"
        @add-child="openCreate"
        @drop="onDrop"
      />
    </div>

    <LoreSectionDialog
      v-model="dialogOpen"
      :mode="dialogMode"
      :section="editedSection"
      :parent-code="dialogParentCode"
      :parent-path="dialogParentPath"
      :parent-options="parentOptions"
      :existing-codes="existingCodes"
      :remove-blocked="removeBlocked"
      @save="onSave"
      @remove="onRemove"
    />
  </div>
</template>

<style scoped>
.lore-sections__tree {
  padding: 4px;
  border-radius: 8px;
  background: rgb(var(--v-theme-primaryLight));
}
</style>
