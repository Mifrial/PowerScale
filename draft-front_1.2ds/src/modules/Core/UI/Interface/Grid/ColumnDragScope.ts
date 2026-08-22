import type { ComputedRef } from 'vue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/Grid/ColumnDefinition';

export interface ColumnDragScope {
  displayColumns: ComputedRef<ColumnDefinition[]>;
  saveOrder: (keys: string[]) => void;
}
