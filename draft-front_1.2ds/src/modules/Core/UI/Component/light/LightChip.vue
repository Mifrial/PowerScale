<script setup lang="ts">
/**
 * Лёгкий чип-бейдж (замена v-chip в плотных/виртуализированных списках).
 * Пилюля small: tonal (заливка) либо outlined (бордер), цвет primary/secondary.
 */
withDefaults(
  defineProps<{
    variant?: 'tonal' | 'outlined';
    color?: 'primary' | 'secondary' | 'warning';
  }>(),
  {
    variant: 'tonal',
    color: undefined,
  },
);
</script>

<template>
  <span class="light-chip" :class="[`light-chip--${variant}`, color ? `light-chip--${color}` : '']">
    <slot />
  </span>
</template>

<style scoped>
.light-chip {
  display: inline-flex;
  align-items: center;
  height: 20px;
  padding: 0 8px;
  border-radius: 9999px;
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1;
  white-space: nowrap;
  color: rgba(var(--v-theme-on-surface), 0.85);
}

.light-chip--tonal {
  background: rgba(var(--v-theme-on-surface), 0.08);
}

.light-chip--tonal.light-chip--primary {
  background: rgba(var(--v-theme-primary), 0.15);
  color: rgb(var(--v-theme-primary));
}

.light-chip--tonal.light-chip--secondary {
  background: rgba(var(--v-theme-secondary), 0.15);
  color: rgb(var(--v-theme-secondary));
}

.light-chip--tonal.light-chip--warning {
  background: rgba(var(--v-theme-warning), 0.15);
  color: rgb(var(--v-theme-warning));
}

.light-chip--outlined {
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  color: rgba(var(--v-theme-on-surface), 0.75);
}
</style>
