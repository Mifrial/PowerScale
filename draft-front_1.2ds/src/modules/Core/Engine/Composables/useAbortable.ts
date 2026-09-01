import { computed, onBeforeUnmount } from 'vue';

/**
 * Держит AbortSignal до размонтирования компонента.
 */
export function useAbortable() {
  const controller = new AbortController();

  onBeforeUnmount(() => {
    controller.abort();
  });

  return {
    signal: computed(() => controller.signal),
  };
}
