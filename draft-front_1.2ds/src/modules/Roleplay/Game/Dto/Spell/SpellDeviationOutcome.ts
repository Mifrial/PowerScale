import type { DiceRollResult } from '@/modules/Roleplay/Game/Dto/DiceRollResult';

/** Исход 2d6 после провала проверки сотворения. */
export interface SpellDeviationOutcome {
  firstFace: number;
  secondFace: number;
  faceSum: number;
  strength: number;
  hasEffect: boolean;
  isDoubles: boolean;
  dieDigit: number;
  roll: DiceRollResult;
}
