<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { DimensionalNumber } from '@/modules/Core/Engine/Value/DimensionalNumber';
import { spellCastDifficultyService } from '@/modules/Roleplay/Game/Service/Instance/spellCastDifficultyService';

const props = defineProps<{
  open: boolean;
  spell: ActiveSpell | null;
  spellName: string;
  minPower: DimensionalNumberValue | null;
  maxPower: DimensionalNumberValue | null;
  busy?: boolean;
  error?: string | null;
  /** Цена продолжения в мане: мощь × число ходов Создания (0 — источник маны не тратит). */
  manaTurns?: number;
  /** Текущая мана источника или null. */
  mana?: number | null;
  /** Стабильность эффекта при текущей мощи. */
  stability?: DimensionalNumberValue | null;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  continue: [sustainPower: DimensionalNumberValue];
  drop: [];
}>();

const power = ref<DimensionalNumberValue>({ base: 3, size: 0 });

const manaCost = computed(() => {
  const turns = props.manaTurns ?? 0;
  if (turns <= 0) return 0;

  return DimensionalNumber.from(power.value).toNumber() * turns;
});

const manaShortage = computed(() => props.mana !== null && props.mana !== undefined && manaCost.value > props.mana);

const stabilityLabel = computed(() =>
  props.stability ? DimensionalNumber.from(props.stability).toString() : null,
);

const rangeLabel = computed(() => {
  const min = props.minPower ? DimensionalNumber.from(props.minPower).toString() : null;
  const max = props.maxPower ? DimensionalNumber.from(props.maxPower).toString() : null;
  if (min && max) return `от ${min} до ${max}`;
  if (min) return `не ниже ${min}`;
  if (max) return `не выше ${max}`;

  return null;
});

function clampPower(value: DimensionalNumberValue): DimensionalNumberValue {
  return spellCastDifficultyService.clampToRange(value, props.minPower, props.maxPower);
}

watch(
  () => props.spell,
  (spell) => {
    if (spell) {
      power.value = clampPower({ ...spell.sustainPower });
    }
  },
);

function submitContinue(): void {
  power.value = clampPower(power.value);
  emit('continue', power.value);
}
</script>

<template>
  <v-dialog :model-value="open" max-width="420" persistent @update:model-value="emit('update:open', $event)">
    <v-card v-if="spell">
      <v-card-title class="text-body-1">Поддержание: {{ spellName }}</v-card-title>
      <v-card-text>
        <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mb-3">{{ error }}</v-alert>
        <DimensionalNumberInput
          v-model="power"
          label="Мощь поддержания"
          :min="minPower?.base"
          :max="maxPower?.base"
          :size-min="minPower?.size"
          :size-max="maxPower?.size"
          :disabled="busy"
        />
        <div v-if="rangeLabel" class="text-caption text-medium-emphasis mt-2">Допустимо {{ rangeLabel }}.</div>
        <div v-if="manaTurns" class="text-caption text-medium-emphasis mt-1">
          Мана: {{ manaCost }}<template v-if="mana !== null && mana !== undefined"> / {{ mana }}</template>
        </div>
        <div v-if="manaShortage" class="text-caption text-warning mt-1">
          Не хватает маны источника: нужно {{ manaCost }}, есть {{ mana }}
        </div>
        <div v-if="stabilityLabel" class="text-caption text-medium-emphasis mt-1">
          Стабильность: {{ stabilityLabel }}
        </div>
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" :disabled="busy" @click="emit('drop')">Оборвать</v-btn>
        <v-btn color="primary" :loading="busy" :disabled="manaShortage" @click="submitContinue">Продолжить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
