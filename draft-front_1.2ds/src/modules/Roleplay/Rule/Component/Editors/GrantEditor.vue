<script setup lang="ts">
import { computed, watch } from 'vue';
import FormulaInput from '@/modules/Roleplay/Rule/Component/FormulaInput.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import { GRANT_TYPES } from '@/modules/Roleplay/Rule/Constant/Ability/GRANT_TYPES';
import { SENSE_STATUS_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Sense/SENSE_STATUS_OPTIONS';
import { LIGHTING_LEVEL_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Lighting/LIGHTING_LEVEL_OPTIONS';
import { MAGIC_STUDY_SCOPE_OPTIONS } from '@/modules/Roleplay/Rule/Constant/Ability/MAGIC_STUDY_SCOPE_OPTIONS';
import type { Grant } from '@/modules/Roleplay/Rule/Dto/Ability/Grant';
import type { CharacteristicRef } from '@/modules/Roleplay/Rule/Dto/Ability/CharacteristicRef';
import type { ResourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/ResourceRef';
import type { AbilityRef } from '@/modules/Roleplay/Rule/Dto/Ability/AbilityRef';
import type { KeywordRef } from '@/modules/Roleplay/Rule/Dto/Ability/KeywordRef';
import type { SourceRef } from '@/modules/Roleplay/Rule/Dto/Ability/SourceRef';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

const props = defineProps<{
  modelValue: Grant;
  characteristics: CharacteristicRef[];
  resources: ResourceRef[];
  abilities: AbilityRef[];
  keywords: KeywordRef[];
  items: { code: string; name: string }[];
  magicPaths: { code: string; name: string }[];
  sources: SourceRef[];
  damageTypes: { code: string; name: string }[];
  senses: { code: string; name: string }[];
  states: { code: string; name: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: Grant];
  remove: [];
}>();

const { inner } = useVModelSync<Grant>({
  modelValue: () => props.modelValue,
  onCommit: (value) => emit('update:modelValue', value),
  clone: true,
});

const selectedResourceIsDimensional = computed(() => {
  const v = inner.value;
  if (v.type !== 'resource') return false;

  return props.resources.find((r) => r.code === v.resource_code)?.isDimensional ?? false;
});

watch(selectedResourceIsDimensional, () => {
  inner.value = abilitySpecService.normalizeGrantLimit(inner.value, props.resources);
});

function updateType(type: string) {
  inner.value = abilitySpecService.createEmptyGrant(type as Grant['type'], props.sources[0]?.code ?? '');
}

function patch(key: string, value: unknown) {
  inner.value = { ...inner.value, [key]: value } as Grant;
}
</script>

<template>
  <v-card variant="outlined" class="pa-2">
    <div class="d-flex gap-2 align-center mb-1">
      <v-select
        :model-value="inner.type"
        @update:model-value="updateType"
        :items="GRANT_TYPES"
        item-title="label"
        item-value="value"
        label="Дар"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
      <v-btn icon size="small" color="error" variant="text" @click="emit('remove')">
        <v-icon>mdi-delete</v-icon>
      </v-btn>
    </div>

    <div class="d-flex align-center mb-1">
      <v-checkbox
        :model-value="inner.permanent !== false"
        @update:model-value="(v) => patch('permanent', v ? true : false)"
        label="Постоянный"
        density="compact"
        hide-details
      />
      <div class="text-body-2 text-medium-emphasis">
        {{ inner.permanent !== false ? 'Действует на всех уровнях ≥ этого' : 'Только на этом уровне' }}
      </div>
    </div>

    <div class="grant-fields">
      <template v-if="inner.type === 'characteristic'">
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
        />
        <DimensionalNumberInput
          :model-value="inner.value"
          @update:model-value="(v) => patch('value', v)"
          label="Значение"
          :min="3"
          :max="5"
        />
      </template>

      <template v-else-if="inner.type === 'characteristic_parameter'">
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
        />
        <v-text-field
          :model-value="inner.parameter_code"
          @update:model-value="patch('parameter_code', $event)"
          label="Код параметра"
          density="compact"
          hide-details
        />
        <ClampedNumberField
          :model-value="inner.per_unit"
          @update:model-value="patch('per_unit', $event)"
          label="На единицу параметра"
          :min="0"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'characteristic_modify'">
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
        />
        <FormulaInput
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          :characteristics="characteristics"
          :abilities="abilities"
          :modes="['fixed', 'ability_level']"
        />
        <v-select
          :model-value="inner.source_code || null"
          @update:model-value="patch('source_code', $event)"
          :items="sources"
          item-title="name"
          item-value="code"
          label="Источник модификатора"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'resource'">
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
        />
        <DimensionalNumberInput
          v-if="selectedResourceIsDimensional"
          :model-value="(inner.limit as DimensionalNumberValue | undefined) ?? { base: 0, size: 0 }"
          @update:model-value="(v) => patch('limit', v)"
          label="Лимит"
        />
        <ClampedNumberField
          v-else
          :model-value="typeof inner.limit === 'number' ? inner.limit : (inner.limit?.base ?? 0)"
          @update:model-value="patch('limit', $event)"
          label="Лимит"
          :min="0"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'resource_limit_change'">
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
        />
        <FormulaInput
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          :characteristics="characteristics"
          :abilities="abilities"
        />
        <v-select
          :model-value="inner.source_code || null"
          @update:model-value="patch('source_code', $event)"
          :items="sources"
          item-title="name"
          item-value="code"
          label="Источник модификатора"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'ability'">
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
        />
      </template>

      <template v-else-if="inner.type === 'keyword'">
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
        />
        <v-switch
          :model-value="inner.remove"
          @update:model-value="patch('remove', $event)"
          label="Убрать"
          hide-details
          density="compact"
        />
      </template>

      <template v-else-if="inner.type === 'item'">
        <v-autocomplete
          :model-value="inner.item_code"
          @update:model-value="patch('item_code', $event)"
          :items="items"
          item-title="name"
          item-value="code"
          label="Предмет"
          density="compact"
          hide-details
          clearable
        />
        <ClampedNumberField
          :model-value="inner.quantity ?? 1"
          :min="1"
          @update:model-value="patch('quantity', $event)"
          label="Количество"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'magic_path'">
        <v-autocomplete
          :model-value="inner.path_code"
          @update:model-value="patch('path_code', $event)"
          :items="magicPaths"
          item-title="name"
          item-value="code"
          label="Путь волшебства"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'magic_study'">
        <v-select
          :model-value="inner.scope"
          @update:model-value="patch('scope', $event)"
          :items="MAGIC_STUDY_SCOPE_OPTIONS"
          item-title="title"
          item-value="value"
          label="Что открывает"
          density="compact"
          hide-details
        />
        <ClampedNumberField
          :model-value="inner.max_cost"
          :min="0"
          @update:model-value="patch('max_cost', $event)"
          label="Максимальная стоимость"
          density="compact"
          hide-details
        />
        <v-autocomplete
          :model-value="inner.path_code ?? null"
          @update:model-value="patch('path_code', $event || undefined)"
          :items="magicPaths"
          item-title="name"
          item-value="code"
          label="Путь изучения (без выдачи пути)"
          density="compact"
          hide-details
          clearable
        />
        <v-checkbox
          :model-value="inner.max_instances === 1"
          @update:model-value="patch('max_instances', $event ? 1 : undefined)"
          label="Только одно"
          density="compact"
          hide-details
        />
        <v-checkbox
          :model-value="inner.paid_cost === 0"
          @update:model-value="patch('paid_cost', $event ? 0 : undefined)"
          label="Бесплатно"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'skill_study'">
        <v-autocomplete
          :model-value="inner.ability_codes"
          @update:model-value="patch('ability_codes', $event ?? [])"
          :items="abilities"
          item-title="name"
          item-value="code"
          label="Навыки"
          density="compact"
          multiple
          chips
          closable-chips
          hide-details
        />
        <ClampedNumberField
          :model-value="inner.max_level"
          :min="1"
          @update:model-value="patch('max_level', $event)"
          label="Бесплатных уровней"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'resistance'">
        <v-autocomplete
          :model-value="inner.damage_type_code"
          @update:model-value="patch('damage_type_code', $event)"
          :items="damageTypes"
          item-title="name"
          item-value="code"
          label="Тип урона"
          density="compact"
          hide-details
          clearable
        />
        <DimensionalNumberInput
          :model-value="
            inner.value && typeof inner.value === 'object' && 'base' in inner.value ? inner.value : { base: 1, size: 0 }
          "
          @update:model-value="(v) => patch('value', v)"
          label="Сопротивление"
        />
        <v-select
          :model-value="inner.source_code || null"
          @update:model-value="patch('source_code', $event)"
          :items="sources"
          item-title="name"
          item-value="code"
          label="Источник"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'sense_modify'">
        <v-autocomplete
          :model-value="inner.sense_code"
          @update:model-value="patch('sense_code', $event)"
          :items="senses"
          item-title="name"
          item-value="code"
          label="Чувство"
          density="compact"
          hide-details
          clearable
        />
        <FormulaInput
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          :characteristics="characteristics"
          :abilities="abilities"
          :modes="['fixed', 'ability_level']"
        />
        <v-select
          :model-value="inner.status ?? null"
          @update:model-value="patch('status', $event || undefined)"
          :items="SENSE_STATUS_OPTIONS"
          item-title="title"
          item-value="value"
          label="Статус чувства"
          density="compact"
          hide-details
          clearable
        />
        <v-select
          :model-value="inner.treat_as_good_down_to ?? null"
          :items="LIGHTING_LEVEL_OPTIONS"
          item-title="title"
          item-value="value"
          label="Как при хорошем освещении до"
          density="compact"
          hide-details
          clearable
          @update:model-value="patch('treat_as_good_down_to', $event || undefined)"
        />
        <v-select
          :model-value="inner.source_code || null"
          @update:model-value="patch('source_code', $event)"
          :items="sources"
          item-title="name"
          item-value="code"
          label="Источник"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'process_distance_multiplier'">
        <v-autocomplete
          :model-value="inner.ability_code"
          @update:model-value="patch('ability_code', $event)"
          :items="abilities"
          item-title="name"
          item-value="code"
          label="Процесс"
          density="compact"
          hide-details
          clearable
        />
        <ClampedNumberField
          :model-value="inner.multiplier"
          @update:model-value="patch('multiplier', $event)"
          label="Множитель"
          :min="1"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'state_modify'">
        <v-autocomplete
          :model-value="inner.state_code"
          @update:model-value="patch('state_code', $event)"
          :items="states"
          item-title="name"
          item-value="code"
          label="Состояние"
          density="compact"
          hide-details
          clearable
        />
        <FormulaInput
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          :characteristics="characteristics"
          :abilities="abilities"
        />
        <v-select
          :model-value="inner.source_code || null"
          @update:model-value="patch('source_code', $event)"
          :items="sources"
          item-title="name"
          item-value="code"
          label="Источник"
          density="compact"
          hide-details
          clearable
        />
      </template>

      <template v-else-if="inner.type === 'check_advantage'">
        <ClampedNumberField
          :model-value="inner.amount"
          @update:model-value="patch('amount', $event)"
          label="Преимущества"
          density="compact"
          hide-details
        />
        <v-text-field
          :model-value="inner.check_codes.join(', ')"
          @update:model-value="
            patch(
              'check_codes',
              String($event ?? '')
                .split(',')
                .map((code) => code.trim())
                .filter(Boolean),
            )
          "
          label="Коды проверок"
          density="compact"
          hide-details
        />
      </template>

      <template v-else-if="inner.type === 'money'">
        <ClampedNumberField
          :model-value="inner.fixed"
          :min="0"
          @update:model-value="patch('fixed', $event)"
          label="Сумма (гз)"
          density="compact"
          hide-details
        />
        <ClampedNumberField
          :model-value="inner.percent"
          :min="0"
          @update:model-value="patch('percent', $event)"
          label="Процент от лимита денег"
          suffix="%"
          density="compact"
          hide-details
        />
        <v-select
          :model-value="inner.apply"
          @update:model-value="patch('apply', $event)"
          :items="[
            { title: 'Большее из суммы и процента', value: 'max' },
            { title: 'Меньшее из суммы и процента', value: 'min' },
          ]"
          label="Сочетание"
          density="compact"
          hide-details
        />
      </template>
    </div>
  </v-card>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
.grant-fields {
  display: flex;
  flex-direction: column;
  gap: 8px;
}
</style>
