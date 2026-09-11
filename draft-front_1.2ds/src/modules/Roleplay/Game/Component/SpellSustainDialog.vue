<script setup lang="ts">
import { ref, watch } from 'vue';
import type { ActiveSpell } from '@/modules/Roleplay/Game/Dto/Spell/ActiveSpell';
import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import DimensionalNumberInput from '@/modules/Core/UI/Component/Input/DimensionalNumberInput.vue';
import { CHARACTERISTIC_BASE_RANGE } from '@/modules/Roleplay/Character/init';

const props = defineProps<{
  open: boolean;
  spell: ActiveSpell | null;
  spellName: string;
  maxPower: DimensionalNumberValue;
}>();

const emit = defineEmits<{
  'update:open': [value: boolean];
  continue: [sustainPower: DimensionalNumberValue];
  drop: [];
}>();

const power = ref<DimensionalNumberValue>({ base: 3, size: 0 });

watch(
  () => props.spell,
  (spell) => {
    if (spell) {
      power.value = { ...spell.sustainPower };
    }
  },
);
</script>

<template>
  <v-dialog :model-value="open" max-width="420" persistent @update:model-value="emit('update:open', $event)">
    <v-card v-if="spell">
      <v-card-title class="text-body-1">Поддержание: {{ spellName }}</v-card-title>
      <v-card-text>
        <DimensionalNumberInput
          v-model="power"
          label="Мощь поддержания"
          :min="CHARACTERISTIC_BASE_RANGE.min"
          :max="CHARACTERISTIC_BASE_RANGE.max"
        />
      </v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('drop')">Оборвать</v-btn>
        <v-btn color="primary" @click="emit('continue', power)">Продолжить</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
