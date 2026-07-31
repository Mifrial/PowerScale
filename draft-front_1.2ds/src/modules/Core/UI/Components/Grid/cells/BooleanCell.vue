<template>
  <span :style="{ color: color }">
    <v-icon v-if="icon" :icon="icon" :color="color" size="small" class="mr-1" />
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ColumnDefinition } from '@/modules/Core/UI/Interfaces/ColumnDefinition'

const props = defineProps<{
  value: any
  column?: ColumnDefinition
}>()

const cfg = computed(() => props.column?.meta ?? {})
const label = computed(() =>
  props.value ? (cfg.value.trueLabel ?? 'Да') : (cfg.value.falseLabel ?? 'Нет'),
)
const icon = computed(() => (props.value ? cfg.value.trueIcon : cfg.value.falseIcon) || undefined)
const color = computed(() => (props.value ? cfg.value.trueColor : cfg.value.falseColor) || undefined)
</script>
