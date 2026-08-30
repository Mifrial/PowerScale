<script setup lang="ts">
import { computed } from 'vue';
import type { ActionComponent } from '@/modules/Roleplay/Rule/Dto/Ability/ActionComponent';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { KeywordRef } from '@/modules/Roleplay/Rule/Dto/Ability/KeywordRef';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import { ACTION_POINTS_RESOURCE_CODE } from '@/modules/Roleplay/Rule/Constant/Ability/ACTION_POINTS_RESOURCE_CODE';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';

const props = defineProps<{
  modelValue: ActionComponent[];
  resources: ResourceRef[];
  items: { code: string; name: string }[];
  keywords: KeywordRef[];
  isSpell: boolean;
}>();

const emit = defineEmits<{
  'update:modelValue': [value: ActionComponent[]];
}>();

const { inner } = useVModelSync<ActionComponent[]>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: true,
});

const typeOptions = [
  { label: 'Ресурс', value: 'resource' },
  { label: 'Вербальный', value: 'verbal' },
  { label: 'Соматический', value: 'somatic' },
  { label: 'Материальный', value: 'material' },
];

const actionPointIndex = computed(() =>
  inner.value.findIndex((c) => c.type === 'resource' && c.resource_code === ACTION_POINTS_RESOURCE_CODE),
);

function isMandatory(index: number): boolean {
  return index === actionPointIndex.value;
}

function updateType(index: number, type: string) {
  inner.value = abilitySpecService.updateActionComponent(
    inner.value,
    index,
    abilitySpecService.createEmptyActionComponent(type as ActionComponent['type']),
  );
}

function patch(index: number, key: string, value: unknown) {
  inner.value = abilitySpecService.patchActionComponent(inner.value, index, key, value);
}

function remove(index: number) {
  inner.value = abilitySpecService.removeActionComponent(inner.value, index);
}

function add(type: string) {
  inner.value = abilitySpecService.addActionComponent(inner.value, type as ActionComponent['type']);
}

function isDimensional(component: ActionComponent): boolean {
  if (component.type !== 'resource' || !component.resource_code) return false;

  return props.resources.find((r) => r.code === component.resource_code)?.isDimensional ?? false;
}

function isChosenAmount(component: ActionComponent): boolean {
  return (
    component.type === 'resource' &&
    typeof component.amount === 'object' &&
    'type' in component.amount &&
    component.amount.type === 'chosen'
  );
}

function updateResource(index: number, key: 'resource_code' | 'amount', value: unknown) {
  let comp = { ...inner.value[index], [key]: value } as ActionComponent;
  if (key === 'resource_code' && comp.type === 'resource') {
    const code = value as string;
    const isDim = props.resources.find((r) => r.code === code)?.isDimensional ?? false;
    if (isDim && typeof comp.amount === 'number') {
      comp = { ...comp, amount: { base: comp.amount, size: 0 } };
    } else if (
      !isDim &&
      comp.amount &&
      typeof comp.amount === 'object' &&
      !Array.isArray(comp.amount) &&
      'base' in comp.amount
    ) {
      comp = { ...comp, amount: comp.amount.base };
    }
    if (code === ACTION_POINTS_RESOURCE_CODE && props.isSpell) {
      comp = { ...comp, label: 'Сотворение' };
    }
  }
  inner.value = abilitySpecService.updateActionComponent(inner.value, index, comp);
}

function materialComponent(component: ActionComponent): Extract<ActionComponent, { type: 'material' }> | null {
  return component.type === 'material' ? component : null;
}

function materialTarget(component: ActionComponent): 'item' | 'tags' {
  if (component.type === 'material' && component.keyword_codes?.length) return 'tags';

  return 'item';
}

function switchMaterialTarget(index: number, mode: string) {
  if (mode === 'tags') patchMaterialTags(index, []);
  else patchMaterialItem(index, undefined);
}

function patchMaterialItem(index: number, code: string | null | undefined) {
  const comp = materialComponent(inner.value[index]);
  if (!comp) return;
  inner.value = abilitySpecService.updateActionComponent(inner.value, index, {
    ...comp,
    item_code: code ?? undefined,
    keyword_codes: undefined,
  });
}

function patchMaterialTags(index: number, codes: string[]) {
  const comp = materialComponent(inner.value[index]);
  if (!comp) return;
  inner.value = abilitySpecService.updateActionComponent(inner.value, index, {
    ...comp,
    keyword_codes: codes,
    item_code: undefined,
  });
}
</script>

