import { ref, watch, toValue } from 'vue';
import type { Ref } from 'vue';
import type { UseVModelSyncOptions } from '@/modules/Core/UI/Dto/VModelSyncOptions';
import { cloneData } from '@/modules/Core/UI/Utils/cloneData';

function shallowClone<T>(value: T): T {
  if (Array.isArray(value)) return [...value] as unknown as T;
  if (value && typeof value === 'object') return { ...value };

  return value;
}

export function useVModelSync<T>(options: UseVModelSyncOptions<T>): { inner: Ref<T>; set: (value: T) => void } {
  const cloneValue = options.clone === false ? shallowClone : cloneData;
  const inner = ref<T>(cloneValue(toValue(options.modelValue))) as Ref<T>;

  watch(
    () => toValue(options.modelValue),
    (value) => {
      if (JSON.stringify(value) !== JSON.stringify(inner.value)) {
        inner.value = cloneValue(value);
      }
    },
    { deep: true },
  );

  watch(
    inner,
    (value) => {
      options.onCommit(cloneValue(value));
    },
    { deep: true },
  );

  function set(value: T): void {
    inner.value = value;
  }

  return { inner, set };
}
