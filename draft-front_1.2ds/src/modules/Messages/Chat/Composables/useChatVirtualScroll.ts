import { ref, computed } from 'vue';
import { useVirtualizer } from '@tanstack/vue-virtual';

const MIN_ITEM_SIZE = 48;
const OVERSCAN = 8;
const TOP_THRESHOLD = 120;
const END_THRESHOLD = 40;

export function useChatVirtualScroll(opts: {
  getScrollElement: () => HTMLElement | null;
  getCount: () => number;
  getItemKey: (index: number) => string | number;
  onReachTop: () => Promise<void>;
  hasMoreOlder: () => boolean;
  loadingOlder: () => boolean;
}) {
  const scrollElement = ref<HTMLElement | null>(null);

  const virtualizer = useVirtualizer(
    computed(() => ({
      getScrollElement: () => scrollElement.value,
      count: opts.getCount(),
      estimateSize: () => MIN_ITEM_SIZE,
      getItemKey: (index: number) => opts.getItemKey(index),
      overscan: OVERSCAN,
    })),
  );

  const pinnedToEnd = ref(true);
  let reachTopPending = false;

  function isAtEnd(): boolean {
    return virtualizer.value.getDistanceFromEnd() <= END_THRESHOLD;
  }

  function updatePinned() {
    pinnedToEnd.value = isAtEnd();
  }

  async function loadOlderWithCompensation() {
    const el = scrollElement.value;
    if (!el) return;
    const prevHeight = el.scrollHeight;
    await opts.onReachTop();
    // После препенда старых сообщений высота выросла — сдвигаем скролл вниз на дельту,
    // чтобы текущая позиция осталась на месте (классическая компенсация чата).
    requestAnimationFrame(() => {
      if (scrollElement.value) {
        scrollElement.value.scrollTop += scrollElement.value.scrollHeight - prevHeight;
      }
    });
  }

  function onScroll() {
    const el = scrollElement.value;
    if (!el) return;
    updatePinned();

    if (el.scrollTop <= TOP_THRESHOLD && opts.hasMoreOlder() && !opts.loadingOlder() && !reachTopPending) {
      reachTopPending = true;
      loadOlderWithCompensation().finally(() => {
        reachTopPending = false;
      });
    }
  }

  function scrollToEnd() {
    virtualizer.value.scrollToEnd();
  }

  function registerElement(el: unknown) {
    scrollElement.value = el instanceof HTMLElement ? el : null;
  }

  function measureElement(node: unknown) {
    virtualizer.value.measureElement(node as HTMLElement | null);
  }

  return {
    scrollElement,
    virtualizer,
    pinnedToEnd,
    registerElement,
    measureElement,
    onScroll,
    scrollToEnd,
    updatePinned,
    isAtEnd,
  };
}
