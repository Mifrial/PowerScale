<script setup lang="ts">
import { watch, onUnmounted } from 'vue'

const props = withDefaults(defineProps<{
  width?: string
  maxWidth?: string
  bodyFlex?: boolean
  closeOnBackdrop?: boolean
}>(), {
  width: '520px',
  bodyFlex: false,
  closeOnBackdrop: true,
})

const open = defineModel<boolean>({ default: false })

function close() {
  open.value = false
}

function onKeydown(e: KeyboardEvent) {
  if (e.key === 'Escape') {
    close()
  }
}

watch(open, (val) => {
  if (val) {
    document.documentElement.style.overflow = 'hidden'
    document.body.style.overflow = 'hidden'
    window.addEventListener('keydown', onKeydown)
  } else {
    document.documentElement.style.overflow = ''
    document.body.style.overflow = ''
    window.removeEventListener('keydown', onKeydown)
  }
}, { immediate: true })

onUnmounted(() => {
  document.documentElement.style.overflow = ''
  document.body.style.overflow = ''
  window.removeEventListener('keydown', onKeydown)
})
</script>

<template>
  <Teleport to="body">
    <Transition name="slide">
      <div v-if="open" class="slide-wrapper" @keydown.esc="close">
      <div class="slide-backdrop" :class="{ 'slide-backdrop--closable': closeOnBackdrop }" @click="closeOnBackdrop && close()" />
      <div class="slide-panel" :style="{ width, maxWidth }">
        <div class="slide-close-tab" aria-label="Закрыть" @click="close">
          <v-icon>mdi-close</v-icon>
        </div>

        <div v-if="$slots.header" class="slide-header">
          <slot name="header" />
        </div>

        <div class="slide-body" :class="{ 'slide-body--flex': bodyFlex }">
          <slot />
        </div>

        <div v-if="$slots.footer" class="slide-footer">
          <slot name="footer" />
        </div>
      </div>
    </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.slide-wrapper {
  position: fixed;
  inset: 0;
  z-index: 2000;
  display: flex;
  justify-content: flex-end;
}

.slide-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(var(--v-theme-scrim), var(--v-scrim-opacity));
}

.slide-panel {
  position: relative;
  height: 100vh;
  background: rgb(var(--v-theme-surface));
  display: flex;
  flex-direction: column;
}

.slide-close-tab {
  position: absolute;
  top: 10px;
  right: 100%;
  z-index: 1;
  width: 36px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  background:
    linear-gradient(
      to right,
      transparent 0%,
      transparent 80%,
      rgba(var(--v-theme-scrim), var(--v-shadow-sm-opacity)) 92%,
      rgba(var(--v-theme-scrim), var(--v-shadow-md-opacity)) 100%
    ),
    rgb(var(--v-theme-surface));
  border-radius: 8px 0 0 8px;
  cursor: pointer;
  color: rgba(var(--v-theme-on-surface), var(--v-text-disabled-opacity));
  transition: background-color 0.15s ease, color 0.15s ease;
}
.slide-close-tab:hover {
  background-color: rgb(var(--v-theme-primaryLight));
  color: rgb(var(--v-theme-primary));
}

.slide-header {
  flex-shrink: 0;
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 20px;
  border-bottom: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}

.slide-body {
  flex: 1;
  overflow-y: auto;
}
.slide-body--flex {
  overflow: hidden;
  display: flex;
}

.slide-footer {
  flex-shrink: 0;
  border-top: 1px solid rgba(var(--v-theme-divider), var(--v-border-opacity));
}

.slide-enter-active,
.slide-leave-active {
  transition: opacity 0.2s ease;
}
.slide-enter-active .slide-panel,
.slide-leave-active .slide-panel {
  transition: transform 0.25s ease;
}
.slide-enter-from,
.slide-leave-to {
  opacity: 0;
}
.slide-enter-from .slide-panel {
  transform: translateX(100%);
}
.slide-leave-to .slide-panel {
  transform: translateX(100%);
}
</style>
