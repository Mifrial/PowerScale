import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumber';
import type { SpellDuration } from '@/modules/Roleplay/Rule/Dto/Ability/SpellDuration';
import type { SpellComponent } from '@/modules/Roleplay/Rule/Dto/Ability/SpellComponent';

export interface SpellSpec {
  difficulty: DimensionalNumberValue;
  duration: SpellDuration;
  components: SpellComponent[];
}
