<script setup lang="ts">
import type { DefenseLineOverview } from '@/modules/Roleplay/Character/Dto/Overview/DefenseOverview';

defineProps<{
  resistance: DefenseLineOverview;
}>();
</script>

<template>
  <v-sheet class="resistance-tile pa-2 rounded border">
    <div class="d-flex align-center ga-2">
      <v-icon icon="mdi-shield-star-outline" color="primary" />
      <span class="text-body-2 font-weight-medium">
        Сопротивление{{ resistance.damageTypeDative ? ` ${resistance.damageTypeDative}` : '' }}
      </span>
    </div>
    <div class="text-body-2 text-medium-emphasis">
      {{ resistance.valueLabel }}
      <span v-if="resistance.protectsInternal">· держит внутренний урон</span>
    </div>
  </v-sheet>
</template>

<style scoped>
.resistance-tile {
  height: 100%;
}
</style>
