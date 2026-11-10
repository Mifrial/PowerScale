import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';
import type { GamePhpParticipant } from '@/modules/Roleplay/Game/Dto/GamePhpParticipant';
import type { GamePhpSpellDeviation } from '@/modules/Roleplay/Game/Dto/GamePhpSpellDeviation';
import type { GamePhpPendingSpellCast } from '@/modules/Roleplay/Game/Dto/GamePhpPendingSpellCast';

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

/** Одна цель взрыва в ответе каста. */
export interface GamePhpSpellCastBurstRow {
  target: { type: 'character' | 'npc'; id: number };
  damage: DimensionalNumberValue | null;
  injury: DimensionalNumberValue | null;
  sheetVersion: number;
}

/** Блок насыщения в ответе каста. */
export interface GamePhpSpellCastSaturation {
  sourceCode?: string | null;
  minRating?: number;
  ratingPerStep?: number;
  powerPerStep?: number;
  rating?: number;
  maxSteps?: number;
  steps?: number;
  ratingSpent?: number;
  powerDelta?: number;
  remainingRating?: number;
}

/** Поставленный хвост снижения Сложности. */
export interface GamePhpSpellCastNextCast {
  sourceRuleCode?: string | null;
  delta?: number | null;
  maxTotalActionCost?: number | null;
  sourceCode?: string | null;
}

/** Ответ каста (game.runAction kind spell; game.castSpell — алиас). */
export interface GamePhpSpellCastResult {
  phase?: 'check' | 'resolve' | 'abort';
  castId?: string | null;
  battleId?: number;
  version?: number;
  cast: {
    difficulty: DimensionalNumberValue;
    needsCheck: boolean;
    roll: Record<string, unknown> | null;
    castOk?: boolean;
    upgrades?: GamePhpSpellCastAppliedUpgrade[];
  };
  saturation?: GamePhpSpellCastSaturation | null;
  deviation?: GamePhpSpellDeviation | null;
  burst?: GamePhpSpellCastBurstRow[] | null;
  nextCast?: GamePhpSpellCastNextCast[];
  spent: number;
  caster: GamePhpParticipant & { sheetVersion: number };
  target: (GamePhpParticipant & { sheetVersion: number }) | null;
  autoFail: boolean;
  spellSr: number | null;
  spellDamage: DimensionalNumberValue | null;
  injury: DimensionalNumberValue | null;
  consequence: Record<string, unknown>;
  pendingSpellCast?: GamePhpPendingSpellCast | null;
}
