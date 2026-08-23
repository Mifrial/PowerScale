/**
 * Спека правила type='damage_type'.
 * `attached_rule_codes` — карточки с механиками (хуки на увечье / применение / атаку), не коды механик.
 */
export interface DamageTypeSpec {
  type: 'damage_type';
  forms: { genitive: string; dative: string };
  attached_rule_codes: string[];
  /** Если true, линии защиты не складываются в сопротивление этому типу. */
  defense_ignored?: boolean;
}
