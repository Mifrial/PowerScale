import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';

/** Ответ каста (game.runAction kind spell; game.castSpell — алиас). */
export interface GamePhpSpellCastResult {
  cast: {
    difficulty: DimensionalNumberValue;
    needsCheck: boolean;
    roll: Record<string, unknown> | null;
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
