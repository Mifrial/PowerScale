<script setup lang="ts">
import { computed } from 'vue';
import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import type { ParameterValueKind } from '@/modules/Roleplay/Rule/Enum/Ability/ParameterValueKind';
import { DIMENSIONAL_FORMULA_MODES } from '@/modules/Roleplay/Rule/Constant/Ability/DIMENSIONAL_FORMULA_MODES';
import { formulaTypeItemsService } from '@/modules/Roleplay/Rule/Service/Instance/formulaTypeItemsService';

const props = withDefaults(
  defineProps<{
    modelValue: Formula | null;
    characteristics: { code: string; name: string }[];
    abilities?: { code: string; name: string }[];
    parameters?: { code: string; label: string; kind: ParameterValueKind }[];
    modes?: Formula['type'][];
    /** Действие для новой формулы actionCharacteristic (профили оружия). */
    action?: 'strike' | 'throw' | 'shoot';
  }>(),
  { action: 'strike' },
);

const emit = defineEmits<{
  'update:modelValue': [value: Formula | null];
}>();

const currentType = computed<Formula['type']>(() => props.modelValue?.type ?? 'fixed');

const fixedModel = computed(() => (props.modelValue?.type === 'fixed' ? props.modelValue : null));

const characteristicModel = computed(() => (props.modelValue?.type === 'characteristic' ? props.modelValue : null));

const abilityLevelModel = computed(() => (props.modelValue?.type === 'ability_level' ? props.modelValue : null));

const dimensionalModel = computed(() => (props.modelValue?.type === 'dimensional' ? props.modelValue : null));

const sizePositiveModel = computed(() =>
  props.modelValue?.type === 'characteristic_size_positive' ? props.modelValue : null,
);

const toScalarModel = computed(() => (props.modelValue?.type === 'to_scalar' ? props.modelValue : null));

const sizeModel = computed(() => (props.modelValue?.type === 'characteristic_size' ? props.modelValue : null));

const sizeGapModel = computed(() => (props.modelValue?.type === 'characteristic_size_gap' ? props.modelValue : null));

const parameterModel = computed(() => (props.modelValue?.type === 'parameter' ? props.modelValue : null));

const floorDivModel = computed(() => (props.modelValue?.type === 'parameter_floor_div' ? props.modelValue : null));

const scalarParameters = computed(() => (props.parameters ?? []).filter((parameter) => parameter.kind === 'scalar'));

const actionCharacteristicModel = computed(() =>
  props.modelValue?.type === 'actionCharacteristic' ? props.modelValue : null,
);

const actionCharacteristicDelta = computed(() =>
  actionCharacteristicModel.value
    ? actionCharacteristicModel.value.modifier.reduce((sum, entry) => sum + entry.delta, 0)
    : 0,
);

const formulaTypes = computed(() =>
  formulaTypeItemsService.formulaTypeItems(props.modelValue?.type, props.modes, Boolean(props.abilities?.length)),
);

function emptyActionCharacteristic(): Formula {
  return {
    type: 'actionCharacteristic',
    action: props.action,
    characteristic: '',
    modifier: [{ delta: 0, source_code: null, source_label: null }],
  };
}

function updateType(type: string) {
  if (type === 'fixed') emit('update:modelValue', { type: 'fixed', value: 0 });
  else if (type === 'characteristic') {
    emit('update:modelValue', { type: 'characteristic', characteristic_code: '', modifier: 0 });
  } else if (type === 'actionCharacteristic') emit('update:modelValue', emptyActionCharacteristic());
  else if (type === 'ability_level') {
    emit('update:modelValue', { type: 'ability_level', ability_code: '', multiplier: 1, offset: 0 });
  } else if (type === 'dimensional') emit('update:modelValue', { type: 'dimensional', base: 3, size: 0 });
  else if (type === 'parameter') emit('update:modelValue', { type: 'parameter', parameter_code: '', per_unit: 1 });
  else if (type === 'parameter_floor_div') {
    emit('update:modelValue', { type: 'parameter_floor_div', parameter_code: '', divisor: 2 });
  } else if (type === 'to_scalar') {
    emit('update:modelValue', {
      type: 'to_scalar',
      value: { type: 'characteristic', characteristic_code: '', modifier: 0 },
    });
  } else if (type === 'characteristic_size') {
    emit('update:modelValue', { type: 'characteristic_size', characteristic_code: '' });
  } else if (type === 'characteristic_size_positive') {
    emit('update:modelValue', { type: 'characteristic_size_positive', characteristic_code: '' });
  } else if (type === 'characteristic_size_gap') {
    emit('update:modelValue', {
      type: 'characteristic_size_gap',
      characteristic_code_from: '',
      characteristic_code_to: '',
    });
  }
}

function updateValue(val: string) {
  emit('update:modelValue', { type: 'fixed', value: Number(val) || 0 });
}

