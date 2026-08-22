import type { ComputedRef, WritableComputedRef } from 'vue';
import type { ActiveChip } from '@/modules/Core/UI/Dto/Filter/ActiveChip';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';

export interface FilterBuffer {
  editBuffer: Record<string, MaybeFilterValue>;
  enabled: Record<string, boolean>;
  searchText: WritableComputedRef<string>;
  activeChips: ComputedRef<ActiveChip[]>;
  hasActiveFilters: ComputedRef<boolean>;
  onValueUpdate(key: string, value: MaybeFilterValue): void;
  setEnabled(key: string, value: boolean): void;
  removeChip(key: string): void;
  apply(): void;
  resetAll(): void;
}
