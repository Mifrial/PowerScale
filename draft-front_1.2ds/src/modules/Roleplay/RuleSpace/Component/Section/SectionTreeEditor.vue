<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { AbilitySection } from '@/modules/Roleplay/RuleSpace/Dto/AbilitySection';
import type { SectionDropPlacement } from '@/modules/Roleplay/RuleSpace/Enum/SectionDropPlacement';
import SectionTreeRow from '@/modules/Roleplay/RuleSpace/Component/Section/SectionTreeRow.vue';

const props = defineProps<{
  sections: AbilitySection[];
  readonly: boolean;
}>();

const emit = defineEmits<{
  edit: [code: string];
  addChild: [code: string];
  drop: [sourceCode: string, targetCode: string, placement: SectionDropPlacement];
}>();

const expandedCodes = ref<string[]>([]);
const dragCode = ref<string | null>(null);
const dropCode = ref<string | null>(null);
const dropPlacement = ref<SectionDropPlacement | null>(null);

const roots = computed(() =>
  props.sections
    .filter((section) => section.parentCode === null)
    .sort((left, right) => left.sortOrder - right.sortOrder || left.code.localeCompare(right.code)),
);

watch(
  () => props.sections.map((section) => section.code).join(','),
  () => {
    const codes = new Set(
      props.sections.filter((section) => section.parentCode === null).map((section) => section.code),
    );
    if (expandedCodes.value.length === 0) {
      expandedCodes.value = [...codes];

      return;
    }
    expandedCodes.value = expandedCodes.value.filter((code) => props.sections.some((section) => section.code === code));
  },
  { immediate: true },
);

function toggle(code: string): void {
  expandedCodes.value = expandedCodes.value.includes(code)
    ? expandedCodes.value.filter((item) => item !== code)
    : [...expandedCodes.value, code];
}

function onDragStart(code: string): void {
  dragCode.value = code;
}

function onDragOver(code: string, placement: SectionDropPlacement): void {
  dropCode.value = code;
  dropPlacement.value = placement;
}

function onDrop(targetCode: string): void {
  if (!dragCode.value || !dropPlacement.value) return;
  emit('drop', dragCode.value, targetCode, dropPlacement.value);
  dragCode.value = null;
  dropCode.value = null;
  dropPlacement.value = null;
}
</script>

<template>
  <div>
    <div v-if="roots.length === 0" class="text-body-2 text-medium-emphasis pa-4">Секций пока нет</div>
    <SectionTreeRow
      v-for="(root, index) in roots"
      :key="root.code"
      :section="root"
      :sections="sections"
      :depth="0"
      :is-last="index === roots.length - 1"
      :expanded-codes="expandedCodes"
      :readonly="readonly"
      :drop-code="dropCode"
      :drop-placement="dropPlacement"
      @toggle="toggle"
      @edit="emit('edit', $event)"
      @add-child="emit('addChild', $event)"
      @dragstart="onDragStart"
      @dragover="onDragOver"
      @drop="onDrop"
    />
  </div>
</template>
