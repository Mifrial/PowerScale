<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import FormulaInput from '@/modules/Roleplay/Rule/Component/FormulaInput.vue';
import type { DimensionalFormula } from '@/modules/Roleplay/Rule/Dto/Ability/DimensionalFormula';
import type { DistanceValue } from '@/modules/Roleplay/Rule/Dto/Item/DistanceValue';
import type { WeaponProfile } from '@/modules/Roleplay/Rule/Dto/Item/WeaponProfile';

const props = defineProps<{
  modelValue: WeaponProfile;
  damageTypes: { code: string; name: string }[];
  characteristics: { code: string; name: string }[];
}>();

const emit = defineEmits<{
  'update:modelValue': [value: WeaponProfile];
  remove: [];
}>();

const localProfile = ref<WeaponProfile>({ ...props.modelValue });

const strengthCode = computed(() => {
  const found = props.characteristics.find((c) => c.name === 'Сила');

  return found ? found.code : '';
});

const distanceTypes = [
  { title: 'Ипари', value: 'ipari' },
  { title: 'Шаги', value: 'step' },
];

function isDefaultFormula(f: DimensionalFormula): boolean {
  if (f.type === 'fixed' && f.value === 0) return true;
  if (f.type === 'characteristic' && (!f.characteristic_code || f.characteristic_code === '') && f.modifier === 0)
    return true;
  if (f.type === 'actionCharacteristic' && !f.characteristic && f.modifier.every((entry) => entry.delta === 0))
    return true;

  return false;
}

function isDefaultDistance(value: DistanceValue): boolean {
  return isDefaultFormula(value.value);
}

function distance(type: 'ipari' | 'step', formula: DimensionalFormula): DistanceValue {
  return { type, value: formula };
}

function defaultFalloff(): DistanceValue {
  return distance('ipari', { type: 'fixed', value: 5 });
}

function ensureFalloff(profile: WeaponProfile): void {
  if (profile.type === 'strike') {
    profile.falloff = null;

    return;
  }
  if (!profile.falloff) {
    profile.falloff = defaultFalloff();
  }
}

function setDodgeBenefit(value: number | null): void {
  if (value === null) {
    delete localProfile.value.dodge_benefit;

    return;
  }
  localProfile.value.dodge_benefit = value;
}

watch(
  () => props.modelValue,
  (value) => {
    if (JSON.stringify(value) !== JSON.stringify(localProfile.value)) {
      localProfile.value = { ...value };
    }
  },
  { deep: true },
);

watch(
  () => props.modelValue.type,
  (newType) => {
    const profile = localProfile.value;
    if (newType === 'shoot') {
      if (isDefaultDistance(profile.distance.min)) {
        profile.distance.min = distance('ipari', { type: 'fixed', value: 0 });
      }
      if (profile.distance.max === null || isDefaultDistance(profile.distance.max)) {
        profile.distance.max = distance('ipari', { type: 'fixed', value: 10 });
      }
      ensureFalloff(profile);
    } else if (newType === 'throw') {
      if (isDefaultDistance(profile.distance.min)) {
        profile.distance.min = distance('ipari', {
          type: 'actionCharacteristic',
          action: 'throw',
          characteristic: strengthCode.value,
          modifier: [{ delta: 0, source_code: null, source_label: null }],
        });
      }
      profile.distance.max = null;
      ensureFalloff(profile);
    } else {
      if (isDefaultDistance(profile.distance.min)) {
        profile.distance.min = distance('ipari', { type: 'fixed', value: 0 });
      }
      if (profile.distance.max === null || isDefaultDistance(profile.distance.max)) {
        profile.distance.max = distance('ipari', { type: 'fixed', value: 0 });
      }
      profile.falloff = null;
    }
    const withAction = (formula: DimensionalFormula): DimensionalFormula =>
      formula.type === 'actionCharacteristic' && formula.action !== newType ? { ...formula, action: newType } : formula;
    profile.distance.min.value = withAction(profile.distance.min.value);
    if (profile.distance.max) profile.distance.max.value = withAction(profile.distance.max.value);
    if (profile.falloff) profile.falloff.value = withAction(profile.falloff.value);
    profile.damage.formula = withAction(profile.damage.formula);
    profile.penetration = withAction(profile.penetration);
  },
  { immediate: true },
);

