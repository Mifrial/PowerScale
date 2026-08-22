import type { Ref } from 'vue';

export interface ColumnResizeScope {
  columnWidths: Ref<Record<string, number>>;
  saveWidths: () => void;
}
