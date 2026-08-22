import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';

export interface SpellSpec {
  difficulty: DimensionalNumberValue;
  duration: SpellDuration;
}
