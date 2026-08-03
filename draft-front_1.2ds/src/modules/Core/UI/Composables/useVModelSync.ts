import { ref, watch, toValue } from 'vue'
import type { MaybeRefOrGetter, Ref } from 'vue'

export interface UseVModelSyncOptions<T> {
  modelValue: MaybeRefOrGetter<T>
  onCommit: (value: T) => void
  clone?: boolean
}

function deepClone<T>(value: T): T {
  return structuredClone(value)
}

function shallowClone<T>(value: T): T {
  if (Array.isArray(value)) return [...value] as unknown as T
  if (value && typeof value === 'object') return { ...value } as T
  return value
}

export function useVModelSync<T>(options: UseVModelSyncOptions<T>): { inner: Ref<T>; set: (value: T) => void } {
  const cloneValue = options.clone === false ? shallowClone : deepClone
  const inner = ref<T>(cloneValue(toValue(options.modelValue))) as Ref<T>

  watch(() => toValue(options.modelValue), (value) => {
    if (JSON.stringify(value) !== JSON.stringify(inner.value)) {
      inner.value = cloneValue(value)
    }
  }, { deep: true })

  watch(inner, (value) => {
    options.onCommit(cloneValue(value))
  }, { deep: true })

  function set(value: T): void {
    inner.value = value
  }

  return { inner, set }
}
