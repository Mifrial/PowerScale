<template>
  <v-chip size="small" variant="outlined" class="dimensional-number">
    <span class="base">{{ base }}</span>
    <v-icon v-if="size > 0" size="18" class="separator">mdi-arrow-up</v-icon>
    <v-icon v-else-if="size < 0" size="18" class="separator">mdi-arrow-down</v-icon>
    <span v-else class="separator-text">|</span>
    <span class="size">{{ displaySize }}</span>
  </v-chip>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  base: number
  size: number
}>()

const displaySize = computed(() => {
  return Math.abs(props.size)
})
</script>

<style scoped>
.dimensional-number {
  font-family: monospace;
  font-weight: 500;
}
.base, .size {
  min-width: 1ch;
  text-align: center;
}
.separator {
  margin: 0 2px;
}
.separator-text {
  margin: 0 4px;
}
</style>
