<script setup lang="ts">
import { ref, watch } from 'vue';
import ClampedNumberField from '@/modules/Core/UI/Component/Input/ClampedNumberField.vue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { CharacterPoisonValue } from '@/modules/Roleplay/Character/Dto/CharacterPoisonValue';
import type { StatePeriodicity } from '@/modules/Roleplay/Rule/Dto/State/Periodicity';
import type { StateDecay } from '@/modules/Roleplay/Rule/Dto/State/StateDecay';
import type { CombatStateDetailRow } from '@/modules/Roleplay/Game/Dto/CombatStateDetailRow';
import type { CombatStateEditKind } from '@/modules/Roleplay/Game/Enum/CombatStateEditKind';

const props = withDefaults(
  defineProps<{
    name: string;
    iconCode?: string | null;
    leftLabel: string;
    valueLabel: string;
    details?: CombatStateDetailRow[];
    canEdit?: boolean;
    editKind?: CombatStateEditKind;
    current?: number;
    minValue?: number;
    dimensionalValue?: DimensionalNumberValue | null;
    poison?: CharacterPoisonValue | null;
    poisonItems?: { title: string; value: string }[];
    damageTypeItems?: { title: string; value: string }[];
    poisonTemplate?: (poisonRuleId: string | null) => CharacterPoisonValue;
  }>(),
  {
    iconCode: null,
    details: () => [],
    canEdit: false,
    editKind: 'none',
    current: 0,
    minValue: 0,
    dimensionalValue: null,
    poison: null,
    poisonItems: () => [],
    damageTypeItems: () => [],
  },
);

const emit = defineEmits<{
  apply: [next: number];
  applyDimensional: [next: DimensionalNumberValue];
  applyPoison: [next: CharacterPoisonValue];
  remove: [];
}>();

const menuOpen = ref(false);
const draft = ref(0);
const draftDim = ref<DimensionalNumberValue>({ base: 1, size: 0 });
const draftPoisonRuleId = ref('');
const draftDamageType = ref('');
const draftStrength = ref<DimensionalNumberValue>({ base: 1, size: 0 });
const draftPeriodicity = ref<StatePeriodicity | undefined>();
const draftDecay = ref<StateDecay | undefined>();

watch(menuOpen, (open) => {
  if (!open) return;
  draft.value = Math.max(props.minValue, props.current);
  draftDim.value = { ...(props.dimensionalValue ?? { base: 1, size: 0 }) };
  draftPoisonRuleId.value = props.poison?.poisonRuleId ?? '';
  draftDamageType.value = props.poison?.damage_type_code ?? '';
  draftStrength.value = { ...(props.poison?.strength ?? { base: 1, size: 0 }) };
  draftPeriodicity.value = props.poison?.periodicity;
  draftDecay.value = props.poison?.decay;
});

function submitNumeric(): void {
  if (draft.value === props.current) {
    menuOpen.value = false;

    return;
  }
  emit('apply', draft.value);
  menuOpen.value = false;
}

function submitDimensional(): void {
  emit('applyDimensional', { ...draftDim.value });
  menuOpen.value = false;
}

function submitPoison(): void {
  emit('applyPoison', {
    poisonRuleId: draftPoisonRuleId.value || null,
    damage_type_code: draftDamageType.value || undefined,
    strength: { ...draftStrength.value },
    periodicity: draftPeriodicity.value,
    decay: draftDecay.value,
  });
  menuOpen.value = false;
}

function onPoisonRuleChange(next: unknown): void {
  const id = typeof next === 'string' ? next : '';
  draftPoisonRuleId.value = id;
  const templated = props.poisonTemplate?.(id || null);
  if (!templated) return;
  draftDamageType.value = templated.damage_type_code ?? '';
  draftStrength.value = { ...(templated.strength ?? { base: 1, size: 0 }) };
  draftPeriodicity.value = templated.periodicity;
  draftDecay.value = templated.decay;
}

function submitRemove(): void {
  emit('remove');
  menuOpen.value = false;
}
</script>