<template>
  <div>
    <div class="text-body-2 text-medium-emphasis mb-2">
      Любое действие стоит минимум 1 ОД. {{ isSpell ? 'У заклинаний ОД называется «Сотворение».' : '' }}
    </div>

    <div v-for="(component, index) in inner" :key="`ac-${index}`" class="pa-1 mb-2 rounded bg-accent">
      <div class="bg-surface rounded pa-2">
        <div class="d-flex align-center mb-1">
          <v-select
            :model-value="component.type"
            @update:model-value="(v) => updateType(index, v)"
            :items="typeOptions"
            item-title="label"
            item-value="value"
            label="Компонент действия"
            density="compact"
            hide-details
            :disabled="isMandatory(index)"
            class="flex-grow-1"
          />
          <v-btn
            icon
            size="x-small"
            color="error"
            variant="text"
            class="ml-2"
            :disabled="isMandatory(index)"
            @click="remove(index)"
          >
            <v-icon>mdi-delete</v-icon>
          </v-btn>
        </div>

        <template v-if="component.type === 'resource'">
          <div class="d-flex gap-2 mb-1">
            <v-autocomplete
              :model-value="component.resource_code"
              @update:model-value="(v) => updateResource(index, 'resource_code', v)"
              :items="resources"
              item-title="name"
              item-value="code"
              label="Ресурс"
              density="compact"
              hide-details
              :clearable="!isMandatory(index)"
              :disabled="isMandatory(index)"
              class="flex-grow-1"
            />
            <v-text-field
              v-if="isChosenAmount(component)"
              model-value="Выбирается игроком"
              label="Стоимость"
              density="compact"
              hide-details
              disabled
              style="min-width: 120px"
            />
            <DimensionalNumberInput
              v-else-if="isDimensional(component)"
              :model-value="(component.amount as DimensionalNumberValue | undefined) ?? { base: 0, size: 0 }"
              @update:model-value="(v) => updateResource(index, 'amount', v)"
              label="Стоимость"
              style="flex: 1 1 auto"
            />
            <ClampedNumberField
              v-else
              :model-value="typeof component.amount === 'number' ? component.amount : 0"
              @update:model-value="(v) => updateResource(index, 'amount', v)"
              label="Стоимость"
              :min="1"
              density="compact"
              hide-details
              style="min-width: 120px"
            />
          </div>
        </template>

        <template v-else-if="component.type === 'verbal' || component.type === 'somatic'">
          <v-text-field
            :model-value="component.note ?? ''"
            @update:model-value="(v) => patch(index, 'note', v || undefined)"
            label="Приписка (например «крик» → «Вербальный (крик)»)"
            density="compact"
            hide-details
            class="mt-1"
          />
        </template>

        <template v-else-if="component.type === 'material'">
          <div class="d-flex gap-2 mt-1">
            <v-select
              :model-value="component.mode"
              @update:model-value="(v) => patch(index, 'mode', v)"
              :items="[
                { label: 'Израсходовать', value: 'consume' },
                { label: 'Использовать', value: 'use' },
              ]"
              item-title="label"
              item-value="value"
              label="Режим"
              density="compact"
              hide-details
              style="min-width: 150px"
            />
            <v-select
              :model-value="materialTarget(component)"
              @update:model-value="(v) => switchMaterialTarget(index, v)"
              :items="[
                { label: 'Предмет', value: 'item' },
                { label: 'По признакам', value: 'tags' },
              ]"
              item-title="label"
              item-value="value"
              label="Способ указания"
              density="compact"
              hide-details
              style="min-width: 150px"
            />
          </div>
          <v-autocomplete
            v-if="materialTarget(component) === 'item'"
            :model-value="component.item_code"
            @update:model-value="(v) => patchMaterialItem(index, v)"
            :items="items"
            item-title="name"
            item-value="code"
            label="Предмет (Item)"
            density="compact"
            hide-details
            clearable
            class="mt-1"
          />
          <v-autocomplete
            v-else
            :model-value="component.keyword_codes"
            @update:model-value="(v) => patchMaterialTags(index, v)"
            :items="keywords"
            item-title="name"
            item-value="code"
            label="Признаки предмета"
            density="compact"
            hide-details
            multiple
            chips
            closable-chips
            class="mt-1"
          />
          <v-text-field
            :model-value="component.description ?? ''"
            @update:model-value="(v) => patch(index, 'description', v || undefined)"
            label="Описание материала"
            density="compact"
            hide-details
            class="mt-1"
          />
        </template>
      </div>
    </div>

    <v-menu>
      <template #activator="{ props: menuProps }">
        <v-btn v-bind="menuProps" variant="text" color="primary" size="small">
          <v-icon start>mdi-plus</v-icon>
          Добавить компонент
        </v-btn>
      </template>
      <v-list density="compact" min-width="220">
        <v-list-item
          v-for="option in typeOptions"
          :key="option.value"
          :title="option.label"
          @click="add(option.value)"
        />
      </v-list>
    </v-menu>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
