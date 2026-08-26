<script setup lang="ts">
import { computed } from 'vue';
import type { Formula } from '@/modules/Roleplay/Rule/Dto/Ability/Formula';
import { formulaTypeItemsService } from '@/modules/Roleplay/Rule/Service/Instance/formulaTypeItemsService';

const props = withDefaults(
  defineProps<{
    modelValue: Formula | null;
    characteristics: { code: string; name: string }[];
    abilities?: { code: string; name: string }[];
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
  if (type === 'fixed') {
    emit('update:modelValue', { type: 'fixed', value: 0 });
  } else if (type === 'characteristic') {
    emit('update:modelValue', { type: 'characteristic', characteristic_code: '', modifier: 0 });
  } else if (type === 'actionCharacteristic') {
    emit('update:modelValue', emptyActionCharacteristic());
  } else if (type === 'ability_level') {
    emit('update:modelValue', { type: 'ability_level', ability_code: '', multiplier: 1, offset: 0 });
  } else {
    emit('update:modelValue', { type: 'dimensional', base: 3, size: 0 });
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

function updateActionCharacteristicDelta(val: string) {
  const current = props.modelValue;
  if (current?.type !== 'actionCharacteristic') return;
  emit('update:modelValue', {
    ...current,
    modifier: [{ delta: Number(val) || 0, source_code: null, source_label: null }],
  });
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