function updateCharacteristicCode(characteristic_code: string | null) {
  const current = props.modelValue;
  if (current?.type === 'characteristic') {
    emit('update:modelValue', {
      type: 'characteristic',
      characteristic_code: characteristic_code ?? '',
      modifier: current.modifier,
    });
  }
}

function updateModifier(val: string) {
  const current = props.modelValue;
  if (current?.type === 'characteristic') {
    emit('update:modelValue', {
      type: 'characteristic',
      characteristic_code: current.characteristic_code,
      modifier: Number(val) || 0,
    });
  }
}

function updateAbilityCode(ability_code: string | null) {
  const current = props.modelValue;
  if (current?.type === 'ability_level') {
    emit('update:modelValue', {
      type: 'ability_level',
      ability_code: ability_code ?? '',
      multiplier: current.multiplier,
      offset: current.offset,
    });
  }
}

function updateAbilityMultiplier(val: string) {
  const current = props.modelValue;
  if (current?.type === 'ability_level') {
    emit('update:modelValue', {
      type: 'ability_level',
      ability_code: current.ability_code,
      multiplier: Number(val) || 1,
      offset: current.offset,
    });
  }
}

function updateAbilityOffset(val: string) {
  const current = props.modelValue;
  if (current?.type === 'ability_level') {
    emit('update:modelValue', {
      type: 'ability_level',
      ability_code: current.ability_code,
      multiplier: current.multiplier,
      offset: Number(val) || 0,
    });
  }
}

function updateDimensionalBase(val: string) {
  const current = props.modelValue;
  if (current?.type === 'dimensional') {
    emit('update:modelValue', {
      type: 'dimensional',
      base: Number(val) || 3,
      size: current.size,
    });
  }
}

function updateDimensionalSize(val: string) {
  const current = props.modelValue;
  if (current?.type === 'dimensional') {
    emit('update:modelValue', {
      type: 'dimensional',
      base: current.base,
      size: Number(val) || 0,
    });
  }
}

function updateActionCharacteristicCode(characteristic: string | null) {
  const current = props.modelValue;
  if (current?.type !== 'actionCharacteristic') return;
  emit('update:modelValue', { ...current, characteristic: characteristic ?? '' });
}

function updateSizePositiveCode(characteristic_code: string | null) {
  emit('update:modelValue', {
    type: 'characteristic_size_positive',
    characteristic_code: characteristic_code ?? '',
  });
}

function updateActionCharacteristicDelta(val: string) {
  const current = props.modelValue;
  if (current?.type !== 'actionCharacteristic') return;
  emit('update:modelValue', {
    ...current,
    modifier: [{ delta: Number(val) || 0, source_code: null, source_label: null }],
  });
}

function updateActionCharacteristicMultiplier(val: string) {
  const current = props.modelValue;
  if (current?.type !== 'actionCharacteristic') return;
  const multiplier = Number(val);
  emit('update:modelValue', {
    ...current,
    multiplier: Number.isFinite(multiplier) && multiplier !== 0 ? multiplier : undefined,
  });
}

function updateParameterCode(parameter_code: string | null) {
  const current = props.modelValue;
  if (current?.type === 'parameter' && 'per_unit' in current) {
    emit('update:modelValue', { ...current, parameter_code: parameter_code ?? '' });
  }
  if (current?.type === 'parameter_floor_div') {
    emit('update:modelValue', { ...current, parameter_code: parameter_code ?? '' });
  }
}

function updatePerUnit(val: string) {
  const current = props.modelValue;
  if (current?.type !== 'parameter' || !('per_unit' in current)) return;
  emit('update:modelValue', { ...current, per_unit: Number(val) || 0 });
}

function updateDivisor(val: string) {
  const current = props.modelValue;
  if (current?.type !== 'parameter_floor_div') return;
  emit('update:modelValue', { ...current, divisor: Number(val) || 0 });
}

function isDimensionalFormula(value: Formula): value is DimensionalFormula {
  return (
    value.type === 'fixed' ||
    value.type === 'dimensional' ||
    value.type === 'characteristic' ||
    value.type === 'actionCharacteristic'
  );
}

function updateToScalar(value: Formula | null) {
  if (!value || !isDimensionalFormula(value)) return;
  emit('update:modelValue', { type: 'to_scalar', value });
}

function updateSizeCode(characteristic_code: string | null) {
  emit('update:modelValue', { type: 'characteristic_size', characteristic_code: characteristic_code ?? '' });
}

function updateSizeGap(side: 'characteristic_code_from' | 'characteristic_code_to', code: string | null) {
  const current = props.modelValue;
  if (current?.type !== 'characteristic_size_gap') return;
  emit('update:modelValue', { ...current, [side]: code ?? '' });
}
</script>

