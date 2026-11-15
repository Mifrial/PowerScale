import type { DimensionalNumberValue } from '@/modules/Core/Engine/Dto/DimensionalNumberValue';

/** Запрос продолжения поддержания. */
export interface GamePhpSustainSpellRequest {
  gameId: number;
  battleId: number;
  idempotencyKey: string;
  expectedBattleVersion: number;
  expectedSheetVersion: number;
  spellId: string;
  sustainPower: DimensionalNumberValue;
  expectedSpellVersion: number;
}
