import type { MaybeRefOrGetter } from 'vue';

export interface UseVModelSyncOptions<T> {
  modelValue: MaybeRefOrGetter<T>;
  onCommit: (value: T) => void;
  clone?: boolean;
}
