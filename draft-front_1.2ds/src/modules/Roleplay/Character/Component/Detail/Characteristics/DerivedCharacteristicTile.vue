<script setup lang="ts">
import { computed } from 'vue';
import type { CharacteristicOverview } from '@/modules/Roleplay/Character/Dto/Overview/CharacteristicOverview';
import CharacteristicTile from '@/modules/Roleplay/Character/Component/Detail/Characteristics/CharacteristicTile.vue';

const props = defineProps<{
  characteristic: CharacteristicOverview;
}>();

const bases = computed(() => props.characteristic.derived?.bases ?? []);
const formulaLabel = computed(() => props.characteristic.derived?.label ?? props.characteristic.derived?.formula ?? '');
</script>

<template>
  <!-- Производная характеристика: слева итог (значение) + подпись формулы, справа базы. Каждая часть кликабельна. -->
  <div class="rounded border pa-2">
    <div class="d-flex align-start ga-3">
      <div class="flex-grow-1" style="min-width: 0">
        <CharacteristicTile :characteristic="characteristic" embedded :caption="formulaLabel" />
      </div>
      <div
        v-if="bases.length"
        class="d-flex flex-column ga-1"
        style="border-left: 1px solid rgba(0, 0, 0, 0.12); padding-left: 10px; min-width: 150px"
      >
        <CharacteristicTile v-for="base in bases" :key="base.ruleId" :characteristic="base" embedded />
      </div>
    </div>
  </div>
</template>
