<script setup lang="ts">
import { computed } from 'vue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

const props = defineProps<{
  value: unknown;
  column?: ColumnDefinition;
}>();

const cfg = computed(() => props.column?.meta ?? {});
const label = computed(() => (props.value ? (cfg.value.trueLabel ?? 'Да') : (cfg.value.falseLabel ?? 'Нет')));
const icon = computed(() => (props.value ? cfg.value.trueIcon : cfg.value.falseIcon) || undefined);
const color = computed(() => (props.value ? cfg.value.trueColor : cfg.value.falseColor) || undefined);
</script>

<template>
  <span :style="{ color: color }">
    <v-icon v-if="icon" :icon="icon" :color="color" size="small" class="mr-1" />
    {{ label }}
  </span>
</template>
