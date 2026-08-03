import { computed, onBeforeUnmount } from 'vue';

export function useAbortable() {
  const controller = new AbortController();

  onBeforeUnmount(() => {
    controller.abort();
  });

  return {
    signal: computed(() => controller.signal),
  };
}