<template>
  <div class="d-flex ga-1 align-start">
    <v-select
      :model-value="currentType"
      @update:model-value="updateType"
      :items="formulaTypes"
      item-title="label"
      item-value="value"
      label="Тип"
      density="compact"
      hide-details
      style="min-width: 110px"
    />

    <v-text-field
      v-if="currentType === 'fixed'"
      :model-value="fixedModel?.value ?? 0"
      @update:model-value="updateValue"
      label="Значение"
      type="number"
      density="compact"
      hide-details
      style="flex: 1 1 auto"
    />

    <template v-if="currentType === 'characteristic'">
      <v-autocomplete
        :model-value="characteristicModel?.characteristic_code"
        @update:model-value="updateCharacteristicCode"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Характеристика"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
      <v-text-field
        :model-value="characteristicModel?.modifier ?? 0"
        @update:model-value="updateModifier"
        label="Модификатор"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px"
      />
    </template>

    <template v-if="currentType === 'actionCharacteristic'">
      <v-autocomplete
        :model-value="actionCharacteristicModel?.characteristic"
        @update:model-value="updateActionCharacteristicCode"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Характеристика"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
      <v-text-field
        :model-value="actionCharacteristicDelta"
        @update:model-value="updateActionCharacteristicDelta"
        label="Модификатор"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px"
      />
      <v-text-field
        :model-value="actionCharacteristicModel?.multiplier ?? ''"
        label="Множитель базы"
        type="number"
        density="compact"
        hide-details
        style="max-width: 90px"
        @update:model-value="updateActionCharacteristicMultiplier"
      />
    </template>

    <template v-if="currentType === 'ability_level'">
      <v-autocomplete
        :model-value="abilityLevelModel?.ability_code"
        @update:model-value="updateAbilityCode"
        :items="abilities"
        item-title="name"
        item-value="code"
        label="Способность"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
      <v-text-field
        :model-value="abilityLevelModel?.multiplier ?? 1"
        @update:model-value="updateAbilityMultiplier"
        label="Множитель"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px"
      />
      <v-text-field
        :model-value="abilityLevelModel?.offset ?? 0"
        @update:model-value="updateAbilityOffset"
        label="Сдвиг"
        type="number"
        density="compact"
        hide-details
        style="max-width: 80px"
      />
    </template>

    <template v-if="currentType === 'characteristic_size_positive'">
      <v-autocomplete
        :model-value="sizePositiveModel?.characteristic_code"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Характеристика"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
        @update:model-value="updateSizePositiveCode"
      />
    </template>

    <template v-if="currentType === 'parameter' && parameterModel && 'per_unit' in parameterModel">
      <v-autocomplete
        :model-value="parameterModel.parameter_code"
        :items="scalarParameters"
        item-title="label"
        item-value="code"
        label="Параметр"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
        @update:model-value="updateParameterCode"
      />
      <v-text-field
        :model-value="parameterModel.per_unit"
        label="На единицу"
        type="number"
        density="compact"
        hide-details
        style="max-width: 90px"
        @update:model-value="updatePerUnit"
      />
    </template>

    <template v-if="currentType === 'parameter_floor_div' && floorDivModel">
      <v-autocomplete
        :model-value="floorDivModel.parameter_code"
        :items="scalarParameters"
        item-title="label"
        item-value="code"
        label="Параметр"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
        @update:model-value="updateParameterCode"
      />
      <v-text-field
        :model-value="floorDivModel.divisor"
        label="Делитель"
        type="number"
        density="compact"
        hide-details
        style="max-width: 90px"
        @update:model-value="updateDivisor"
      />
    </template>

    <FormulaInput
      v-if="toScalarModel"
      :model-value="toScalarModel.value"
      :characteristics="characteristics"
      :abilities="abilities"
      :parameters="parameters"
      :modes="[...DIMENSIONAL_FORMULA_MODES]"
      :action="action"
      @update:model-value="updateToScalar"
    />

    <template v-if="currentType === 'characteristic_size'">
      <v-autocomplete
        :model-value="sizeModel?.characteristic_code"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Характеристика"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
        @update:model-value="updateSizeCode"
      />
    </template>

    <template v-if="currentType === 'characteristic_size_gap' && sizeGapModel">
      <v-autocomplete
        :model-value="sizeGapModel.characteristic_code_from"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Выше"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
        @update:model-value="updateSizeGap('characteristic_code_from', $event)"
      />
      <v-autocomplete
        :model-value="sizeGapModel.characteristic_code_to"
        :items="characteristics"
        item-title="name"
        item-value="code"
        label="Ниже"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
        @update:model-value="updateSizeGap('characteristic_code_to', $event)"
      />
    </template>

    <template v-if="currentType === 'dimensional'">
      <v-text-field
        :model-value="dimensionalModel?.base ?? 3"
        @update:model-value="updateDimensionalBase"
        label="База"
        type="number"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
      <v-text-field
        :model-value="dimensionalModel?.size ?? 0"
        @update:model-value="updateDimensionalSize"
        label="Размер"
        type="number"
        density="compact"
        hide-details
        style="flex: 1 1 auto"
      />
    </template>
  </div>
</template>

<style scoped>
.gap-1 {
  gap: 4px;
}
</style>
