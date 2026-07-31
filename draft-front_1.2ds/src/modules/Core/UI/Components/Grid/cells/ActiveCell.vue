<template>
  <v-chip :color="isActive ? 'success' : 'grey'" variant="tonal" size="x-small">
    <v-icon start size="x-small">{{ isActive ? 'mdi-check-circle' : 'mdi-cancel' }}</v-icon>
    {{ isActive ? 'Активен' : 'Неактивен' }}
  </v-chip>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  value: any
  column?: any
}>()

const isActive = computed(() => {
  if (typeof props.value === 'boolean') return props.value
  if (typeof props.value === 'string') return props.value === 'true'
  return Boolean(props.value)
})
</script>
