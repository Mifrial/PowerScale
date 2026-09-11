import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Прыжки улучшения вроде Цепной молнии: повтор автопопадания после повреждений. */
export interface SpellChain {
  damage_size_per_hop: number;
  min: DimensionalNumberValue;
  retarget: 'from_last_hit';
  same_target: 'via_other';
}
