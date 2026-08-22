import type { Ref } from 'vue';

export interface ColumnResizeApi {
  resizingKey: Ref<string | null>;
  resizePerformed: Ref<boolean>;
  start: (key: string, e: MouseEvent) => void;
}
