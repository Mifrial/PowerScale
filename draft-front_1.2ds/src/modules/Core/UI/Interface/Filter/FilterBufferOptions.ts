import type { Ref } from 'vue';
import type { FilterField } from '@/modules/Core/UI/Dto/Filter/Field';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';

export interface FilterBufferOptions {
  fields: Ref<FilterField[]>;
  modelValue: Ref<Record<string, FilterValue>>;
  menuOpen: Ref<boolean>;
  onCommit: (value: Record<string, FilterValue>) => void;
}
