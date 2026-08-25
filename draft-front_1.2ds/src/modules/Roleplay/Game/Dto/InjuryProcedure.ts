/** Константы проверки на увечье одной версии (алгоритм в хендлере). */
export interface InjuryProcedure {
  code: string;
  version: string;
  /** Всегда 4к6; Стойкость уже в формуле сложности. */
  poolDice: number;
  woundDivisor: number;
  /** С этого значения истощение даёт сложность exhaustion − offset. */
  exhaustionCheckMin: number;
  exhaustionDifficultyOffset: number;
}
