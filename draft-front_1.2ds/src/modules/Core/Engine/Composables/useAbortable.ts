import { ref, computed, onBeforeUnmount } from 'vue'

export function useAbortable() {
  const controller = ref(new AbortController())
  
  onBeforeUnmount(() => {
    controller.value.abort()
  })
  
  return {
    signal: computed(() => controller.value.signal)
  }
}
