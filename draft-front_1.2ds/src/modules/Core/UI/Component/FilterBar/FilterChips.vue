<script setup lang="ts">
import type { ActiveChip } from '@/modules/Core/UI/Dto/Filter/ActiveChip';

defineProps<{
  chips: ActiveChip[];
}>();

defineEmits<{
  remove: [key: string];
}>();
</script>

<template>
  <div class="filter-bar__chips d-flex ga-1 flex-wrap">
    <v-chip v-for="chip in chips" :key="chip.key" size="x-small" closable @click:close.stop="$emit('remove', chip.key)">
      {{ chip.label }}
    </v-chip>
  </div>
</template>

<style scoped>
.filter-bar__chips {
  align-items: center;
  max-width: 70%;
  overflow: hidden;
}
</style>
