<script setup lang="ts">
/**
 * Лёгкая раскрывающаяся панель (замена v-expansion-panel на строках списков).
 * Без Vuetify-компонентов: шапка-триггер (div role="button") + тело на v-show
 * с CSS-анимацией высоты через grid-template-rows. Не требует JS-замеров.
 * v-model: контролируемое извне либо автономный state (как SlidePanel).
 */
const open = defineModel<boolean>({ default: false });

function toggle(): void {
  open.value = !open.value;
}

function onKeydown(e: KeyboardEvent): void {
  if (e.key === 'Enter' || e.key === ' ') {
    e.preventDefault();
    toggle();
  }
}
</script>

<template>
  <div class="expandable-item" :class="{ 'expandable-item--open': open }">
    <div
      class="expandable-item__trigger"
      role="button"
      :tabindex="0"
      :aria-expanded="open"
      @click="toggle"
      @keydown="onKeydown"
    >
      <slot name="title" />
    </div>
    <div class="expandable-item__body">
      <div class="expandable-item__content">
        <slot />
      </div>
    </div>
  </div>
</template>

<style scoped>
.expandable-item {
  width: 100%;
}

.expandable-item__trigger {
  display: flex;
  align-items: center;
  cursor: pointer;
  outline: none;
  user-select: none;
}

.expandable-item__trigger:focus-visible {
  outline: 2px solid rgb(var(--v-theme-primary));
  outline-offset: -2px;
}

/* Тело: CSS-анимация высоты через grid-строку 0fr→1fr, без JS-замеров. */
.expandable-item__body {
  display: grid;
  grid-template-rows: 0fr;
  transition: grid-template-rows 0.22s ease;
}

.expandable-item--open > .expandable-item__body {
  grid-template-rows: 1fr;
}

.expandable-item__content {
  overflow: hidden;
  min-height: 0;
}

/* Раскрытая строка — заметная тень (шапка + тело целиком), как у прежней Vuetify-панели. */
.expandable-item--open {
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.22);
}

/* Отделение шапки от тела внутри раскрытой строки. */
.expandable-item--open > .expandable-item__trigger {
  border-bottom: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}
</style>
