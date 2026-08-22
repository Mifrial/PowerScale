<script setup lang="ts">
/**
 * Лёгкая кнопка (замена v-btn в плотных/виртуализированных списках).
 * Outlined small, без ripple; контент — слот (текст + <i class="mdi …">).
 * Лишние атрибуты (title, class и т.п.) уходят на нативный <button>.
 */
withDefaults(
  defineProps<{
    disabled?: boolean;
    /** Выделение «текущего» состояния (напр. текущий array-уровень). */
    active?: boolean;
  }>(),
  {
    disabled: false,
    active: false,
  },
);
</script>

<template>
  <button type="button" class="light-btn" :class="{ 'light-btn--active': active }" :disabled="disabled">
    <slot />
  </button>
</template>

<style scoped>
.light-btn {
  display: inline-flex;
  align-items: center;
  gap: 2px;
  height: 24px;
  padding: 0 8px;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  background: transparent;
  color: rgb(var(--v-theme-primary));
  font-size: 0.6875rem;
  font-weight: 500;
  line-height: 1;
  cursor: pointer;
  outline: none;
}

.light-btn .mdi {
  font-size: 14px;
  line-height: 1;
}

.light-btn:hover:not(:disabled) {
  background: rgba(var(--v-theme-primary), 0.08);
}

.light-btn:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: 1px;
}

.light-btn:disabled {
  opacity: 0.4;
  cursor: default;
}

.light-btn--active {
  border-color: rgb(var(--v-theme-primary));
  color: rgb(var(--v-theme-primary));
  background: rgba(var(--v-theme-primary), 0.1);
}
</style>
