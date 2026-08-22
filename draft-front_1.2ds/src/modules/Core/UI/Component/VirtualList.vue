<script setup lang="ts" generic="T">
import { computed, ref, watch } from 'vue';
import { useVirtualizer } from '@tanstack/vue-virtual';

/**
 * Виртуализированный список: в DOM рендерится только видимая часть строк (+ overscan),
 * высота строк может быть произвольной (замеряется через ResizeObserver).
 * Паттерн — как в чате (useChatVirtualScroll).
 */
const props = withDefaults(
  defineProps<{
    items: T[];
    /** Начальная оценка высоты строки до замера (px). */
    estimateSize?: number;
    /** Сколько строк рендерить за пределами видимой области. */
    overscan?: number;
    getItemKey?: (item: T, index: number) => string | number;
    /** Высота скролл-области (число — px или строка CSS). */
    height?: string | number;
    maxHeight?: string | number;
    emptyText?: string;
    /** При изменении скролл сбрасывается наверх и пере-замеряется (смена фильтров). */
    resetKey?: string;
  }>(),
  {
    estimateSize: 56,
    overscan: 8,
    getItemKey: (_item: T, index: number) => index,
    height: undefined,
    maxHeight: undefined,
    emptyText: '',
    resetKey: '',
  },
);

const scrollElement = ref<HTMLElement | null>(null);

const virtualizer = useVirtualizer(
  computed(() => ({
    getScrollElement: () => scrollElement.value,
    count: props.items.length,
    estimateSize: () => props.estimateSize,
    getItemKey: (index: number) => props.getItemKey(props.items[index], index),
    overscan: props.overscan,
    // Батчим ResizeObserver-замеры в rAF: меньше синхронных блокировок при скролле.
    useAnimationFrameWithResizeObserver: true,
  })),
);

function measureElement(node: unknown): void {
  virtualizer.value.measureElement(node as HTMLElement | null);
}

watch(
  () => props.resetKey,
  () => {
    virtualizer.value.scrollToOffset(0);
    virtualizer.value.measure();
  },
);

function scrollToTop(): void {
  virtualizer.value.scrollToOffset(0);
}

defineExpose({
  scrollToTop,
  measure: () => virtualizer.value.measure(),
});
</script>

<template>
  <div ref="scrollElement" class="virtual-list" :style="{ height, maxHeight }">
    <div v-if="items.length === 0" class="virtual-list__empty">{{ emptyText }}</div>
    <div v-else class="virtual-viewport">
      <div class="virtual-content" :style="{ height: `${virtualizer.getTotalSize()}px` }">
        <div
          v-for="vItem in virtualizer.getVirtualItems()"
          :key="String(vItem.key)"
          :data-index="vItem.index"
          :ref="measureElement"
          class="virtual-item"
          :style="{ transform: `translateY(${vItem.start}px)` }"
        >
          <slot :item="items[vItem.index]" :index="vItem.index" />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.virtual-list {
  position: relative;
  overflow-y: auto;
  overscroll-behavior: contain;
}

.virtual-viewport {
  position: relative;
  contain: layout paint;
}

.virtual-content {
  position: relative;
  width: 100%;
}

.virtual-item {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  contain: layout style;
}

.virtual-list__empty {
  padding: 16px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
</style>