<template>
  <v-menu v-model="menuOpen" location="right top" :close-on-content-click="false" :z-index="2200">
    <template #activator="{ props: menuProps }">
      <div v-bind="menuProps" class="combat-card-state" role="button" tabindex="0">
        <v-icon v-if="iconCode" :icon="iconCode" size="16" class="flex-shrink-0" />
        <span class="combat-card-state__label text-truncate">{{ leftLabel }}</span>
        <span class="combat-card-state__value">{{ valueLabel }}</span>
      </div>
    </template>
    <v-card class="rounded border" elevation="8" style="width: max-content; min-width: 280px; max-width: 420px">
      <v-card-title class="text-body-1">{{ name }}</v-card-title>
      <v-card-text class="pt-0">
        <template v-if="!(canEdit && (editKind === 'dimensional' || editKind === 'poison'))">
          <div class="d-flex align-center justify-space-between py-1 text-body-2">
            <span class="text-medium-emphasis">Сила</span>
            <span class="font-weight-medium">{{ valueLabel }}</span>
          </div>
        </template>
        <div
          v-for="row in details"
          :key="row.label"
          class="d-flex align-center justify-space-between py-1 text-body-2 ga-3"
        >
          <span class="text-medium-emphasis">{{ row.label }}</span>
          <span class="font-weight-medium">{{ row.value }}</span>
        </div>
        <template v-if="canEdit && editKind === 'numeric'">
          <ClampedNumberField
            v-model="draft"
            class="mt-3"
            label="Значение"
            :min="minValue"
            density="compact"
            hide-details
          />
          <v-btn class="mt-3" color="primary" variant="tonal" size="small" block @click="submitNumeric">
            Изменить
          </v-btn>
        </template>
        <template v-else-if="canEdit && editKind === 'dimensional'">
          <DimensionalNumberInput v-model="draftDim" class="mt-3" label="Сила" />
          <v-btn class="mt-3" color="primary" variant="tonal" size="small" block @click="submitDimensional">
            Изменить
          </v-btn>
          <v-btn class="mt-2" color="error" variant="tonal" size="small" block @click="submitRemove">Убрать</v-btn>
        </template>
        <template v-else-if="canEdit && editKind === 'poison'">
          <v-select
            :model-value="draftPoisonRuleId"
            class="mt-3"
            :items="poisonItems"
            label="Яд"
            density="compact"
            hide-details
            @update:model-value="onPoisonRuleChange"
          />
          <v-select
            v-model="draftDamageType"
            class="mt-3"
            :items="damageTypeItems"
            label="Тип урона"
            density="compact"
            hide-details
          />
          <DimensionalNumberInput v-model="draftStrength" class="mt-3" label="Сила" />
          <v-btn class="mt-3" color="primary" variant="tonal" size="small" block @click="submitPoison">
            Изменить
          </v-btn>
          <v-btn class="mt-2" color="error" variant="tonal" size="small" block @click="submitRemove">Убрать</v-btn>
        </template>
        <v-btn v-else-if="canEdit" class="mt-3" color="error" variant="tonal" size="small" block @click="submitRemove">
          Убрать
        </v-btn>
      </v-card-text>
    </v-card>
  </v-menu>
</template>

<style scoped>
.combat-card-state {
  display: flex;
  align-items: center;
  gap: 6px;
  min-height: 32px;
  padding: 3px 8px;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.14);
  border-radius: 8px;
  cursor: pointer;
  background: rgb(var(--v-theme-surface));
  transition:
    border-color 0.15s ease,
    background-color 0.15s ease;
}
.combat-card-state:hover {
  border-color: rgba(var(--v-theme-primary), 0.5);
  background-color: rgba(var(--v-theme-primary), 0.05);
}
.combat-card-state__label {
  flex: 1;
  font-size: 13px;
  min-width: 0;
}
.combat-card-state__value {
  font-size: 13px;
  font-weight: 500;
  flex-shrink: 0;
}
</style>
