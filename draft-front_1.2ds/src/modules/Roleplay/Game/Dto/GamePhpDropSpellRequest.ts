/** Запрос прекращения поддержания. */
export interface GamePhpDropSpellRequest {
  gameId: number;
  battleId: number;
  idempotencyKey: string;
  expectedBattleVersion: number;
  expectedSheetVersion: number;
  spellId: string;
  expectedSpellVersion: number;
}
