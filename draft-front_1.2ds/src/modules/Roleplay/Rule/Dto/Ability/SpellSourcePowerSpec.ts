import type { ScalarFormula } from '@/modules/Roleplay/Rule/Dto/Ability/ScalarFormula';

/** Область действия бонуса к потолку мощи источника. */
export type SpellSourceScope =
  { kind: 'any' } | { kind: 'innate' } | { kind: 'item'; item_code: string } | { kind: 'source'; target_code: string };

/** Бонус к потолку мощи источника при касте. */
export interface SpellSourcePowerSpec {
  scope: SpellSourceScope;
  /**
   * `create` — только при касте через путь; теория (инструмент/умение вне каста)
   * сохраняется без бонуса.
   */
  path_scoped: boolean;
  amount: ScalarFormula;
}
