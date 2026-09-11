import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';

/** Бросок сотворения или пропуск, если сложность {0|-1}. */
export interface SpellCastRollOutcome {
  difficulty: DimensionalNumberValue;
  needsCheck: boolean;
  roll: DiceRollResult | null;
}
