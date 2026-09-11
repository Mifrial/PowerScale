/** Слой урона одного типа в одном ударе. */
export interface InjuryDamageLayer {
  hpDamage: number;
  remainingSr: number;
  damageTypeCode: string | null;
  cuttingWound?: number | null;
  woundFromHit?: number | null;
}
