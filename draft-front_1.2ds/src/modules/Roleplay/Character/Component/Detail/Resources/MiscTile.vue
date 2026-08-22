<script setup lang="ts">
import type { MiscItemOverview } from '@/modules/Roleplay/Character/Dto/Overview/MiscItemOverview';

defineProps<{
  item: MiscItemOverview;
}>();
</script>

<template>
  <v-sheet class="misc-tile pa-2 rounded border">
    <div class="d-flex align-center justify-space-between ga-2">
      <span class="text-body-2 text-medium-emphasis text-truncate">{{ item.label }}</span>
      <span class="text-body-2 font-weight-medium">{{ item.valueLabel }}</span>
    </div>
    <div v-if="item.subtitle" class="text-caption text-medium-emphasis text-truncate">{{ item.subtitle }}</div>
  </v-sheet>
</template>

<style scoped>
.misc-tile {
  height: 100%;
}
</style>
