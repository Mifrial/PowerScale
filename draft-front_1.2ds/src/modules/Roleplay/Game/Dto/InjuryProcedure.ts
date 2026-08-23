/** Константы скоринга увечья одной версии (алгоритм в хендлере). */
export interface InjuryProcedure {
  code: string;
  version: string;
  explodeFace: number;
  /** Грани строго ниже порога убираются после взрыва (3 = убрать 1 и 2). */
  dropBelow: number;
  woundDiceDivisor: number;
  exhaustionCheckMin: number;
  exhaustionDiceOffset: number;
}
