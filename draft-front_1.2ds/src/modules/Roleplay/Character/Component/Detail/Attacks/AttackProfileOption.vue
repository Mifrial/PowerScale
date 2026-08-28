<script setup lang="ts">
import type { AttackOverview } from '@/modules/Roleplay/Character/Dto/Overview/AttackOverview';

defineProps<{
  attack: AttackOverview;
  selected?: boolean;
}>();

defineEmits<{
  select: [attack: AttackOverview];
}>();
</script>

<template>
  <v-sheet
    class="attack-profile-option pa-2 rounded border cursor-pointer"
    :class="{ 'attack-profile-option--selected': selected }"
    @click="$emit('select', attack)"
  >
    <div class="d-flex align-center justify-space-between ga-2">
      <span class="text-body-2 font-weight-medium text-truncate">{{ attack.itemName }}</span>
      <span class="text-body-2 text-no-wrap">Дистанция {{ attack.distanceLabel }}</span>
    </div>
    <div class="d-flex align-center justify-space-between ga-2">
      <span class="text-body-2 text-medium-emphasis text-truncate">{{ attack.profileTypeLabel }}</span>
      <span class="text-body-2 text-no-wrap">{{ attack.accuracyLabel }}</span>
    </div>
    <div class="d-flex align-center justify-space-between ga-2">
      <span class="text-body-2 text-medium-emphasis text-truncate">{{ attack.damageLabel }}</span>
      <span class="text-body-2 text-no-wrap">{{ attack.penetrationLabel }}</span>
    </div>
  </v-sheet>
</template>

<style scoped>
.attack-profile-option--selected {
  border-color: rgb(var(--v-theme-primary)) !important;
  background: rgba(var(--v-theme-primary), 0.08);
}
</style>
