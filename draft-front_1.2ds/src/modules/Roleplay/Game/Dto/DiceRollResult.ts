import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec';
import type { DiceRollCheckOutcome } from '@/modules/Roleplay/Game/Dto/DiceRollCheckOutcome';
import type { InjuryOutcome } from '@/modules/Roleplay/Game/Dto/InjuryOutcome';

export interface DiceRollResult {
  spec: DiceRollSpec;
  rolls: number[];
  successes: number[];
  adjustedRolls: number[];
  droppedRolls: number[];
  totalSuccesses: number;
  /** Имена механик, реально повлиявших на бросок (напр. «Правило 6 и 1»). */
  appliedMechanics?: string[];
  /** Слой проверки (чат: простая vs {0|0}). Нет — только бросок. */
  check?: DiceRollCheckOutcome;
  /** Скоринг проверки на увечье (не РУ). */
  injury?: InjuryOutcome;
}
