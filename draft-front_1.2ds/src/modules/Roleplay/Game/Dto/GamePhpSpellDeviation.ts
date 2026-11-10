/** Взрыв при дубле отклонения. */
export interface GamePhpSpellDeviationBurst {
  damageTypeCode: string;
  successRating: number;
  /** Радиус в ипари или null. */
  radius: number | null;
}

/** 2d6 после провала проверки сотворения. */
export interface GamePhpSpellDeviation {
  firstFace: number;
  secondFace: number;
  faceSum: number;
  strength: number;
  hasEffect: boolean;
  isDoubles: boolean;
  dieDigit: number;
  burst: GamePhpSpellDeviationBurst | null;
}
