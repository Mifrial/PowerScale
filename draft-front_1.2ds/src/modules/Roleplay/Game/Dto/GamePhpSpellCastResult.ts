import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Применённое сервером улучшение каста. */
export interface GamePhpSpellCastAppliedUpgrade {
  code: string;
  name: string;
  value: number;
  actionPointDelta: number;
  checkAdvantage: number;
  resistancePenetration: number;
  requiredPowerDelta: number;
}

/** Ответ каста (game.runAction kind spell; game.castSpell — алиас). */
export interface GamePhpSpellCastResult {
  cast: {
    difficulty: DimensionalNumberValue;
    needsCheck: boolean;
    roll: Record<string, unknown> | null;
    upgrades?: GamePhpSpellCastAppliedUpgrade[];
  };
  spent: number;
  caster: GamePhpParticipant & { sheetVersion: number };
  target: (GamePhpParticipant & { sheetVersion: number }) | null;
  autoFail: boolean;
  spellSr: number | null;
  spellDamage: DimensionalNumberValue | null;
  injury: DimensionalNumberValue | null;
  consequence: Record<string, unknown>;
}
