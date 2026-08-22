<script setup lang="ts">
import { computed, watch } from 'vue';
import type { Requirement } from '@/modules/Roleplay/Rule/Dto/Ability/Requirement';
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef';
import type { KeywordRef } from '@/modules/Roleplay/Rule/Dto/Ability/KeywordRef';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import { REQUIREMENT_TYPES } from '@/modules/Roleplay/Rule/Constant/Ability/REQUIREMENT_TYPES';

const props = defineProps<{
  modelValue: Requirement;
  characteristics: CharacteristicRef[];
  resources: ResourceRef[];
  abilities: AbilityRef[];
  keywords: KeywordRef[];
  abilityKeywords: KeywordRef[];
  removable?: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Requirement];
  remove: [];
}>();

const { inner } = useVModelSync<Requirement>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: true,
});

const selectedResourceIsDimensional = computed(() => {
  const v = inner.value;
  if (v.type !== 'resource_limit') return false;

  return props.resources.find((r) => r.code === v.resource_code)?.isDimensional ?? false;
});

watch(
  () => props.resources,
  (resources) => {
    const v = inner.value;
    if (v.type !== 'resource_limit' || !v.resource_code) return;
    const res = resources.find((r) => r.code === v.resource_code);
    const min = v.min;
    if (res?.isDimensional && typeof min === 'number') {
      inner.value = { ...v, min: { base: min, size: 0 } } as Requirement;
    } else if (!res?.isDimensional && min && typeof min === 'object' && !Array.isArray(min)) {
      inner.value = { ...v, min: min.base } as Requirement;
    }
  },
  { deep: true },
);

function updateType(type: string) {
  inner.value = abilitySpecService.createEmptyRequirement(type as Requirement['type']);
}

function patch(key: string, value: unknown) {
  inner.value = { ...inner.value, [key]: value } as Requirement;
}

function addChild() {
  if (inner.value.type === 'and' || inner.value.type === 'or') {
    inner.value = {
      ...inner.value,
      children: [...inner.value.children, abilitySpecService.createEmptyRequirement('has_keyword')],
    };
  }
}

function removeChild(index: number) {
  if (inner.value.type === 'and' || inner.value.type === 'or') {
    const children = inner.value.children.filter((_, i) => i !== index);
    inner.value = { ...inner.value, children };
  }
}
</script>

<template>
  <div class="requirement-node">
    <div class="d-flex gap-2 align-start">
      <v-select
        :model-value="inner.type"
        @update:model-value="updateType"
        :items="REQUIREMENT_TYPES"
        item-title="label"
        item-value="value"
        label="Условие"
        density="compact"
        hide-details
        style="min-width: 200px"
      >
        <template #item="{ props: itemProps, item }">
          <v-list-item v-bind="itemProps" :title="item.raw.label" :subtitle="item.raw.description" />
        </template>
      </v-select>

      <template v-if="inner.type === 'has_ability'">
        <v-autocomplete
          :model-value="inner.ability_code"
          @update:model-value="patch('ability_code', $event)"
          :items="abilities"
          item-title="name"
          item-value="code"
          label="Способность"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <ClampedNumberField
          :model-value="inner.min_level ?? 1"
          @update:model-value="patch('min_level', $event)"
          label="Ур. (мин)"
          :min="1"
          density="compact"
          hide-details
          style="min-width: 90px"
        />
      </template>

      <template v-else-if="inner.type === 'has_ability_keyword'">
        <v-autocomplete
          :model-value="inner.keyword_code"
          @update:model-value="patch('keyword_code', $event)"
          :items="abilityKeywords"
          item-title="name"
          item-value="code"
          label="Тег способностей"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <ClampedNumberField
          :model-value="inner.min_count"
          @update:model-value="patch('min_count', $event)"
          label="Кол-во"
          :min="1"
          density="compact"
          hide-details
          style="min-width: 90px"
        />
      </template>

      <template v-else-if="inner.type === 'has_keyword'">
        <v-autocomplete
          :model-value="inner.keyword_code"
          @update:model-value="patch('keyword_code', $event)"
          :items="keywords"
          item-title="name"
          item-value="code"
          label="Признак"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
      </template>

      <template v-else-if="inner.type === 'characteristic_value'">
        <v-autocomplete
          :model-value="inner.characteristic_code"
          @update:model-value="patch('characteristic_code', $event)"
          :items="characteristics"
          item-title="name"
          item-value="code"
          label="Характеристика"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <DimensionalNumberInput
          :model-value="inner.min"
          @update:model-value="(v) => patch('min', v)"
          label="Мин. значение"
          :min="3"
          :max="5"
          style="flex: 1 1 auto"
        />
      </template>

      <template v-else-if="inner.type === 'resource_limit'">
        <v-autocomplete
          :model-value="inner.resource_code"
          @update:model-value="patch('resource_code', $event)"
          :items="resources"
          item-title="name"
          item-value="code"
          label="Ресурс"
          density="compact"
          hide-details
          clearable
          class="flex-grow-1"
        />
        <DimensionalNumberInput
          v-if="selectedResourceIsDimensional"
          :model-value="(inner.min as DimensionalNumberValue | undefined) ?? { base: 0, size: 0 }"
          @update:model-value="(v) => patch('min', v)"
          label="Лимит (мин)"
          style="flex: 1 1 auto"
        />
        <ClampedNumberField
          v-else
          :model-value="typeof inner.min === 'number' ? inner.min : (inner.min?.base ?? 0)"
          @update:model-value="patch('min', $event)"
          label="Лимит (мин)"
          :min="0"
          density="compact"
          hide-details
          style="min-width: 110px"
        />
      </template>

      <v-btn v-if="removable" icon size="x-small" color="error" variant="text" class="mt-1" @click="emit('remove')">
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>

    <div v-if="inner.type === 'and' || inner.type === 'or'" class="ml-6 mt-2">
      <div v-for="(child, index) in inner.children" :key="index" class="mb-1">
        <RequirementNodeEditor
          v-model="inner.children[index]"
          :characteristics="characteristics"
          :resources="resources"
          :abilities="abilities"
          :keywords="keywords"
          :ability-keywords="abilityKeywords"
          removable
          @remove="removeChild(index)"
        />
      </div>
      <v-btn variant="text" color="primary" size="small" @click="addChild">
        <v-icon start>mdi-plus</v-icon>
        Добавить условие
      </v-btn>
    </div>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
