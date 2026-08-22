<script setup lang="ts">
import { computed, watch } from 'vue';
import FormulaInput from '@/modules/Roleplay/Rule/Component/FormulaInput.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import { abilitySpecService } from '@/modules/Roleplay/Rule/Service/Instance/abilitySpecService';
import { useVModelSync } from '@/modules/Core/UI/Composables/useVModelSync';
import { GRANT_TYPES } from '@/modules/Roleplay/Rule/Constant/Ability/GRANT_TYPES';
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
  sources: SourceRef[];
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