watch(
  localProfile,
  (value) => {
    emit('update:modelValue', { ...value });
  },
  { deep: true },
);
</script>

<template>
  <div>
    <div class="mb-2">
      <label class="text-caption text-medium-emphasis">Дистанция от</label>
      <div class="d-flex gap-2">
        <v-select
          v-model="localProfile.distance.min.type"
          :items="distanceTypes"
          density="compact"
          hide-details
          style="max-width: 120px"
        />
        <div style="flex: 1 1 auto">
          <FormulaInput
            v-model="localProfile.distance.min.value"
            :characteristics="characteristics"
            :modes="['fixed', 'actionCharacteristic', 'dimensional']"
            :action="localProfile.type"
          />
        </div>
      </div>
    </div>

    <div v-if="localProfile.type !== 'throw' && localProfile.distance.max" class="mb-2">
      <label class="text-caption text-medium-emphasis">Дистанция до</label>
      <div class="d-flex gap-2">
        <v-select
          v-model="localProfile.distance.max.type"
          :items="distanceTypes"
          density="compact"
          hide-details
          style="max-width: 120px"
        />
        <div style="flex: 1 1 auto">
          <FormulaInput
            v-model="localProfile.distance.max.value"
            :characteristics="characteristics"
            :modes="['fixed', 'actionCharacteristic', 'dimensional']"
            :action="localProfile.type"
          />
        </div>
      </div>
    </div>

    <div v-if="localProfile.type === 'throw' || localProfile.type === 'shoot'" class="mb-2">
      <label class="text-caption text-medium-emphasis">Дальнобойность</label>
      <div v-if="localProfile.falloff" class="d-flex gap-2">
        <v-select
          v-model="localProfile.falloff.type"
          :items="distanceTypes"
          density="compact"
          hide-details
          style="max-width: 120px"
        />
        <div style="flex: 1 1 auto">
          <FormulaInput
            v-model="localProfile.falloff.value"
            :characteristics="characteristics"
            :modes="['fixed', 'actionCharacteristic', 'dimensional']"
            :action="localProfile.type"
          />
        </div>
      </div>
    </div>

    <v-card class="mt-2 pa-3" variant="outlined">
      <v-card-title class="text-subtitle-2">Урон</v-card-title>
      <div class="d-flex gap-2">
        <div style="flex: 1 1 auto">
          <FormulaInput
            v-model="localProfile.damage.formula"
            :characteristics="characteristics"
            :modes="['fixed', 'actionCharacteristic', 'dimensional']"
            :action="localProfile.type"
          />
        </div>
        <v-autocomplete
          v-model="localProfile.damage.damage_type_code"
          :items="damageTypes"
          item-title="name"
          item-value="code"
          label="Тип урона"
          density="compact"
          hide-details
          clearable
          style="min-width: 150px"
        />
      </div>
    </v-card>

    <v-card class="mt-2 pa-3" variant="outlined">
      <v-card-title class="text-subtitle-2">Пробитие</v-card-title>
      <FormulaInput
        v-model="localProfile.penetration"
        :characteristics="characteristics"
        :modes="['fixed', 'actionCharacteristic', 'dimensional']"
        :action="localProfile.type"
      />
    </v-card>

    <DimensionalNumberInput v-model="localProfile.accuracy" label="Точность" class="mt-2" />

    <ClampedNumberField
      class="mt-2"
      label="Польза уклонения"
      :model-value="localProfile.dodge_benefit ?? null"
      nullable
      @update:model-value="setDodgeBenefit"
    />

    <v-btn color="error" variant="text" @click="emit('remove')" class="mt-2">
      <v-icon start>mdi-delete</v-icon>
      Удалить профиль
    </v-btn>
  </div>
</template>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
