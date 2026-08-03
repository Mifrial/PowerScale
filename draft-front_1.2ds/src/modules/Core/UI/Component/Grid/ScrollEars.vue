<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useScrollEars } from '@/modules/Core/UI/Composables/useScrollEars'
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition'

const props = defineProps<{
  columns: ColumnDefinition[]
}>()

const scrollContainer = ref<HTMLElement | null>(null)
const { earsLeft, earsRight, updateEars, scroll, initEars, cleanup } = useScrollEars({ scrollContainer })

watch(() => props.columns, () => nextTick(initEars), { immediate: true })

onMounted(() => {
  setTimeout(initEars, 500)
})

onBeforeUnmount(cleanup)
</script>

<template>
  <div class="smart-ears-wrapper">
    <div class="smart-ear smart-ear--left" v-show="earsLeft" @click="scroll(-1)">‹</div>
    <div class="smart-ear smart-ear--right" v-show="earsRight" @click="scroll(1)">›</div>
    <div class="smart-ears-scroll" ref="scrollContainer" @mouseenter="updateEars">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.smart-ears-wrapper {
  position: relative;
  overflow: hidden;
}
.smart-ears-scroll {
  overflow-x: auto;
  scrollbar-width: thin;
}
.smart-ear {
  position: absolute;
  top: var(--ear-top, 44px);
  bottom: var(--ear-bottom, 44px);
  width: 20px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(var(--v-theme-primary), 0.08);
  border: thin solid rgba(var(--v-theme-primary), 0.15);
  cursor: pointer;
  z-index: 2;
  padding: 0;
  transition: background 0.15s, opacity 0.15s;
  color: rgb(var(--v-theme-primary));
  font-size: 14px;
  user-select: none;
  backdrop-filter: blur(2px);
}
.smart-ear:hover {
  background: rgba(var(--v-theme-primary), 0.15);
}
.smart-ear--left {
  left: 0;
  border-radius: 0 8px 8px 0;
  border-left: none;
}
.smart-ear--right {
  right: 0;
  border-radius: 8px 0 0 8px;
  border-right: none;
}
</style>
