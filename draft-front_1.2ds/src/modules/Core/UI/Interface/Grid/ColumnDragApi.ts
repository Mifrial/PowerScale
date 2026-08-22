import type { Ref } from 'vue';
import type { ColumnDropTarget } from '@/modules/Core/UI/Interface/Grid/ColumnDropTarget';

export interface ColumnDragApi {
  dragKey: Ref<string | null>;
  dropTarget: Ref<ColumnDropTarget | null>;
  start: (key: string, e: DragEvent) => void;
  enter: (key: string, e: DragEvent) => void;
  drop: (key: string, e: DragEvent) => void;
  end: () => void;
}
